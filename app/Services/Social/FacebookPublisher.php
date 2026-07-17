<?php

namespace App\Services\Social;

use App\Models\PostTarget;
use App\Services\Posts\PlatformPostFieldsService;
use App\Services\SettingsService;
use App\Services\Social\Contracts\SocialPublisherInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher implements SocialPublisherInterface
{
    public function __construct(
        protected PlatformPostFieldsService $platformFields = new PlatformPostFieldsService()
    ) {
    }

    public function publish(PostTarget $target): array
    {
        $account = $target->socialAccount;
        $destination = $target->socialDestination;

        if (! $account || $account->isDisconnected()) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Facebook account is disconnected. Reconnect it in Accounts, then retry this post.',
            ];
        }

        if (! $destination) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Facebook page is no longer linked. Reconnect Facebook in Accounts, then retry.',
            ];
        }

        if (SettingsService::shouldMockPublish('facebook')) {
            Log::info("Facebook mock publishing for Target ID: {$target->id}");

            return [
                'status' => 'success',
                'provider_post_id' => 'fb_mock_post_'.uniqid(),
                'provider_post_url' => 'https://facebook.com/mock-page/posts/'.uniqid(),
                'metadata' => ['mock' => true, 'payload' => $target->platform_payload],
                'error' => null,
            ];
        }

        try {
            $accessToken = $account->access_token;
            $apiVersion = config('social-platforms.platforms.facebook.api_version', 'v20.0');
            $apiUrl = config('social-platforms.platforms.facebook.api_url', 'https://graph.facebook.com');
            $pageAccessToken = $destination->metadata['access_token'] ?? $accessToken;
            $pageId = $destination->provider_destination_id;

            $endpoint = "{$apiUrl}/{$apiVersion}/{$pageId}";
            $hasMedia = $target->hasDisplayableMedia();
            $isVideo = $hasMedia && $target->isVideoMedia();
            $localPath = $hasMedia ? $target->resolvedMediaAbsolutePath() : null;

            if ($hasMedia && $isVideo) {
                $endpoint .= '/videos';
                $payload = $this->platformFields->buildFacebookVideoApiPayload($target);

                if ($localPath && is_readable($localPath)) {
                    return $this->publishVideoMultipart(
                        $endpoint,
                        $pageAccessToken,
                        $payload,
                        $localPath,
                        $target
                    );
                }

                $payload['file_url'] = $target->resolvedMediaUrl();
            } elseif ($hasMedia) {
                $endpoint .= '/photos';
                $payload = $this->platformFields->buildFacebookApiPayload($target, true);

                // Prefer multipart upload of the local file. Passing only a public URL
                // (especially via ngrok) often triggers Graph error code 1 even when
                // Facebook already created the photo on the page.
                if ($localPath && is_readable($localPath)) {
                    return $this->publishPhotoMultipart(
                        $endpoint,
                        $pageAccessToken,
                        $payload,
                        $localPath,
                        $target
                    );
                }

                $payload['url'] = $target->resolvedMediaUrl();
            } else {
                $endpoint .= '/feed';
                $payload = $this->platformFields->buildFacebookApiPayload($target, false);
            }

            $requestLog = [
                'method' => 'POST',
                'endpoint' => $endpoint,
                'body' => $this->redactRequestBody($payload),
                'upload_mode' => $hasMedia
                    ? ($isVideo ? 'video_remote_url' : 'remote_url')
                    : 'feed',
            ];

            Log::info('Facebook API request', $requestLog);

            $response = Http::timeout($isVideo ? 180 : 90)
                ->withToken($pageAccessToken)
                ->asForm()
                ->post($endpoint, $payload);

            return $this->interpretResponse($response, $requestLog, $pageAccessToken, $apiUrl, $apiVersion, $pageId, $isVideo);
        } catch (\Exception $e) {
            Log::error('Facebook publish exception: '.$e->getMessage());

            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function publishPhotoMultipart(
        string $endpoint,
        string $pageAccessToken,
        array $payload,
        string $localPath,
        PostTarget $target
    ): array {
        $caption = (string) ($payload['caption'] ?? '');
        $fileName = basename($localPath);
        $mime = $target->resolvedMimeType() ?: 'image/jpeg';
        $contents = file_get_contents($localPath);

        if ($contents === false) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Could not read local media file for Facebook upload.',
            ];
        }

        $requestLog = [
            'method' => 'POST',
            'endpoint' => $endpoint,
            'body' => [
                'caption' => $caption,
                'published' => true,
                'source' => $fileName,
                'bytes' => strlen($contents),
            ],
            'upload_mode' => 'multipart_local',
        ];

        Log::info('Facebook API request', $requestLog);

        $response = Http::timeout(120)
            ->withToken($pageAccessToken)
            ->attach('source', $contents, $fileName, ['Content-Type' => $mime])
            ->post($endpoint, [
                'caption' => $caption,
                'published' => 'true',
            ]);

        $apiVersion = config('social-platforms.platforms.facebook.api_version', 'v20.0');
        $apiUrl = config('social-platforms.platforms.facebook.api_url', 'https://graph.facebook.com');
        $pageId = $target->socialDestination?->provider_destination_id;

        return $this->interpretResponse(
            $response,
            $requestLog,
            $pageAccessToken,
            $apiUrl,
            $apiVersion,
            (string) $pageId,
            false
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function publishVideoMultipart(
        string $endpoint,
        string $pageAccessToken,
        array $payload,
        string $localPath,
        PostTarget $target
    ): array {
        $description = (string) ($payload['description'] ?? '');
        $title = (string) ($payload['title'] ?? '');
        $fileName = basename($localPath);
        $mime = $target->resolvedMimeType() ?: 'video/mp4';
        $contents = file_get_contents($localPath);

        if ($contents === false) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Could not read local video file for Facebook upload.',
            ];
        }

        $requestLog = [
            'method' => 'POST',
            'endpoint' => $endpoint,
            'body' => [
                'description' => $description,
                'title' => $title !== '' ? $title : null,
                'published' => true,
                'source' => $fileName,
                'bytes' => strlen($contents),
            ],
            'upload_mode' => 'video_multipart_local',
        ];

        Log::info('Facebook API request', $requestLog);

        $form = [
            'description' => $description,
            'published' => 'true',
        ];
        if ($title !== '') {
            $form['title'] = $title;
        }

        $response = Http::timeout(300)
            ->withToken($pageAccessToken)
            ->attach('source', $contents, $fileName, ['Content-Type' => $mime])
            ->post($endpoint, $form);

        $apiVersion = config('social-platforms.platforms.facebook.api_version', 'v20.0');
        $apiUrl = config('social-platforms.platforms.facebook.api_url', 'https://graph.facebook.com');
        $pageId = $target->socialDestination?->provider_destination_id;

        return $this->interpretResponse(
            $response,
            $requestLog,
            $pageAccessToken,
            $apiUrl,
            $apiVersion,
            (string) $pageId,
            true
        );
    }

    /**
     * @param  array<string, mixed>  $requestLog
     */
    protected function interpretResponse(
        Response $response,
        array $requestLog,
        string $pageAccessToken,
        string $apiUrl,
        string $apiVersion,
        string $pageId,
        bool $isVideo = false
    ): array {
        $responseBody = $response->json() ?? ['raw' => $response->body()];
        Log::info('Facebook API response', [
            'status' => $response->status(),
            'body' => $responseBody,
        ]);

        if ($response->successful()) {
            $data = is_array($responseBody) ? $responseBody : [];
            $postId = $data['post_id'] ?? $data['id'] ?? null;
            $postUrl = $postId ? "https://facebook.com/{$postId}" : null;

            return [
                'status' => 'success',
                'provider_post_id' => $postId,
                'provider_post_url' => $postUrl,
                'metadata' => $data,
                'request_payload' => $requestLog,
                'response_payload' => $data,
                'error' => null,
            ];
        }

        $errorData = is_array($responseBody) ? $responseBody : [];
        $errorMessage = $errorData['error']['message'] ?? 'Unknown Facebook API error';
        $errorUserMsg = $errorData['error']['error_user_msg'] ?? null;
        $errorCode = $errorData['error']['code'] ?? null;

        if (is_string($errorUserMsg) && $errorUserMsg !== '') {
            $errorMessage = $errorUserMsg.' ('.$errorMessage.')';
        }

        if ($errorCode == 200 || str_contains($errorMessage, 'pages_manage_posts')) {
            $errorMessage = 'Facebook publishing requires the pages_manage_posts permission. '
                .'In Meta App Dashboard → App Review, add pages_manage_posts to your app. '
                .'Then set FACEBOOK_OAUTH_SCOPES to include pages_manage_posts in .env, run php artisan config:clear, '
                .'disconnect Facebook in Accounts, and reconnect. '
                .'(Development mode: app admins can use this permission before full App Review.)';
        }

        if ($response->status() === 401 || ($errorData['error']['code'] ?? 0) == 190) {
            return [
                'status' => 'expired',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => $errorData,
                'request_payload' => $requestLog,
                'response_payload' => $errorData,
                'error' => 'Facebook access token expired or invalid: '.$errorMessage,
            ];
        }

        // Graph error code 1 is often a false failure after a successful photo create when
        // Facebook fetched media via URL / ngrok. Prefer verifying a recent photo id.
        if (! $isVideo && $this->isTransientReduceDataError($errorCode, $errorMessage) && $pageId !== '') {
            $recent = $this->findRecentPagePhoto($pageAccessToken, $apiUrl, $apiVersion, $pageId);
            if ($recent) {
                Log::warning('Facebook returned transient error code 1, but a recent page photo was found — treating as success.', [
                    'page_id' => $pageId,
                    'photo' => $recent,
                ]);

                $postId = $recent['post_id'] ?? $recent['id'] ?? null;

                return [
                    'status' => 'success',
                    'provider_post_id' => $postId,
                    'provider_post_url' => $postId ? "https://facebook.com/{$postId}" : null,
                    'metadata' => array_merge($errorData, ['recovered_from_transient_error' => true, 'photo' => $recent]),
                    'request_payload' => $requestLog,
                    'response_payload' => $errorData,
                    'error' => null,
                ];
            }

            $errorMessage = 'Facebook returned a temporary Graph API error (code 1). '
                .'The photo may still appear on your page — check Facebook before retrying to avoid duplicates. '
                .'Original: '.$errorMessage;
        }

        return [
            'status' => 'failed',
            'provider_post_id' => null,
            'provider_post_url' => null,
            'metadata' => $errorData,
            'request_payload' => $requestLog,
            'response_payload' => $errorData,
            'error' => $errorMessage,
        ];
    }

    protected function isTransientReduceDataError(mixed $errorCode, string $errorMessage): bool
    {
        return (int) $errorCode === 1
            || str_contains(strtolower($errorMessage), 'reduce the amount of data');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findRecentPagePhoto(
        string $pageAccessToken,
        string $apiUrl,
        string $apiVersion,
        string $pageId
    ): ?array {
        try {
            $response = Http::timeout(20)
                ->withToken($pageAccessToken)
                ->get("{$apiUrl}/{$apiVersion}/{$pageId}/photos", [
                    'type' => 'uploaded',
                    'fields' => 'id,post_id,created_time,name',
                    'limit' => 3,
                ]);

            if ($response->failed()) {
                return null;
            }

            $photos = $response->json('data') ?? [];
            if (! is_array($photos) || $photos === []) {
                return null;
            }

            $latest = $photos[0];
            if (! is_array($latest) || empty($latest['id'])) {
                return null;
            }

            $created = $latest['created_time'] ?? null;
            if (is_string($created) && $created !== '') {
                $createdAt = strtotime($created);
                // Only accept photos created in the last 3 minutes.
                if ($createdAt !== false && (time() - $createdAt) > 180) {
                    return null;
                }
            }

            return $latest;
        } catch (\Throwable $e) {
            Log::warning('Facebook recent photo lookup failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redactRequestBody(array $payload): array
    {
        return $payload;
    }
}
