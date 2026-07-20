<?php

namespace App\Services\Organizations;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyOrganizationMigrator
{
    /**
     * Tables that can be remapped by an owning user column.
     *
     * @var array<string, string>
     */
    protected array $userOwnedTables = [
        'social_accounts' => 'user_id',
        'media_assets' => 'user_id',
        'posts' => 'user_id',
        'in_app_notifications' => 'user_id',
        'automation_campaigns' => 'created_by',
    ];

    /**
     * @return array{organizations_created: int, memberships_created: int, users_updated: int, rows_moved: int, skipped: int}
     */
    public function migrate(bool $dryRun = false): array
    {
        $stats = [
            'organizations_created' => 0,
            'memberships_created' => 0,
            'users_updated' => 0,
            'rows_moved' => 0,
            'skipped' => 0,
        ];

        $aiEmail = strtolower((string) config('automation.ai_agent_email', 'ai-agent@pixflow.local'));
        $legacyOrg = $this->legacyOrganization();

        $users = User::query()->orderBy('id')->get();

        /** @var array<int, Organization> $userOrganizations */
        $userOrganizations = [];

        $callback = function () use ($users, $legacyOrg, $aiEmail, $dryRun, &$stats, &$userOrganizations) {
            foreach ($users as $user) {
                if (strcasecmp((string) $user->email, $aiEmail) === 0) {
                    $stats['skipped']++;

                    continue;
                }

                $existingOwned = OrganizationMembership::query()
                    ->where('user_id', $user->id)
                    ->where('role', OrganizationMembership::ROLE_OWNER)
                    ->where('status', OrganizationMembership::STATUS_ACTIVE)
                    ->where(function ($query) use ($legacyOrg) {
                        if ($legacyOrg) {
                            $query->where('organization_id', '!=', $legacyOrg->id);
                        }
                    })
                    ->with('organization')
                    ->first();

                if ($existingOwned?->organization) {
                    $userOrganizations[$user->id] = $existingOwned->organization;
                    $stats['skipped']++;

                    continue;
                }

                if (! $this->shouldOwnFirm($user)) {
                    continue;
                }

                $organization = $this->ensureFirmForOwner($user, $dryRun, $stats);
                $userOrganizations[$user->id] = $organization;
                $stats['rows_moved'] += $this->moveUserOwnedRows($user, $organization, $dryRun);
            }

            $fallbackOrg = collect($userOrganizations)->first() ?? $legacyOrg;

            foreach ($users as $user) {
                if (strcasecmp((string) $user->email, $aiEmail) === 0) {
                    continue;
                }

                if (isset($userOrganizations[$user->id])) {
                    $this->ensureCurrentOrganization($user, $userOrganizations[$user->id], $dryRun, $stats);

                    continue;
                }

                $membership = OrganizationMembership::query()
                    ->where('user_id', $user->id)
                    ->where('status', OrganizationMembership::STATUS_ACTIVE)
                    ->where(function ($query) use ($legacyOrg) {
                        if ($legacyOrg) {
                            $query->where('organization_id', '!=', $legacyOrg->id);
                        }
                    })
                    ->with('organization')
                    ->first();

                if ($membership?->organization) {
                    $userOrganizations[$user->id] = $membership->organization;
                    $this->ensureCurrentOrganization($user, $membership->organization, $dryRun, $stats);

                    continue;
                }

                if (! $fallbackOrg) {
                    $organization = $this->ensureFirmForOwner($user, $dryRun, $stats);
                    $userOrganizations[$user->id] = $organization;
                    $stats['rows_moved'] += $this->moveUserOwnedRows($user, $organization, $dryRun);
                    $this->ensureCurrentOrganization($user, $organization, $dryRun, $stats);

                    continue;
                }

                $this->attachCreator($fallbackOrg, $user, $dryRun, $stats);
                $userOrganizations[$user->id] = $fallbackOrg;
                $stats['rows_moved'] += $this->moveUserOwnedRows($user, $fallbackOrg, $dryRun);
                $this->ensureCurrentOrganization($user, $fallbackOrg, $dryRun, $stats);
            }

            $stats['rows_moved'] += $this->backfillChildRows($dryRun);
            $stats['rows_moved'] += $this->backfillOrphanRows($legacyOrg, $userOrganizations, $dryRun);
        };

        if ($dryRun) {
            $callback();
        } else {
            DB::transaction($callback);
        }

        return $stats;
    }

