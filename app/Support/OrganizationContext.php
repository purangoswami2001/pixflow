<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use RuntimeException;

class OrganizationContext
{
    protected ?Organization $organization = null;

    protected ?int $organizationId = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
        $this->organizationId = $organization?->id;
    }

    public function setId(?int $organizationId): void
    {
        $this->organizationId = $organizationId;
        $this->organization = null;
    }

    public function id(): ?int
    {
        return $this->organizationId ?? $this->organization?->id;
    }

    public function organization(): ?Organization
    {
        if ($this->organization) {
            return $this->organization;
        }

        if ($this->organizationId) {
            $this->organization = Organization::query()->find($this->organizationId);
        }

        return $this->organization;
    }

    public function requireId(): int
    {
        $id = $this->id();

        if (! $id) {
            throw new RuntimeException('No active organization context is set.');
        }

        return $id;
    }

    public function clear(): void
    {
        $this->organization = null;
        $this->organizationId = null;
    }

    public function resolveForUser(User $user, ?int $preferredOrganizationId = null): ?Organization
    {
        $preferred = $preferredOrganizationId ?? $user->current_organization_id;

        if ($preferred) {
            $membership = OrganizationMembership::query()
                ->where('user_id', $user->id)
                ->where('organization_id', $preferred)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->first();

            if ($membership) {
                $organization = $membership->organization;
                $this->set($organization);

                if ($user->current_organization_id !== $organization->id) {
                    $user->forceFill(['current_organization_id' => $organization->id])->saveQuietly();
                }

                return $organization;
            }
        }

        $membership = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->orderBy('id')
            ->first();

        if (! $membership) {
            $this->clear();

            return null;
        }

        $organization = $membership->organization;
        $this->set($organization);

        if ($user->current_organization_id !== $organization->id) {
            $user->forceFill(['current_organization_id' => $organization->id])->saveQuietly();
        }

        return $organization;
    }
}
