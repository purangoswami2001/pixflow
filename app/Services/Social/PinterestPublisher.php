<?php

namespace App\Services\Social;

use App\Models\PostTarget;
use App\Services\Posts\PlatformPostFieldsService;
use App\Services\Social\Contracts\SocialPublisherInterface;
use Illuminate\Support\Facades\Log;
use App\Services\SettingsService;

class PinterestPublisher implements SocialPublisherInterface
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
            return $this->failed('Pinterest account is disconnected. Reconnect it in Accounts, then retry this post.');
        }

        if (SettingsService::shouldMockPublish('pinterest')) {
            Log::info("Pinterest mock publishing for Target ID: {$target->id}");

            return [
                'status' => 'success',
                'provider_post_id' => 'pin_mock_post_' . uniqid(),
                'provider_post_url' => 'https://pinterest.com/pin/mock-id-' . uniqid(),
                'metadata' => ['mock' => true],
                'error' => null,
            ];
        }

        try {
            $accessToken = $account->access_token;
            $apiVersion = PinterestApiClient::version();
            $apiUrl = PinterestApiClient::apiUrlForAccount($account);

            if (!$destination) {
                return $this->failed('No Pinterest board is linked to this post. Re-select a board on Accounts.');
            }

            if (!$target->hasDisplayableMedia()) {
                return $this->failed('Pinterest Pins must include an image or video.');
            }

            $endpoint = "{$apiUrl}/{$apiVersion}/pins";

            if ($target->isVideoMedia()) {
                $mediaSource = PinterestApiClient::buildVideoMediaSourceForTarget($target, $accessToken);
            } else {
                $mediaSource = PinterestApiClient::buildImageMediaSourceForTarget($target);
            }

            $payload = $this->platformFields->buildPinterestApiPayload($target, $mediaSource);

            $requestLog = [
                'method' => 'POST',
                'endpoint' => $endpoint,
                'body' => PinterestApiClient::redactPayloadForLog($payload),
            ];

            Log::info('Pinterest Pin API request', $requestLog);

            $response = PinterestApiClient::api($accessToken)->post($endpoint, $payload);

            $responseBody = $response->json() ?? ['raw' => $response->body()];
            Log::info('Pinterest Pin API response', [
                'status' => $response->status(),
                'body' => $responseBody,
            ]);

            if ($response->failed()) {
                $errorData = is_array($responseBody) ? $responseBody : [];
                $errorMessage = $errorData['message'] ?? 'Failed to create Pin';
                $errorCode = $errorData['code'] ?? null;

                if ($errorCode === 29 || str_contains(strtolower($errorMessage), 'sandbox')) {
                    PinterestApiClient::markAccountTrial($account);

                    return $this->failed(
                        'Your Pinterest app is on Trial access and must use the API Sandbox. '
                        . 'Set PINTEREST_SANDBOX=true in .env, run php artisan config:clear, '
                        . 'then disconnect Pinterest, reconnect, and retry this post.',
                        $errorData,
                        $requestLog,
                        $errorData
                    );
                }

                if ($response->status() === 401) {
                    return [
                        'status' => 'expired',
                        'provider_post_id' => null,
                        'provider_post_url' => null,
                        'metadata' => $errorData,
                        'request_payload' => $requestLog,
                        'response_payload' => $errorData,
                        'error' => 'Pinterest access token expired or unauthorized: ' . $errorMessage,
                    ];
                }

                return $this->failed($errorMessage, $errorData, $requestLog, $errorData);
            }

            $data = is_array($responseBody) ? $responseBody : [];
            $pinId = $data['id'] ?? null;
            $pinUrl = $pinId ? "https://pinterest.com/pin/{$pinId}" : null;

            return [
                'status' => 'success',
                'provider_post_id' => $pinId,
                'provider_post_url' => $pinUrl,
                'metadata' => $data,
                'request_payload' => $requestLog,
                'response_payload' => $data,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Pinterest publish exception: ' . $e->getMessage());

            return $this->failed($e->getMessage());
        }
    }

  /**
   * @param  array<string, mixed>  $metadata
   * @param  array<string, mixed>|null  $requestPayload
   * @param  array<string, mixed>|null  $responsePayload
   * @return array<string, mixed>
   */
    protected function failed(
        string $message,
        array $metadata = [],
        ?array $requestPayload = null,
        ?array $responsePayload = null
    ): array {
        return [
            'status' => 'failed',
            'provider_post_id' => null,
            'provider_post_url' => null,
            'metadata' => $metadata,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload ?? $metadata,
            'error' => $message,
        ];
    }
}
