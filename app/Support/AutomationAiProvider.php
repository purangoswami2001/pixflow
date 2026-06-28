<?php

namespace App\Support;

use App\Exceptions\AiGenerationException;
use App\Services\Ai\CursorApiClient;

class AutomationAiProvider
{
    public static function name(): string
    {
        $provider = strtolower(trim((string) config('automation.ai_provider', 'gemini')));

        return in_array($provider, ['openai', 'gemini', 'cursor'], true) ? $provider : 'gemini';
    }

    public static function imageProviderName(): string
    {
        $override = strtolower(trim((string) config('automation.image_provider', '')));
        $strictCursor = filter_var(env('AUTOMATION_IMAGE_STRICT_CURSOR', false), FILTER_VALIDATE_BOOL);

        if ($override !== '' && in_array($override, ['openai', 'gemini', 'cursor'], true)) {
            if ($override === 'cursor' && ! $strictCursor) {
                if (filled(config('ai.gemini.api_key'))) {
                    return 'gemini';
                }
                if (filled(config('ai.openai.api_key'))) {
                    return 'openai';
                }
            }

            return $override;
        }

        if (self::name() === 'cursor' && filled(config('ai.gemini.api_key'))) {
            return 'gemini';
        }

        return self::name();
    }

    /**
     * @return list<string>
     */
    public static function textProviders(): array
    {
        return [self::name()];
    }

    /**
     * Same provider chain for every platform — style and size come from campaign config + prompts.
     *
     * @return list<string>
     */
    public static function imageProvidersForPlatform(string $platform): array
    {
        $primary = self::imageProviderName();
        $providers = [$primary];

        if ($primary === 'cursor') {
            if (filled(config('ai.gemini.api_key'))) {
                $providers[] = 'gemini';
            }
            if (filled(config('ai.openai.api_key'))) {
                $providers[] = 'openai';
            }
        }

        return array_values(array_unique($providers));
    }

    public static function imageProviderNameForPlatform(string $platform): string
    {
        return self::imageProviderName();
    }

    public static function usesCursorImageArtifacts(?string $platform = null): bool
    {
        return self::usesCursorAgentImage($platform);
    }

    public static function usesCursorAgentImage(?string $platform = null): bool
    {
        if (self::imageProviderNameForPlatform($platform ?? '') !== 'cursor') {
            return false;
        }

        $client = app(CursorApiClient::class);

        if (! $client->usesCloudApi()) {
            return false;
        }

        $mode = $client->imageMode();

        if (in_array($mode, ['agent', 'layout'], true)) {
            return true;
        }

        return $mode === 'api' && (bool) config('ai.cursor.agent_fallback', true);
    }

    public static function usesCursorLayoutImages(?string $platform = null): bool
    {
        if (self::imageProviderNameForPlatform($platform ?? '') !== 'cursor') {
            return false;
        }

        return app(CursorApiClient::class)->usesCloudApi()
            && app(CursorApiClient::class)->imageMode() === 'layout';
    }

    /**
     * @return list<string>
     */
    public static function imageProviders(): array
    {
        return self::imageProvidersForPlatform('');
    }

    public static function assertConfigured(): void
    {
        $textProvider = self::name();

        if ($textProvider === 'cursor') {
            app(CursorApiClient::class)->assertConfigured(requireImage: false);
        } else {
            self::assertProviderKey($textProvider, 'text');
        }

        $lastError = 'No image AI provider is configured for automation.';
        foreach (self::imageProviders() as $provider) {
            try {
                if ($provider === 'cursor') {
                    app(CursorApiClient::class)->assertConfigured(requireImage: true);
                } else {
                    self::assertProviderKey($provider, 'image');
                }

                return;
            } catch (AiGenerationException $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new AiGenerationException($lastError);
    }

    protected static function assertProviderKey(string $provider, string $purpose): void
    {
        $configured = match ($provider) {
            'openai' => filled(config('ai.openai.api_key')),
            'gemini' => filled(config('ai.gemini.api_key')),
            'cursor' => filled(config('ai.cursor.api_key')),
            default => false,
        };

        if (! $configured) {
            throw new AiGenerationException(
                "Automation {$purpose} provider is {$provider} but the API key is not set in .env."
            );
        }
    }
}
