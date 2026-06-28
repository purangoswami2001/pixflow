<?php

namespace App\Services\Social;

use App\Models\PostTarget;
use App\Services\Posts\PlatformPostFieldsService;
use App\Services\Social\Contracts\SocialPublisherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SettingsService;

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
        $post = $target->post;

        if (!$account || $account->isDisconnected()) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Facebook account is disconnected. Reconnect it in Accounts, then retry this post.',
            ];
        }

        if (!$destination) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Facebook page is no longer linked. Reconnect Facebook in Accounts, then retry.',
            ];
        }

        // Mock Mode Check
        if (SettingsService::shouldMockPublish('facebook')) {
            Log::info("Facebook mock publishing for Target ID: {$target->id}");
            return [
                'status' => 'success',
                'provider_post_id' => 'fb_mock_post_' . uniqid(),
                'provider_post_url' => "https://facebook.com/mock-page/posts/" . uniqid(),
                'metadata' => ['mock' => true, 'payload' => $target->platform_payload],
                'error' => null
            ];
        }

        try {
            $accessToken = $account->access_token;
            // Facebook Graph API base URL and version from config
            $apiVersion = config('social-platforms.platforms.facebook.api_version', 'v20.0');
            $apiUrl = config('social-platforms.platforms.facebook.api_url', 'https://graph.facebook.com');

            // Pages require page-specific access token. If stored in destination metadata, use it.
            $pageAccessToken = $destination->metadata['access_token'] ?? $accessToken;

            $endpoint = "{$apiUrl}/{$apiVersion}/{$destination->provider_destination_id}";
            $hasMedia = $target->hasDisplayableMedia();

            if ($hasMedia) {
                $endpoint .= '/photos';
                $payload = $this->platformFields->buildFacebookApiPayload($target, true);
                $payload['url'] = $target->resolvedMediaUrl();
            } else {
                $endpoint .= '/feed';
                $payload = $this->platformFields->buildFacebookApiPayload($target, false);
            }

            $requestLog = [
                'method' => 'POST',
                'endpoint' => $endpoint,
                'body' => $payload,
            ];

            Log::info('Facebook API request', $requestLog);

            $response = Http::withToken($pageAccessToken)
                ->post($endpoint, $payload);

            $responseBody = $response->json() ?? ['raw' => $response->body()];
            Log::info('Facebook API response', [
                'status' => $response->status(),
                'body' => $responseBody,
            ]);

            if ($response->failed()) {
                $errorData = is_array($responseBody) ? $responseBody : [];
                $errorMessage = $errorData['error']['message'] ?? 'Unknown Facebook API error';
                $errorCode = $errorData['error']['code'] ?? null;

                if ($errorCode == 200 || str_contains($errorMessage, 'pages_manage_posts')) {
                    $errorMessage = 'Facebook publishing requires the pages_manage_posts permission. '
                        . 'In Meta App Dashboard → App Review, add pages_manage_posts to your app. '
                        . 'Then set FACEBOOK_OAUTH_SCOPES to include pages_manage_posts in .env, run php artisan config:clear, '
                        . 'disconnect Facebook in Accounts, and reconnect. '
                        . '(Development mode: app admins can use this permission before full App Review.)';
                }
                
                // If token expired, we will throw a custom error to handle in job
                if ($response->status() === 401 || ($errorData['error']['code'] ?? 0) == 190) {
                    return [
                        'status' => 'expired',
                        'provider_post_id' => null,
                        'provider_post_url' => null,
                        'metadata' => $errorData,
                        'request_payload' => $requestLog,
                        'response_payload' => $errorData,
                        'error' => 'Facebook access token expired or invalid: ' . $errorMessage
                    ];
                }

                return [
                    'status' => 'failed',
                    'provider_post_id' => null,
                    'provider_post_url' => null,
                    'metadata' => $errorData,
                    'request_payload' => $requestLog,
                    'response_payload' => $errorData,
                    'error' => $errorMessage
                ];
            }

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
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error("Facebook publish exception: " . $e->getMessage());
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}
