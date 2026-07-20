<?php

namespace App\Services\Organizations;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(protected OrganizationContext $organizationContext)
    {
    }

    /**
     * @param  array{name: string, email: string, password: string, organization_name?: string, invitation_token?: string|null}  $data
     */
    public function registerFirm(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $invitationToken = $data['invitation_token'] ?? null;

            if ($invitationToken) {
                $invitation = OrganizationInvitation::query()
                    ->where('token', $invitationToken)
                    ->first();

                if ($invitation && $invitation->isPending() && strcasecmp($invitation->email, $user->email) === 0) {
                    $this->acceptInvitation($invitation, $user);
                    $this->organizationContext->set($invitation->organization);

                    return $user->fresh();
                }
            }

            $organization = Organization::create([
                'name' => $data['organization_name'] ?? ($data['name']."'s Firm"),
            ]);

            $this->attachMember($organization, $user, OrganizationMembership::ROLE_OWNER);
            $user->forceFill(['current_organization_id' => $organization->id])->save();
            $this->organizationContext->set($organization);

            return $user->fresh();
        });
    }

    public function attachMember(
        Organization $organization,
        User $user,
        string $role = OrganizationMembership::ROLE_CREATOR,
        string $status = OrganizationMembership::STATUS_ACTIVE,
    ): OrganizationMembership {
        return OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'status' => $status,
            ]
        );
    }

    public function switchOrganization(User $user, Organization $organization): Organization
    {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->firstOrFail();

        $user->forceFill(['current_organization_id' => $organization->id])->save();
        $this->organizationContext->set($organization);

        return $membership->organization;
    }

    public function invite(
        Organization $organization,
        User $inviter,
        string $email,
        string $role,
    ): OrganizationInvitation {
        if (! in_array($role, [OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_CREATOR], true)) {
            throw ValidationException::withMessages([
                'role' => 'Invitations may only assign admin or creator roles.',
            ]);
        }

        $email = strtolower(trim($email));

        $existingMember = User::query()->where('email', $email)->first();
        if ($existingMember) {
            $already = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $existingMember->id)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->exists();

            if ($already) {
                throw ValidationException::withMessages([
                    'email' => 'That user is already a member of this firm.',
                ]);
            }
        }

        return OrganizationInvitation::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => $email,
            ],
            [
                'role' => $role,
                'token' => Str::random(64),
                'invited_by' => $inviter->id,
                'accepted_at' => null,
                'expires_at' => now()->addDays(7),
            ]
        );
    }

    public function acceptInvitation(OrganizationInvitation $invitation, User $user): OrganizationMembership
    {
        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'This invitation was sent to a different email address.',
            ]);
        }

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation is no longer valid.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $user) {
            $membership = $this->attachMember(
                $invitation->organization,
                $user,
                $invitation->role,
            );

            $invitation->update(['accepted_at' => now()]);

            if (! $user->current_organization_id) {
                $user->forceFill(['current_organization_id' => $invitation->organization_id])->save();
            }

            return $membership;
        });
    }

    public function updateMemberRole(
        Organization $organization,
        User $actor,
        User $member,
        string $role,
    ): OrganizationMembership {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        if ($membership->isOwner() && $role !== OrganizationMembership::ROLE_OWNER) {
            $ownerCount = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('role', OrganizationMembership::ROLE_OWNER)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Transfer ownership before demoting the final owner.',
                ]);
            }
        }

        if ($member->id === $actor->id && $membership->isOwner() && $role !== OrganizationMembership::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'role' => 'You cannot demote your own owner role.',
            ]);
        }

        if (! in_array($role, [
            OrganizationMembership::ROLE_OWNER,
            OrganizationMembership::ROLE_ADMIN,
            OrganizationMembership::ROLE_CREATOR,
        ], true)) {
            throw ValidationException::withMessages([
                'role' => 'Invalid organization role.',
            ]);
        }

        $membership->update(['role' => $role]);

        return $membership->fresh();
    }

    public function removeMember(Organization $organization, User $actor, User $member): void
    {
        if ($actor->id === $member->id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot remove yourself. Ask another owner/admin, or transfer ownership first.',
            ]);
        }

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        if ($membership->isOwner()) {
            $ownerCount = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('role', OrganizationMembership::ROLE_OWNER)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'member' => 'Cannot remove the final owner of this firm.',
                ]);
            }
        }

        $membership->delete();

        if ($member->current_organization_id === $organization->id) {
            $next = OrganizationMembership::query()
                ->where('user_id', $member->id)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->orderBy('id')
                ->first();

            $member->forceFill([
                'current_organization_id' => $next?->organization_id,
            ])->save();
        }
    }
}
