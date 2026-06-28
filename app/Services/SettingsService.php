<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    /**
     * Get a setting value, with fallback to config / env.
     */
    public static function get(string $key, $default = null)
    {
        try {
            $setting = Setting::find($key);
            if ($setting !== null && $setting->value !== null && $setting->value !== '') {
                $val = $setting->value;
                if ($val === 'true' || $val === '1') {
                    return true;
                }
                if ($val === 'false' || $val === '0') {
                    return false;
                }

                return $val;
            }
        } catch (\Exception $e) {
            // Fallback during migrations/tests where table might not exist yet
        }

        return $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function asBool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default);

        if (is_bool($val)) {
            return $val;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function usesMockMode(): bool
    {
        return filter_var(config('social-platforms.mock_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function platformUsesSandbox(string $provider): bool
    {
        return filter_var(
            config("social-platforms.platforms.{$provider}.sandbox", false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Simulate publish without calling live APIs (global mock or per-platform sandbox for Meta).
     */
    public static function shouldMockPublish(string $provider): bool
    {
        if (self::usesMockMode()) {
            return true;
        }

        if ($provider === 'pinterest') {
            return false;
        }

        return self::platformUsesSandbox($provider);
    }

    /**
     * OAuth credentials and sandbox flags from .env only (via config/social-platforms.php).
     */
    public static function platformOAuth(string $provider): array
    {
        return self::platformFromEnv($provider);
    }

    /**
     * @return array<string, mixed>
     */
    public static function platformFromEnv(string $provider): array
    {
        $config = config("social-platforms.platforms.{$provider}", []);
        $redirectUri = self::redirectUriFor($provider);

        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;

        if ($provider === 'instagram' && (empty($clientId) || empty($clientSecret))) {
            $facebook = config('social-platforms.platforms.facebook', []);
            $clientId = $clientId ?: ($facebook['client_id'] ?? null);
            $clientSecret = $clientSecret ?: ($facebook['client_secret'] ?? null);
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'sandbox' => self::platformUsesSandbox($provider),
            'scopes' => self::oauthScopesFor($provider),
            'api_url' => $config['api_url'] ?? null,
            'api_version' => $config['api_version'] ?? null,
        ];
    }

    /**
     * OAuth scopes sent to the provider (base .env scopes + optional publish scopes).
     *
     * @return array<int, string>
     */
    public static function oauthScopesFor(string $provider): array
    {
        $config = config("social-platforms.platforms.{$provider}", []);
        $scopes = array_values(array_filter($config['scopes'] ?? []));

        $flag = match ($provider) {
            'facebook' => 'FACEBOOK_REQUEST_PUBLISH_SCOPES',
            'instagram' => 'INSTAGRAM_REQUEST_PUBLISH_SCOPES',
            default => null,
        };

        if ($flag && filter_var(env($flag, false), FILTER_VALIDATE_BOOLEAN)) {
            $scopes = array_values(array_unique(array_merge($scopes, $config['publish_scopes'] ?? [])));
        }

        return $scopes;
    }

    /**
     * OAuth redirect URI for a provider (from .env / config).
     */
    public static function redirectUriFor(string $provider): string
    {
        $metaRedirect = env('META_REDIRECT_URI');
        if ($metaRedirect && in_array($provider, ['facebook', 'instagram'], true)) {
            return $metaRedirect;
        }

        $config = config("social-platforms.platforms.{$provider}", []);
        $redirectUri = $config['redirect_uri'] ?? null;

        if ($provider === 'instagram') {
            $facebook = config('social-platforms.platforms.facebook', []);
            $fbRedirect = $facebook['redirect_uri'] ?? null;
            $fbClientId = $facebook['client_id'] ?? null;
            $igClientId = $config['client_id'] ?: $fbClientId;

            // Same Meta app ID → reuse Facebook redirect (whitelist once in Meta dashboard)
            if ($fbRedirect && ($igClientId === $fbClientId || empty($redirectUri))) {
                return $fbRedirect;
            }
        }

        if ($redirectUri) {
            return $redirectUri;
        }

        return route('oauth.callback', $provider);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function allPlatformsFromEnv(): array
    {
        $platforms = [];

        foreach (array_keys(config('social-platforms.platforms', [])) as $provider) {
            $platforms[$provider] = self::platformFromEnv($provider);
        }

        return $platforms;
    }
}
