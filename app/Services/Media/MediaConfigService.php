<?php

namespace App\Services\Media;

class MediaConfigService
{
    public function channels(): array
    {
        $platforms = config('social-platforms.platforms', []);
        $channelMedia = config('social-platforms.channel_media', []);

        $result = [];
        foreach ($channelMedia as $key => $config) {
            $result[$key] = [
                'key' => $key,
                'name' => $platforms[$key]['name'] ?? ucfirst($key),
                'icon' => $config['icon'] ?? 'share',
                'color' => $config['color'] ?? 'primary',
            ];
        }

        return $result;
    }

    public function mediaTypesForChannel(string $channel): array
    {
        $types = config("social-platforms.channel_media.{$channel}.types", []);

        return collect($types)->map(function ($config, $type) {
            $presets = $this->presetsForType($config);

            return [
                'key' => $type,
                'label' => $type === 'image' ? 'Image' : 'Video',
                'icon' => $type === 'image' ? 'image' : 'videocam',
                'has_sizes' => count($presets) > 0,
                'presets' => $presets,
            ];
        })->values()->all();
    }

    public function presetsForChannelAndType(string $channel, string $mediaType): array
    {
        $config = config("social-platforms.channel_media.{$channel}.types.{$mediaType}", []);

        return $this->presetsForType($config);
    }

    public function preset(string $presetKey): ?array
    {
        $preset = config("social-platforms.presets.{$presetKey}");

        if (!$preset) {
            return null;
        }

        return array_merge(['key' => $presetKey], $preset);
    }

    public function defaultPostType(string $channel, string $mediaType, ?string $presetKey = null): string
    {
        if ($channel === 'instagram' && $mediaType === 'video') {
            return $presetKey === 'instagram_story' ? 'instagram_reel' : 'instagram_reel';
        }

        if ($channel === 'instagram' && $presetKey === 'instagram_story') {
            return 'instagram_story';
        }

        return match ($channel) {
            'facebook' => 'facebook_post',
            'instagram' => 'instagram_post',
            'pinterest' => 'pinterest_pin',
            default => "{$channel}_post",
        };
    }

    protected function presetsForType(array $typeConfig): array
    {
        $presetKeys = $typeConfig['presets'] ?? [];
        $allPresets = config('social-platforms.presets', []);

        return collect($presetKeys)
            ->filter(fn ($key) => isset($allPresets[$key]))
            ->map(fn ($key) => array_merge(['key' => $key], $allPresets[$key]))
            ->values()
            ->all();
    }
}
