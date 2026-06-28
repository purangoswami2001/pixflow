<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class MediaTransformer
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Transform media asset based on preset keys and crop options.
     */
    public function transform(MediaAsset $asset, string $presetKey, string $mode = 'cover'): string
    {
        $presets = config('social-platforms.presets');

        if (!isset($presets[$presetKey])) {
            throw new \InvalidArgumentException("Preset '{$presetKey}' is not configured.");
        }

        $preset = $presets[$presetKey];
        $width = $preset['width'];
        $height = $preset['height'];

        // Retrieve original image file content
        $originalPath = Storage::disk($asset->disk)->path($asset->file_path);
        
        $image = $this->manager->decodePath($originalPath);

        if ($mode === 'cover') {
            // Resize and crop to fill the dimensions
            $image->cover($width, $height);
        } else {
            // Resize to fit within dimensions
            $image->scaleDown($width, $height);
        }

        // Save transformed image
        $filename = 'transformed_' . Str::random(10) . '_' . basename($asset->file_path);
        $directory = 'platforms/' . $presetKey;
        
        // Ensure directory exists in public storage
        Storage::disk('public')->makeDirectory($directory);
        
        $publicPath = Storage::disk('public')->path($directory . '/' . $filename);
        
        $image->save($publicPath);

        return $directory . '/' . $filename;
    }
}
