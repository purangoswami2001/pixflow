<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50); // facebook, instagram, pinterest
            $table->string('provider_user_id', 125);
            $table->string('name');
            $table->string('username')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token'); // will be encrypted
            $table->text('refresh_token')->nullable(); // will be encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('active'); // active, expired, error
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider']);
            $table->unique(['provider', 'provider_user_id']);
        });

        Schema::create('social_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('destination_type', 50); // facebook_page, instagram_account, pinterest_board
            $table->string('provider_destination_id', 125);
            $table->string('name');
            $table->text('avatar_url')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['social_account_id', 'status']);
            $table->unique(['provider', 'provider_destination_id']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable(); // in seconds for videos
            $table->string('alt_text')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('draft'); // draft, scheduled, publishing, published, failed, cancelled
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('post_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_destination_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('post_type', 50); // facebook_post, instagram_post/story/reel, pinterest_pin
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform_media_path')->nullable();
            $table->json('platform_payload')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('status')->default('draft'); // draft, scheduled, publishing, published, failed, cancelled
            $table->string('provider_post_id')->nullable();
            $table->text('provider_post_url')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['scheduled_at', 'status', 'provider']);
            $table->index('post_id');
        });

        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('frequency')->default('weekly'); // weekly
            $table->json('days_of_week'); // e.g. [1, 3, 5] (Monday, Wednesday, Friday)
            $table->string('time'); // e.g. "14:30"
            $table->string('timezone')->default('UTC');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('status')->default('active'); // active, paused, finished
            $table->timestamps();

            $table->index(['next_run_at', 'status']);
        });

        Schema::create('publish_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('status'); // success, failed
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('post_target_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_logs');
        Schema::dropIfExists('recurring_schedules');
        Schema::dropIfExists('post_targets');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('social_destinations');
        Schema::dropIfExists('social_accounts');
    }
};
