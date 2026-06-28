<?php

use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialDestination;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            $table->json('connection_snapshot')->nullable()->after('provider');
        });

        PostTarget::query()->orderBy('id')->chunkById(100, function ($targets) {
            foreach ($targets as $target) {
                $account = $target->social_account_id
                    ? SocialAccount::find($target->social_account_id)
                    : null;
                $destination = $target->social_destination_id
                    ? SocialDestination::find($target->social_destination_id)
                    : null;

                if (!$account && !$destination) {
                    continue;
                }

                $target->update([
                    'connection_snapshot' => $target->buildConnectionSnapshot($account, $destination),
                ]);
            }
        });

        Schema::table('post_targets', function (Blueprint $table) {
            $table->dropForeign(['social_account_id']);
            $table->dropForeign(['social_destination_id']);
        });

        DB::statement('ALTER TABLE post_targets MODIFY social_account_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE post_targets MODIFY social_destination_id BIGINT UNSIGNED NULL');

        Schema::table('post_targets', function (Blueprint $table) {
            $table->foreign('social_account_id')
                ->references('id')
                ->on('social_accounts')
                ->nullOnDelete();
            $table->foreign('social_destination_id')
                ->references('id')
                ->on('social_destinations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            $table->dropForeign(['social_account_id']);
            $table->dropForeign(['social_destination_id']);
        });

        DB::statement('ALTER TABLE post_targets MODIFY social_account_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE post_targets MODIFY social_destination_id BIGINT UNSIGNED NOT NULL');

        Schema::table('post_targets', function (Blueprint $table) {
            $table->foreign('social_account_id')
                ->references('id')
                ->on('social_accounts')
                ->cascadeOnDelete();
            $table->foreign('social_destination_id')
                ->references('id')
                ->on('social_destinations')
                ->cascadeOnDelete();
            $table->dropColumn('connection_snapshot');
        });
    }
};
