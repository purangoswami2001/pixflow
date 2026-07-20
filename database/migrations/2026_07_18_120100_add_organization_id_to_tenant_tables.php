<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addNullableOrganizationColumns();
        $organizationId = $this->ensureLegacyOrganization();
        $this->backfillOrganizationIds($organizationId);
        $this->replaceUniqueIndexes();
    }

    public function down(): void
    {
        if (Schema::hasTable('social_accounts') && Schema::hasColumn('social_accounts', 'organization_id')) {
            try {
                Schema::table('social_accounts', function (Blueprint $table) {
                    $table->dropUnique('social_accounts_org_provider_user_unique');
                });
            } catch (\Throwable) {
            }

            Schema::table('social_accounts', function (Blueprint $table) {
                $table->unique(['provider', 'provider_user_id']);
            });
        }

        if (Schema::hasTable('social_destinations') && Schema::hasColumn('social_destinations', 'organization_id')) {
            try {
                Schema::table('social_destinations', function (Blueprint $table) {
                    $table->dropUnique('social_destinations_org_provider_dest_unique');
                });
            } catch (\Throwable) {
            }

            Schema::table('social_destinations', function (Blueprint $table) {
                $table->unique(['provider', 'provider_destination_id']);
            });
        }

        foreach ($this->tenantTables() as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }

    protected function addNullableOrganizationColumns(): void
    {
        foreach ($this->tenantTables() as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }
    }

    protected function ensureLegacyOrganization(): int
    {
        $existing = DB::table('organizations')->orderBy('id')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $now = now();

        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Legacy Organization',
            'slug' => 'legacy-organization',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function backfillOrganizationIds(int $organizationId): void
    {
        $users = DB::table('users')->orderBy('id')->get(['id']);

        foreach ($users as $user) {
            $role = 'creator';

            if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
                $isAdmin = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.model_id', $user->id)
                    ->where('roles.name', 'admin')
                    ->exists();

                $role = $isAdmin ? 'owner' : 'creator';
            }

            $exists = DB::table('organization_user')
                ->where('organization_id', $organizationId)
                ->where('user_id', $user->id)
                ->exists();

            if (! $exists) {
                DB::table('organization_user')->insert([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'role' => $role,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')
                ->where('id', $user->id)
                ->whereNull('current_organization_id')
                ->update(['current_organization_id' => $organizationId]);
        }

        foreach ($this->tenantTables() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organization_id')) {
                DB::table($table)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
            }
        }
    }

    protected function replaceUniqueIndexes(): void
    {
        if (Schema::hasTable('social_accounts') && Schema::hasColumn('social_accounts', 'organization_id')) {
            try {
                Schema::table('social_accounts', function (Blueprint $table) {
                    $table->dropUnique(['provider', 'provider_user_id']);
                });
            } catch (\Throwable) {
            }

            Schema::table('social_accounts', function (Blueprint $table) {
                $table->unique(['organization_id', 'provider', 'provider_user_id'], 'social_accounts_org_provider_user_unique');
            });
        }

        if (Schema::hasTable('social_destinations') && Schema::hasColumn('social_destinations', 'organization_id')) {
            try {
                Schema::table('social_destinations', function (Blueprint $table) {
                    $table->dropUnique(['provider', 'provider_destination_id']);
                });
            } catch (\Throwable) {
            }

            Schema::table('social_destinations', function (Blueprint $table) {
                $table->unique(['organization_id', 'provider', 'provider_destination_id'], 'social_destinations_org_provider_dest_unique');
            });
        }

        if (Schema::hasTable('content_categories') && Schema::hasColumn('content_categories', 'organization_id')) {
            try {
                Schema::table('content_categories', function (Blueprint $table) {
                    $table->dropUnique(['slug']);
                });
            } catch (\Throwable) {
            }

            Schema::table('content_categories', function (Blueprint $table) {
                $table->unique(['organization_id', 'slug'], 'content_categories_org_slug_unique');
            });
        }
    }

    /**
     * @return list<string>
     */
    protected function tenantTables(): array
    {
        return [
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
            'in_app_notifications',
        ];
    }
};
