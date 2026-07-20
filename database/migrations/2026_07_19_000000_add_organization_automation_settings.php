<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'automation_enabled')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('automation_enabled')->default(false)->after('slug');
                $table->timestamp('automation_initialized_at')->nullable()->after('automation_enabled');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'organization_id')) {
            Schema::table('prompt_templates', function (Blueprint $table) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        // Keep the composite index short: MyISAM caps keys at 1000 bytes, and
        // two utf8mb4 varchar(191) columns alone would exceed that.
        if (! Schema::hasIndex('prompt_templates', 'prompt_templates_org_platform_index')) {
            Schema::table('prompt_templates', function (Blueprint $table) {
                $table->index(['organization_id', 'platform'], 'prompt_templates_org_platform_index');
            });
        }

        $firstOrganizationId = DB::table('organizations')->orderBy('id')->value('id');
        if ($firstOrganizationId) {
            DB::table('prompt_templates')
                ->whereNull('organization_id')
                ->update(['organization_id' => $firstOrganizationId]);

            $initializedOrganizationIds = collect([$firstOrganizationId])
                ->merge(DB::table('automation_campaigns')->distinct()->pluck('organization_id'))
                ->merge(DB::table('content_categories')->distinct()->pluck('organization_id'))
                ->filter()
                ->unique()
                ->values();

            $defaultPrompts = DB::table('prompt_templates')
                ->where('organization_id', $firstOrganizationId)
                ->get();

            foreach ($initializedOrganizationIds as $organizationId) {
                if ((int) $organizationId !== (int) $firstOrganizationId) {
                    foreach ($defaultPrompts as $prompt) {
                        DB::table('prompt_templates')->updateOrInsert(
                            [
                                'organization_id' => $organizationId,
                                'platform' => $prompt->platform,
                                'step' => $prompt->step,
                                'name' => $prompt->name,
                            ],
                            [
                                'template' => $prompt->template,
                                'is_default' => $prompt->is_default,
                                'is_active' => $prompt->is_active,
                                'version' => $prompt->version,
                                'created_at' => $prompt->created_at,
                                'updated_at' => $prompt->updated_at,
                            ],
                        );
                    }
                }

                DB::table('organizations')
                    ->where('id', $organizationId)
                    ->update([
                        'automation_enabled' => true,
                        'automation_initialized_at' => now(),
                    ]);
            }
        }

        $this->replaceLegacyAiAgentUser();
    }

    public function down(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropIndex('prompt_templates_org_platform_index');
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['automation_enabled', 'automation_initialized_at']);
        });
    }

    private function replaceLegacyAiAgentUser(): void
    {
        $aiUserId = DB::table('users')
            ->where('email', config('automation.ai_agent_email', 'ai-agent@pixflow.local'))
            ->value('id');

        if (! $aiUserId) {
            return;
        }

        $organizationIds = collect()
            ->merge(DB::table('posts')->where('user_id', $aiUserId)->pluck('organization_id'))
            ->merge(DB::table('media_assets')->where('user_id', $aiUserId)->pluck('organization_id'))
            ->filter()
            ->unique();

        foreach ($organizationIds as $organizationId) {
            $actorId = DB::table('organization_user')
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'admin'])
                ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
                ->value('user_id');

            if (! $actorId) {
                continue;
            }

            DB::table('posts')
                ->where('organization_id', $organizationId)
                ->where('user_id', $aiUserId)
                ->update(['user_id' => $actorId]);

            DB::table('media_assets')
                ->where('organization_id', $organizationId)
                ->where('user_id', $aiUserId)
                ->update(['user_id' => $actorId]);
        }

        $stillReferenced = DB::table('posts')->where('user_id', $aiUserId)->exists()
            || DB::table('media_assets')->where('user_id', $aiUserId)->exists()
            || DB::table('social_accounts')->where('user_id', $aiUserId)->exists()
            || DB::table('automation_campaigns')->where('created_by', $aiUserId)->exists();

        if (! $stillReferenced) {
            DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $aiUserId)
                ->delete();
            DB::table('users')->where('id', $aiUserId)->delete();
        }
    }
};