    protected function shouldOwnFirm(User $user): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return true;
        }

        if (Schema::hasTable('social_accounts') && $user->socialAccounts()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{organizations_created: int, memberships_created: int, users_updated: int, rows_moved: int, skipped: int}  $stats
     */
    protected function ensureFirmForOwner(User $user, bool $dryRun, array &$stats): Organization
    {
        $name = trim((string) $user->name) !== '' ? $user->name."'s Firm" : 'Firm '.$user->id;

        if ($dryRun) {
            $stats['organizations_created']++;
            $stats['memberships_created']++;

            return new Organization([
                'id' => -1 * $user->id,
                'name' => $name,
                'slug' => Str::slug($name).'-'.$user->id,
            ]);
        }

        $organization = Organization::query()->create([
            'name' => $name,
            'automation_enabled' => false,
        ]);

        $stats['organizations_created']++;

        OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => OrganizationMembership::ROLE_OWNER,
                'status' => OrganizationMembership::STATUS_ACTIVE,
            ]
        );

        $stats['memberships_created']++;

        $legacyOrg = $this->legacyOrganization();
        if ($legacyOrg) {
            OrganizationMembership::query()
                ->where('organization_id', $legacyOrg->id)
                ->where('user_id', $user->id)
                ->delete();
        }

        return $organization;
    }

    /**
     * @param  array{organizations_created: int, memberships_created: int, users_updated: int, rows_moved: int, skipped: int}  $stats
     */
    protected function attachCreator(Organization $organization, User $user, bool $dryRun, array &$stats): void
    {
        if ($dryRun || (int) $organization->id < 1) {
            $stats['memberships_created']++;

            return;
        }

        OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => OrganizationMembership::ROLE_CREATOR,
                'status' => OrganizationMembership::STATUS_ACTIVE,
            ]
        );

        $stats['memberships_created']++;

        $legacyOrg = $this->legacyOrganization();
        if ($legacyOrg && (int) $legacyOrg->id !== (int) $organization->id) {
            OrganizationMembership::query()
                ->where('organization_id', $legacyOrg->id)
                ->where('user_id', $user->id)
                ->delete();
        }
    }

    /**
     * @param  array{organizations_created: int, memberships_created: int, users_updated: int, rows_moved: int, skipped: int}  $stats
     */
    protected function ensureCurrentOrganization(User $user, Organization $organization, bool $dryRun, array &$stats): void
    {
        if ($dryRun || (int) $organization->id < 1) {
            return;
        }

        if ((int) $user->current_organization_id === (int) $organization->id) {
            return;
        }

        $user->forceFill(['current_organization_id' => $organization->id])->save();
        $stats['users_updated']++;
    }

    protected function moveUserOwnedRows(User $user, Organization $organization, bool $dryRun): int
    {
        if ($dryRun || (int) $organization->id < 1) {
            return 0;
        }

        $moved = 0;

        foreach ($this->userOwnedTables as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id') || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $moved += DB::table($table)
                ->where($column, $user->id)
                ->where(function ($query) use ($organization) {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', '!=', $organization->id);
                })
                ->update(['organization_id' => $organization->id]);
        }

        return $moved;
    }

    protected function backfillChildRows(bool $dryRun): int
    {
        if ($dryRun) {
            return 0;
        }

        $moved = 0;

        $moved += $this->copyOrganizationFromParent(
            'social_destinations',
            'social_account_id',
            'social_accounts',
        );

        $moved += $this->copyOrganizationFromParent(
            'post_targets',
            'post_id',
            'posts',
        );

        $moved += $this->copyOrganizationFromParent(
            'publish_logs',
            'post_target_id',
            'post_targets',
        );

        $moved += $this->copyOrganizationFromParent(
            'queue_slots',
            'social_destination_id',
            'social_destinations',
        );

        $moved += $this->copyOrganizationFromParent(
            'recurring_schedules',
            'post_id',
            'posts',
        );

        $moved += $this->copyOrganizationFromParent(
            'automation_runs',
            'automation_campaign_id',
            'automation_campaigns',
        );

        $moved += $this->copyOrganizationFromParent(
            'automation_run_items',
            'automation_run_id',
            'automation_runs',
        );

        return $moved;
    }

    protected function copyOrganizationFromParent(string $childTable, string $foreignKey, string $parentTable): int
    {
        if (! Schema::hasTable($childTable)
            || ! Schema::hasColumn($childTable, 'organization_id')
            || ! Schema::hasColumn($childTable, $foreignKey)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, 'organization_id')) {
            return 0;
        }

        $moved = 0;
        $rows = DB::table($childTable)->select(['id', $foreignKey, 'organization_id'])->orderBy('id')->get();

        foreach ($rows as $row) {
            $parentOrgId = DB::table($parentTable)
                ->where('id', $row->{$foreignKey})
                ->value('organization_id');

            if (! $parentOrgId || (int) $row->organization_id === (int) $parentOrgId) {
                continue;
            }

            DB::table($childTable)
                ->where('id', $row->id)
                ->update(['organization_id' => $parentOrgId]);

            $moved++;
        }

        return $moved;
    }

    /**
     * @param  array<int, Organization>  $userOrganizations
     */
    protected function backfillOrphanRows(?Organization $legacyOrg, array $userOrganizations, bool $dryRun): int
    {
        if ($dryRun) {
            return 0;
        }

        $fallbackId = collect($userOrganizations)
            ->filter(fn (Organization $org) => (int) $org->id > 0)
            ->map(fn (Organization $org) => (int) $org->id)
            ->first()
            ?? $legacyOrg?->id;

        if (! $fallbackId) {
            return 0;
        }

        $moved = 0;
        $tables = [
            'social_accounts',
            'social_destinations',
            'media_assets',
            'posts',
            'post_targets',
            'queue_slots',
            'recurring_schedules',
            'publish_logs',
            'automation_campaigns',
            'automation_runs',
            'automation_run_items',
            'content_categories',
            'prompt_templates',
            'in_app_notifications',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            $moved += DB::table($table)
                ->whereNull('organization_id')
                ->update(['organization_id' => $fallbackId]);
        }

        return $moved;
    }

    protected function legacyOrganization(): ?Organization
    {
        return Organization::query()
            ->where(function ($query) {
                $query->where('slug', 'legacy-organization')
                    ->orWhere('name', 'Legacy Organization');
            })
            ->orderBy('id')
            ->first();
    }
}
