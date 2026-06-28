<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Services\Media\ImageMetadataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RewriteAiImageMetadata extends Command
{
    protected $signature = 'media:rewrite-canva-metadata
                            {--dry-run : List matching images without changing files}
                            {--rename : Rename files to Canva-style Untitled design-*.png names}';

    protected $description = 'Rewrite file metadata on existing AI-generated images to Canva-style metadata';

    public function handle(ImageMetadataService $metadata): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rename = (bool) $this->option('rename');

        $assets = MediaAsset::query()
            ->where('media_type', 'image')
            ->where(function ($query) {
                $query->where('file_path', 'like', 'media/ai/%')
                    ->orWhereJsonContains('tags', 'ai-generated')
                    ->orWhere('metadata->source', 'automation');
            })
            ->orderBy('id')
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No AI-generated images found in the media library.');

            return self::SUCCESS;
        }

        $this->info("Found {$assets->count()} image(s) to process.");

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($assets as $asset) {
            $disk = Storage::disk($asset->disk);
            if (! $disk->exists($asset->file_path)) {
                $this->warn("Skip #{$asset->id}: file missing ({$asset->file_path})");
                $skipped++;

                continue;
            }

            $absolutePath = $disk->path($asset->file_path);
            $label = "#{$asset->id} {$asset->file_path}";

            if ($dryRun) {
                $this->line("Would update {$label}");
                $updated++;

                continue;
            }

            try {
                $metadata->applyCanvaMetadata($absolutePath);

                $newPath = $asset->file_path;
                $newName = $asset->original_name;

                if ($rename) {
                    $newName = $metadata->nextAvailableFileName(dirname($asset->file_path));
                    $newPath = dirname($asset->file_path).'/'.$newName;

                    if ($newPath !== $asset->file_path) {
                        $disk->makeDirectory(dirname($newPath));
                        $disk->move($asset->file_path, $newPath);
                        $absolutePath = $disk->path($newPath);
                    }
                }

                $tags = array_values(array_unique(array_merge(
                    array_diff($asset->tags ?? [], ['ai-generated']),
                    ['canva']
                )));

                $assetMetadata = array_merge($asset->metadata ?? [], [
                    'creator_tool' => 'Canva',
                ]);
                unset($assetMetadata['image_provider'], $assetMetadata['text_provider']);

                $asset->update([
                    'file_path' => $newPath,
                    'original_name' => $newName,
                    'file_size' => $disk->size($newPath),
                    'tags' => $tags,
                    'metadata' => $assetMetadata,
                ]);

                $this->line("Updated {$label}");
                $updated++;
            } catch (\Throwable $e) {
                $this->error("Failed {$label}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Updated: {$updated}, skipped: {$skipped}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
