<?php

namespace App\Services\Posts;

use App\Models\MediaAsset;
use App\Models\PostTarget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostMediaArchiveService
{
    /**
     * Copy library media onto the post target and detach from the media library.
     */
    public function copyFromLibrary(PostTarget $target, MediaAsset $asset): void
    {
        if (
            $target->platform_media_path
            && $this->isArchivedPath($target->platform_media_path, $target->post_id)
            && Storage::disk('public')->exists($target->platform_media_path)
        ) {
            if (!$target->media_snapshot) {
                $target->update([
                    'media_snapshot' => $this->buildSnapshotFromAsset($asset),
                    'media_asset_id' => null,
                ]);
            }

            return;
        }

        $newPath = $this->copyFileFromAsset($asset, $target->post_id);

        if (!$newPath) {
            return;
        }

        $oldPath = $target->platform_media_path;

        $target->update([
            'platform_media_path' => $newPath,
            'media_snapshot' => $this->buildSnapshotFromAsset($asset),
            'media_asset_id' => null,
        ]);

        if ($oldPath && $oldPath !== $newPath) {
            $this->removeArchivedFile($oldPath, $target->post_id);
        }
    }

    /**
     * Replace the post's archived media with a newly selected library file.
     */
    public function replaceFromLibrary(PostTarget $target, MediaAsset $asset): void
    {
        $oldPath = $target->platform_media_path;
        $newPath = $this->copyFileFromAsset($asset, $target->post_id);

        if (!$newPath) {
            return;
        }

        $target->update([
            'platform_media_path' => $newPath,
            'media_snapshot' => $this->buildSnapshotFromAsset($asset),
            'media_asset_id' => null,
        ]);

        if ($oldPath && $oldPath !== $newPath) {
            $this->removeArchivedFile($oldPath, $target->post_id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshotFromAsset(MediaAsset $asset): array
    {
        return [
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'media_type' => $asset->media_type,
            'width' => $asset->width,
            'height' => $asset->height,
            'preset_key' => $asset->preset_key,
            'file_size' => $asset->file_size,
        ];
    }

    public function copyFileFromAsset(MediaAsset $asset, int $postId): ?string
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($asset->file_path)) {
            Log::warning('Post media copy skipped: source file missing', [
                'asset_id' => $asset->id,
                'path' => $asset->file_path,
                'post_id' => $postId,
            ]);

            return null;
        }

        $extension = pathinfo($asset->file_path, PATHINFO_EXTENSION)
            ?: pathinfo($asset->original_name, PATHINFO_EXTENSION);
        $baseName = Str::slug(pathinfo($asset->original_name, PATHINFO_FILENAME)) ?: 'media';
        $filename = Str::uuid() . '_' . $baseName . ($extension ? '.' . $extension : '');
        $destination = "post-media/{$postId}/{$filename}";

        $disk->copy($asset->file_path, $destination);

        return $destination;
    }

    public function isArchivedPath(string $path, int $postId): bool
    {
        return str_starts_with(ltrim($path, '/'), "post-media/{$postId}/");
    }

    public function removeArchivedFile(?string $path, int $postId): void
    {
        if (!$path || !$this->isArchivedPath($path, $postId)) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
