<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_categories')) {
            Schema::create('content_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('niche_prompt')->nullable();
                $table->text('safety_rules')->nullable();
                $table->string('audience')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('content_topics')) {
            Schema::create('content_topics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('content_category_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->string('topic_type')->default('educational');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('times_used')->default(0);
                $table->timestamp('last_used_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prompt_templates')) {
            Schema::create('prompt_templates', function (Blueprint $table) {
                $table->id();
                $table->string('platform');
                $table->string('step');
                $table->string('name');
                $table->longText('template');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('automation_campaigns')) {
            Schema::create('automation_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('content_category_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->unsignedTinyInteger('posts_per_day')->default(1);
                $table->json('platforms');
                $table->json('platform_accounts');
                $table->json('platform_counts')->nullable();
                $table->unsignedTinyInteger('promotional_ratio')->default(15);
                $table->string('default_timezone')->default('UTC');
                $table->time('generation_time')->default('06:00:00');
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('automation_runs')) {
            Schema::create('automation_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_campaign_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('running');
                $table->unsignedSmallInteger('planned_count')->default(0);
                $table->unsignedSmallInteger('created_count')->default(0);
                $table->unsignedSmallInteger('failed_count')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->json('log')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('automation_run_items')) {
            Schema::create('automation_run_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('content_topic_id')->nullable()->constrained()->nullOnDelete();
                $table->string('platform');
                $table->string('topic_type')->default('educational');
                $table->string('status')->default('pending');
                $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
                $table->text('design_prompt')->nullable();
                $table->string('image_provider')->nullable();
                $table->string('text_provider')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_run_items');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_campaigns');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('content_topics');
        Schema::dropIfExists('content_categories');
    }
};
