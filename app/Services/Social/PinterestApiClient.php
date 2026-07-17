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

    /**
     * Register + upload a video, then return media_source for pins/create.
     *
     * @return array<string, mixed>
     */
    public static function buildVideoMediaSourceForTarget(PostTarget $target, string $accessToken): array
    {
        $path = $target->platform_media_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            throw new \RuntimeException('No local video file is available for this Pinterest pin.');
        }

        $absolutePath = Storage::disk('public')->path($path);
        $apiUrl = self::apiUrlForAccount($target->socialAccount);
        $version = self::version();

        $register = self::api($accessToken)->post("{$apiUrl}/{$version}/media", [
            'media_type' => 'video',
        ]);

        if ($register->failed()) {
            throw new \RuntimeException(
                'Pinterest video register failed: '.self::errorMessage($register, $register->body())
            );
        }

        $registerBody = $register->json() ?? [];
        $mediaId = $registerBody['media_id'] ?? null;
        $uploadUrl = $registerBody['upload_url'] ?? null;
        $uploadParameters = $registerBody['upload_parameters'] ?? [];

        if (! $mediaId || ! $uploadUrl || ! is_array($uploadParameters)) {
            throw new \RuntimeException('Pinterest did not return video upload credentials.');
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new \RuntimeException('Could not read the local video file for Pinterest upload.');
        }

        $multipart = [];
        foreach ($uploadParameters as $key => $value) {
            $multipart[] = ['name' => (string) $key, 'contents' => (string) $value];
        }
        $multipart[] = [
            'name' => 'file',
            'contents' => $contents,
            'filename' => basename($absolutePath),
            'headers' => ['Content-Type' => $target->resolvedMimeType() ?: 'video/mp4'],
        ];

        $upload = Http::timeout(300)->asMultipart()->post($uploadUrl, $multipart);
        if ($upload->failed() && $upload->status() >= 400) {
            throw new \RuntimeException(
                'Pinterest video file upload failed (HTTP '.$upload->status().').'
            );
        }

        // Wait briefly for processing when status endpoint is available.
        self::waitForVideoReady($accessToken, $apiUrl, $version, (string) $mediaId);

        $cover = self::buildVideoCoverForTarget($target);

        return array_filter([
            'source_type' => 'video_id',
            'media_id' => (string) $mediaId,
            'cover_image_url' => $cover['url'] ?? null,
            'cover_image_content_type' => $cover['content_type'] ?? null,
            'cover_image_data' => $cover['data'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected static function waitForVideoReady(
        string $accessToken,
        string $apiUrl,
        string $version,
        string $mediaId
    ): void {
        for ($i = 0; $i < 12; $i++) {
            sleep(3);
            try {
                $status = self::api($accessToken)->get("{$apiUrl}/{$version}/media/{$mediaId}");
                if ($status->failed()) {
                    continue;
                }
                $code = strtoupper((string) ($status->json('status') ?? ''));
                if (in_array($code, ['SUCCEEDED', 'SUCCESS', 'READY', 'FINISHED'], true)) {
                    return;
                }
                if (in_array($code, ['FAILED', 'ERROR'], true)) {
                    throw new \RuntimeException('Pinterest video processing failed.');
                }
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Keep waiting — some environments omit status until pin create.
            }
        }
    }

    /**
     * @return array{url?: string, content_type?: string, data?: string}
     */
    public static function buildVideoCoverForTarget(PostTarget $target): array
    {
        $path = $target->platform_media_path;
        $absolute = $path && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->path($path)
            : null;

        $framePath = $absolute ? self::extractVideoFrame($absolute) : null;
        if ($framePath && is_readable($framePath)) {
            $binary = file_get_contents($framePath);
            @unlink($framePath);
            if ($binary !== false && $binary !== '') {
                return [
                    'content_type' => 'image/jpeg',
                    'data' => base64_encode($binary),
                ];
            }
        }

        $placeholder = self::generatePlaceholderCoverJpeg(
            (string) ($target->post?->title ?? 'Video pin')
        );

        return [
            'content_type' => 'image/jpeg',
            'data' => base64_encode($placeholder),
        ];
    }

    protected static function extractVideoFrame(string $absoluteVideoPath): ?string
    {
        $ffmpeg = trim((string) shell_exec('where ffmpeg 2>nul'));
        if ($ffmpeg === '') {
            $ffmpeg = trim((string) shell_exec('which ffmpeg 2>/dev/null'));
        }
        if ($ffmpeg === '') {
            return null;
        }

        $ffmpegBin = strtok($ffmpeg, "\r\n") ?: 'ffmpeg';
        $out = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pixflow_cover_'.uniqid('', true).'.jpg';
        $cmd = escapeshellarg($ffmpegBin)
            .' -y -ss 00:00:01 -i '.escapeshellarg($absoluteVideoPath)
            .' -frames:v 1 -q:v 3 '.escapeshellarg($out).' 2>nul';

        @exec($cmd, $output, $code);
        if ($code === 0 && is_file($out) && filesize($out) > 0) {
            return $out;
        }

        @unlink($out);

        return null;
    }

    protected static function generatePlaceholderCoverJpeg(string $title): string
    {
        $width = 1000;
        $height = 1500;
        $image = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($image, 15, 23, 42);
        $accent = imagecolorallocate($image, 0, 74, 198);
        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 148, 163, 184);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
        imagefilledrectangle($image, 0, 0, $width, 12, $accent);

        $label = 'VIDEO';
        imagestring($image, 5, (int) (($width - imagefontwidth(5) * strlen($label)) / 2), (int) ($height / 2 - 40), $label, $accent);

        $safeTitle = mb_substr(preg_replace('/[^\x20-\x7E]/', '', $title) ?: 'Video pin', 0, 40);
        imagestring($image, 3, (int) (($width - imagefontwidth(3) * strlen($safeTitle)) / 2), (int) ($height / 2 + 10), $safeTitle, $white);
        imagestring($image, 2, (int) (($width - imagefontwidth(2) * 18) / 2), (int) ($height / 2 + 40), 'Posted with PixFlow', $muted);

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return (string) ob_get_clean();
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
        if (isset($payload['media_source']['data']) && is_string($payload['media_source']['data'])) {
            $payload['media_source']['data'] = '[base64 '.strlen($payload['media_source']['data']).' chars]';
        }

        if (isset($payload['media_source']['cover_image_data']) && is_string($payload['media_source']['cover_image_data'])) {
            $payload['media_source']['cover_image_data'] = '[base64 '.strlen($payload['media_source']['cover_image_data']).' chars]';
        }

        return $payload;
    }
}
