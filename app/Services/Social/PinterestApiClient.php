<?php

namespace App\Services\Social;

use App\Models\MediaAsset;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PinterestApiClient
{
    public const PRODUCTION_API_URL = 'https://api.pinterest.com';

    public const SANDBOX_API_URL = 'https://api-sandbox.pinterest.com';

    public static function usesSandbox(): bool
    {
        return filter_var(
            config('social-platforms.platforms.pinterest.sandbox', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function usesSandboxForAccount(?SocialAccount $account): bool
    {
        if ($account) {
            $env = $account->metadata['pinterest_api_env'] ?? null;

            if ($env === 'sandbox') {
                return true;
            }

            if ($env === 'production') {
                return false;
            }

            if (!empty($account->metadata['pinterest_trial_detected'])) {
                return true;
            }
        }

        return self::usesSandbox();
    }

    public static function apiUrl(): string
    {
        return self::apiUrlForAccount(null);
    }

    public static function apiUrlForAccount(?SocialAccount $account): string
    {
        if (self::usesSandboxForAccount($account)) {
            return self::SANDBOX_API_URL;
        }

        $configured = config('social-platforms.platforms.pinterest.api_url', self::PRODUCTION_API_URL);

        return rtrim($configured, '/');
    }

    public static function environmentLabelForAccount(?SocialAccount $account): string
    {
        return self::usesSandboxForAccount($account) ? 'Sandbox (Trial)' : 'Production';
    }

    public static function version(): string
    {
        return config('social-platforms.platforms.pinterest.api_version', 'v5');
    }

    public static function oauthScopes(): array
    {
        return config('social-platforms.platforms.pinterest.scopes', []);
    }

    public static function tokenUrl(): string
    {
        return self::tokenUrlForAccount(null);
    }

    public static function tokenUrlForAccount(?SocialAccount $account): string
    {
        return self::apiUrlForAccount($account) . '/' . self::version() . '/oauth/token';
    }

    public static function markAccountTrial(SocialAccount $account): void
    {
        $account->update([
            'metadata' => array_merge($account->metadata ?? [], [
                'pinterest_trial_detected' => true,
                'pinterest_api_env' => 'sandbox',
            ]),
        ]);
    }

    public static function api(string $accessToken): PendingRequest
    {
        return Http::acceptJson()->withToken($accessToken);
    }

  /**
   * Parse scope string returned by Pinterest token endpoint.
   */
    public static function parseGrantedScopes(?string $scope): array
    {
        if (!$scope) {
            return self::oauthScopes();
        }

        return array_values(array_filter(preg_split('/[\s,]+/', $scope) ?: []));
    }

    public static function errorMessage($response, string $fallback): string
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json['message']
                ?? $json['error_description']
                ?? $json['error']
                ?? $fallback;
        }

        return $fallback;
    }

    /**
     * List all boards for the authenticated user (paginated, all privacy types).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listBoards(string $accessToken, ?SocialAccount $account = null): array
    {
        $boards = [];
        $seen = [];
        $apiUrl = self::apiUrlForAccount($account);

        foreach ([null, 'PUBLIC', 'PROTECTED', 'SECRET'] as $privacy) {
            $bookmark = null;

            do {
                $query = array_filter([
                    'page_size' => 250,
                    'bookmark' => $bookmark,
                    'privacy' => $privacy,
                ]);

                $response = self::api($accessToken)
                    ->get($apiUrl . '/' . self::version() . '/boards', $query);

                if ($response->failed()) {
                    \Illuminate\Support\Facades\Log::warning('Pinterest boards list request failed', [
                        'privacy' => $privacy,
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                        'api' => $apiUrl,
                    ]);
                    break;
                }

                $payload = $response->json();
                foreach ($payload['items'] ?? [] as $board) {
                    $id = $board['id'] ?? null;
                    if ($id && !isset($seen[$id])) {
                        $seen[$id] = true;
                        $boards[] = $board;
                    }
                }

                $bookmark = $payload['bookmark'] ?? null;
            } while ($bookmark);
        }

        return $boards;
    }

    /**
     * Build Pinterest pin media_source — base64 embed or public URL.
     *
     * @return array<string, mixed>
     */
    public static function buildImageMediaSource(MediaAsset $asset, bool $embedInPayload = false, ?string $imageUrl = null): array
    {
        if ($embedInPayload) {
            return self::imageBase64MediaSource($asset);
        }

        return [
            'source_type' => 'image_url',
            'url' => $imageUrl ?? $asset->url,
        ];
    }

    public static function buildImageMediaSourceForTarget(PostTarget $target, ?string $imageUrl = null): array
    {
        $path = $target->platform_media_path;
        $mime = $target->resolvedMimeType();
        $url = $imageUrl ?? $target->resolvedMediaUrl();

        $account = $target->socialAccount;
        $embed = self::usesSandboxForAccount($account) || !self::isPubliclyAccessibleUrl($url);

        if ($embed && $path && Storage::disk('public')->exists($path)) {
            return self::imageBase64FromPath($path, $mime);
        }

        if ($url && self::isPubliclyAccessibleUrl($url)) {
            return [
                'source_type' => 'image_url',
                'url' => $url,
            ];
        }

        throw new \RuntimeException('No accessible image is available for this Pinterest pin.');
    }

    public static function isPubliclyAccessibleUrl(?string $url): bool
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        foreach (['ngrok', '.local', '.test', '.invalid'] as $needle) {
            if (str_contains($host, $needle)) {
                return false;
            }
        }

        return str_starts_with($url, 'https://');
    }

    /**
     * @return array{source_type: string, content_type: string, data: string}
     */
    public static function imageBase64FromPath(string $path, ?string $mimeType = null): array
    {
        $binary = Storage::disk('public')->get($path);

        return [
            'source_type' => 'image_base64',
            'content_type' => self::pinImageContentType($mimeType),
            'data' => base64_encode($binary),
        ];
    }

    /**
     * @return array{source_type: string, content_type: string, data: string}
     */
    public static function imageBase64MediaSource(MediaAsset $asset): array
    {
        $disk = Storage::disk($asset->disk);

        if (!$disk->exists($asset->file_path)) {
            throw new \RuntimeException('Image file not found for Pinterest upload.');
        }

        $binary = $disk->get($asset->file_path);

        return [
            'source_type' => 'image_base64',
            'content_type' => self::pinImageContentType($asset->mime_type),
            'data' => base64_encode($binary),
        ];
    }

    public static function pinImageContentType(?string $mimeType): string
    {
        return $mimeType === 'image/png' ? 'image/png' : 'image/jpeg';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function redactPayloadForLog(array $payload): array
    {
        if (!isset($payload['media_source']['data']) || !is_string($payload['media_source']['data'])) {
            return $payload;
        }

        $payload['media_source']['data'] = '[base64 ' . strlen($payload['media_source']['data']) . ' chars]';

        return $payload;
    }
}
