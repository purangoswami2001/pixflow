<?php

namespace App\Services\Social;

use App\Models\PostTarget;
use App\Services\Posts\PlatformPostFieldsService;
use App\Services\Social\Contracts\SocialPublisherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SettingsService;

class InstagramPublisher implements SocialPublisherInterface
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
                'error' => 'Instagram account is disconnected. Reconnect it in Accounts, then retry this post.',
            ];
        }

        if (!$destination) {
            return [
                'status' => 'failed',
                'provider_post_id' => null,
                'provider_post_url' => null,
                'metadata' => [],
                'error' => 'Instagram profile is no longer linked. Reconnect Instagram in Accounts, then retry.',
            ];
        }

        // Mock Mode Check
        if (SettingsService::shouldMockPublish('instagram')) {
            Log::info("Instagram mock publishing for Target ID: {$target->id}");
            return [
                'status' => 'success',
                'provider_post_id' => 'ig_mock_post_' . uniqid(),
                'provider_post_url' => "https://instagram.com/p/mock-code-" . uniqid(),
                'metadata' => ['mock' => true],
                'error' => null
            ];
        }

        try {
            $accessToken = $account->access_token;
            $apiVersion = config('social-platforms.platforms.instagram.api_version', 'v20.0');
            $apiUrl = config('social-platforms.platforms.instagram.api_url', 'https://graph.facebook.com');

            $igAccountId = $destination->provider_destination_id;

            // Instagram requires media asset
            if (!$target->hasDisplayableMedia()) {
                return [
                    'status' => 'failed',
                    'provider_post_id' => null,
                    'provider_post_url' => null,
                    'metadata' => [],
                    'error' => 'Instagram posts must include a media asset (image/video).'
                ];
            }

            $mediaUrl = $target->resolvedMediaUrl();
            $isVideo = $target->isVideoMedia();

            // Step 1: Create Container
            $containerEndpoint = "{$apiUrl}/{$apiVersion}/{$igAccountId}/media";
            $containerPayload = $this->platformFields->buildInstagramContainerPayload($target, $mediaUrl);

            $containerRequestLog = [
                'method' => 'POST',
                'endpoint' => $containerEndpoint,
                'body' => $containerPayload,
                'media_kind' => $isVideo ? 'video' : 'image',
            ];

            Log::info('Instagram container API request', $containerRequestLog);
            $containerResponse = Http::timeout(90)->withToken($accessToken)->post($containerEndpoint, $containerPayload);
            $containerBody = $containerResponse->json() ?? ['raw' => $containerResponse->body()];
            Log::info('Instagram container API response', [
                'status' => $containerResponse->status(),
                'body' => $containerBody,
            ]);

            if ($containerResponse->failed()) {
                $errorData = is_array($containerBody) ? $containerBody : [];
                $errorMessage = $errorData['error']['message'] ?? 'Failed to create Instagram media container';
                $subcode = $errorData['error']['error_subcode'] ?? null;

                if ($subcode == 2207023) {
                    $errorMessage = 'Instagram rejected the media container. For feed photos use an image post (not Reel). '
                        . 'Do not send share_to_feed with image_url — that field is for Reels only.';
                }

                if ($isVideo && str_contains(strtolower($errorMessage), 'url')) {
                    $errorMessage .= ' Video posts require a publicly reachable HTTPS media URL (not localhost/ngrok-blocked hosts).';
                }
                
                if ($containerResponse->status() === 401 || ($errorData['error']['code'] ?? 0) == 190) {
                    return [
                        'status' => 'expired',
                        'provider_post_id' => null,
                        'provider_post_url' => null,
                        'metadata' => $errorData,
                        'request_payload' => $containerRequestLog,
                        'response_payload' => $errorData,
                        'error' => 'Instagram access token expired: ' . $errorMessage
                    ];
                }

                return [
                    'status' => 'failed',
                    'provider_post_id' => null,
                    'provider_post_url' => null,
                    'metadata' => $errorData,
                    'request_payload' => $containerRequestLog,
                    'response_payload' => $errorData,
                    'error' => $errorMessage
                ];
            }

            $containerId = is_array($containerBody) ? ($containerBody['id'] ?? null) : null;

            if (!$containerId) {
                return [
                    'status' => 'failed',
                    'provider_post_id' => null,
                    'provider_post_url' => null,
                    'metadata' => [],
                    'error' => 'No container ID returned from Instagram'
                ];
            }

            // Step 2: Poll Container Status (videos need longer processing)
            $statusEndpoint = "{$apiUrl}/{$apiVersion}/{$containerId}";
            $isReady = false;
            $pollAttempts = 0;
            $maxPolls = $isVideo ? 20 : 5;
            $pollSleep = $isVideo ? 5 : 3;

            while (!$isReady && $pollAttempts < $maxPolls) {
                $pollAttempts++;
                sleep($pollSleep);

                Log::debug("Instagram Poll Status Attempt {$pollAttempts}: GET {$statusEndpoint}");
                $statusResponse = Http::withToken($accessToken)->get($statusEndpoint, [
                    'fields' => 'status_code,status'
                ]);

                if ($statusResponse->successful()) {
                    $statusCode = $statusResponse->json()['status_code'] ?? '';
                    if ($statusCode === 'FINISHED') {
                        $isReady = true;
                    } elseif ($statusCode === 'ERROR') {
                        return [
                            'status' => 'failed',
                            'provider_post_id' => null,
                            'provider_post_url' => null,
                            'metadata' => $statusResponse->json() ?? [],
                            'error' => 'Instagram container processing failed with status ERROR'
                        ];
                    }
                }
            }

            if (!$isReady) {
                return [
                    'status' => 'failed',
                    'provider_post_id' => null,
                    'provider_post_url' => null,
                    'metadata' => [],
                    'error' => $isVideo
                        ? 'Instagram video processing timed out. Try a shorter MP4 (H.264) and ensure the media URL is publicly reachable.'
                        : 'Instagram container processing timed out'
                ];
            }

            // Step 3: Publish Container
            $publishEndpoint = "{$apiUrl}/{$apiVersion}/{$igAccountId}/media_publish";
            $publishPayload = ['creation_id' => $containerId];
            $publishRequestLog = [
                'method' => 'POST',
                'endpoint' => $publishEndpoint,
                'body' => $publishPayload,
                'container_request' => $containerRequestLog,
            ];

            Log::info('Instagram publish API request', $publishRequestLog);
            
            $publishResponse = Http::withToken($accessToken)->post($publishEndpoint, $publishPayload);
            $publishBody = $publishResponse->json() ?? ['raw' => $publishResponse->body()];
            Log::info('Instagram publish API response', [
                'status' => $publishResponse->status(),
                'body' => $publishBody,
            ]);

            if ($publishResponse->failed()) {
                $errorData = is_array($publishBody) ? $publishBody : [];

                return [
                    'status' => 'failed',
                    'provider_post_id' => null,
                    'provider_post_url' => null,
                    'metadata' => $errorData,
                    'request_payload' => $publishRequestLog,
                    'response_payload' => $errorData,
                    'error' => $errorData['error']['message'] ?? 'Failed to publish Instagram media'
                ];
            }

            $publishedId = is_array($publishBody) ? ($publishBody['id'] ?? null) : null;
            
            // To get permalink, we can poll media endpoint
            $mediaUrlEndpoint = "{$apiUrl}/{$apiVersion}/{$publishedId}";
            $mediaInfo = Http::withToken($accessToken)->get($mediaUrlEndpoint, ['fields' => 'permalink']);
            $permalink = $mediaInfo->json()['permalink'] ?? "https://instagram.com";

            return [
                'status' => 'success',
                'provider_post_id' => $publishedId,
                'provider_post_url' => $permalink,
                'metadata' => is_array($publishBody) ? $publishBody : [],
                'request_payload' => $publishRequestLog,
                'response_payload' => is_array($publishBody) ? $publishBody : [],
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error("Instagram publish exception: " . $e->getMessage());
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
