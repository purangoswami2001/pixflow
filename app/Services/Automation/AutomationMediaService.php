<?php

namespace App\Services\Automation;

use App\Models\User;
use App\Services\Media\ImageMetadataService;
use App\Services\Media\MediaConfigService;
use App\Services\Media\MediaTransformer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AutomationMediaService
{
    public function __construct(
        protected MediaConfigService $mediaConfig,
        protected MediaTransformer $transformer,
        protected ImageMetadataService $metadata
    ) {
    }

    public function createAssetFromGenerated(
        User $owner,
        string $localPath,
        string $platform,
        string $presetKey,
        string $altText = ''
    ): \App\Models\MediaAsset {
        $preset = $this->mediaConfig->preset($presetKey);
        if (! $preset) {
            throw new \RuntimeException("Unknown preset: {$presetKey}");
        }

        $binary = Storage::disk('local')->get($localPath);
        $tempName = Str::uuid() . '.png';
        $staging = 'ai-staging/' . $tempName;
        Storage::disk('public')->put($staging, $binary);

        $manager = new ImageManager(new Driver());
        $image = $manager->decodePath(Storage::disk('public')->path($staging));
        $actual = $this->normalizeToPresetIfNeeded($image, $preset, $platform);

        $designName = $this->metadata->nextAvailableFileName('media/ai/'.now()->format('Y/m'));
        $finalPath = 'media/ai/'.now()->format('Y/m').'/'.$designName;
        Storage::disk('public')->makeDirectory(dirname($finalPath));
        $absolutePath = Storage::disk('public')->path($finalPath);
        $image->save($absolutePath);
        $this->metadata->applyCanvaMetadata($absolutePath);

        Storage::disk('public')->delete($staging);
        Storage::disk('local')->delete($localPath);

        return \App\Models\MediaAsset::create([
            'user_id' => $owner->id,
            'file_path' => $finalPath,
            'disk' => 'public',
            'original_name' => $designName,
            'mime_type' => 'image/png',
            'media_type' => 'image',
            'file_size' => Storage::disk('public')->size($finalPath),
            'width' => $actual['width'],
            'height' => $actual['height'],
            'preset_key' => $presetKey,
            'alt_text' => $altText,
            'tags' => ['canva', $platform],
            'channels' => [$platform],
            'metadata' => [
                'source' => 'automation',
                'creator_tool' => 'Canva',
            ],
        ]);
    }

    /**
     * Keep the AI output intact. Never center-crop (cover) — that clips on-image text.
     * The Cursor agent is instructed to export the exact preset pixels; we only
     * proportionally scale when the aspect ratio already matches.
     *
     * @return array{width: int, height: int}
     */
    protected function normalizeToPresetIfNeeded(
        \Intervention\Image\Interfaces\ImageInterface $image,
        array $preset,
        string $platform
    ): array {
        $targetW = (int) $preset['width'];
        $targetH = (int) $preset['height'];
        $sourceW = $image->width();
        $sourceH = $image->height();

        if ($sourceW === $targetW && $sourceH === $targetH) {
            return ['width' => $sourceW, 'height' => $sourceH];
        }

        $targetRatio = $targetW / max(1, $targetH);
        $sourceRatio = $sourceW / max(1, $sourceH);
        $ratioDelta = abs($targetRatio - $sourceRatio);

        if ($ratioDelta < 0.02) {
            $image->scale($targetW, $targetH);

            Log::info('Automation image scaled to preset (same aspect ratio, no crop)', [
                'platform' => $platform,
                'from' => "{$sourceW}x{$sourceH}",
                'to' => "{$targetW}x{$targetH}",
            ]);

            return ['width' => $targetW, 'height' => $targetH];
        }

        throw new \RuntimeException(sprintf(
            'AI image has wrong aspect ratio for %s: got %dx%d, expected %dx%d (no crop applied). Regenerate the image.',
            $platform,
            $sourceW,
            $sourceH,
            $targetW,
            $targetH
        ));
    }

    public static function aiAgentUser(): User
    {
        $email = config('automation.ai_agent_email', 'ai-agent@pixflow.local');

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'AI Agent',
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ]
        );
    }
}
