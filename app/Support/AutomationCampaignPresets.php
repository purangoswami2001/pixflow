<?php

namespace App\Support;

use App\Models\AutomationCampaign;
use App\Services\Media\MediaConfigService;

class AutomationCampaignPresets
{
    /**
     * Image presets available per platform (from social-platforms config).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function optionsByPlatform(): array
    {
        $media = app(MediaConfigService::class);
        $platforms = ['pinterest', 'facebook', 'instagram'];
        $result = [];

        foreach ($platforms as $platform) {
            $result[$platform] = $media->presetsForChannelAndType($platform, 'image');
        }

        return $result;
    }

    public static function defaultForPlatform(string $platform): string
    {
        return (string) config("automation.default_presets.{$platform}", match ($platform) {
            'facebook' => 'facebook_flexible',
            'instagram' => 'instagram_portrait',
            default => 'pinterest_pin',
        });
    }

    public static function resolvePresetKey(AutomationCampaign $campaign, string $platform): string
    {
        $presets = $campaign->platform_image_presets ?? [];
        if (! is_array($presets)) {
            $presets = [];
        }

        $key = $presets[$platform] ?? null;

        if (is_string($key) && $key !== '' && self::isValidForPlatform($platform, $key)) {
            return $key;
        }

        return self::defaultForPlatform($platform);
    }

    public static function isValidForPlatform(string $platform, string $presetKey): bool
    {
        $allowed = collect(self::optionsByPlatform()[$platform] ?? [])
            ->pluck('key')
            ->all();

        return in_array($presetKey, $allowed, true);
    }

    /**
     * Persist image presets for active campaign platforms only.
     *
     * @return array<string, string>
     */
    public static function normalizeInput(?array $input, array $selectedPlatforms): array
    {
        $input = $input ?? [];
        $normalized = [];

        foreach ($selectedPlatforms as $platform) {
            $key = $input[$platform] ?? self::defaultForPlatform($platform);
            if (is_string($key) && $key !== '' && self::isValidForPlatform($platform, $key)) {
                $normalized[$platform] = $key;
            } else {
                $normalized[$platform] = self::defaultForPlatform($platform);
            }
        }

        return $normalized;
    }

    public static function presetDimensions(string $presetKey): array
    {
        $preset = config("social-platforms.presets.{$presetKey}", []);

        return [
            'width' => (int) ($preset['width'] ?? 1000),
            'height' => (int) ($preset['height'] ?? 1500),
            'name' => $preset['name'] ?? $presetKey,
        ];
    }
}
