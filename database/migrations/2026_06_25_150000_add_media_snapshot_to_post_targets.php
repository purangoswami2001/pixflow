<?php

use App\Models\MediaAsset;
use App\Models\PostTarget;
use App\Services\Posts\PostMediaArchiveService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            $table->json('media_snapshot')->nullable()->after('platform_media_path');
        });

        $service = app(PostMediaArchiveService::class);

        PostTarget::query()
            ->orderBy('id')
            ->chunkById(50, function ($targets) use ($service) {
                foreach ($targets as $target) {
                    if (
                        $target->platform_media_path
                        && $service->isArchivedPath($target->platform_media_path, $target->post_id)
                        && !$target->media_snapshot
                        && $target->media_asset_id
                    ) {
                        $asset = MediaAsset::find($target->media_asset_id);
                        if ($asset) {
                            $target->update([
                                'media_snapshot' => $service->buildSnapshotFromAsset($asset),
                                'media_asset_id' => null,
                            ]);
                            continue;
                        }
                    }

                    if ($target->media_asset_id) {
                        $asset = MediaAsset::find($target->media_asset_id);
                        if ($asset) {
                            $service->copyFromLibrary($target, $asset);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            $table->dropColumn('media_snapshot');
        });
    }
};
