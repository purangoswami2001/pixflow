<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialDestination;
use App\Models\PostTarget;
use App\Models\User;
use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use App\Enums\DestinationType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SettingsService;
use App\Services\Social\PinterestApiClient;
use App\Support\OrganizationContext;

class SocialAccountManager
{
    protected function resolveOrganizationId(User $user): int
    {
        return $user->current_organization_id
            ?? app(OrganizationContext::class)->requireId();
    }

    /**
     * Connect a mock social account for testing.
     */
    public function connectMock(User $user, string $provider, string $name, string $username = null): SocialAccount
    {
        $providerType = ProviderType::from($provider);
        $providerUserId = 'mock_usr_' . rand(1000, 9999);
        $organizationId = $this->resolveOrganizationId($user);

        // Delete existing mock account of same provider to keep it clean
        SocialAccount::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('provider', $providerType)
            ->where('provider_user_id', $providerUserId)
            ->delete();

        $account = SocialAccount::create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'provider' => $providerType,
            'provider_user_id' => $providerUserId,
            'name' => $name,
            'username' => $username ?? strtolower(str_replace(' ', '', $name)),
            'avatar_url' => "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random",
            'access_token' => 'mock_access_token_' . uniqid(),
            'refresh_token' => 'mock_refresh_token_' . uniqid(),
            'token_expires_at' => now()->addDays(60),
            'scopes' => config("social-platforms.platforms.{$provider}.scopes"),
            'metadata' => ['mock' => true],
            'status' => SocialAccountStatus::ACTIVE,
        ]);

        $this->syncDestinations($account);

        return $account;
    }

    /**
     * Disconnect social account while preserving post history.
     */
    public function disconnect(SocialAccount $account): bool
    {
        PostTarget::query()
            ->where('social_account_id', $account->id)
            ->each(function (PostTarget $target) {
                $target->update([
                    'connection_snapshot' => $target->buildConnectionSnapshot(),
                ]);
            });

        $account->update([
            'status' => SocialAccountStatus::DISCONNECTED,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'last_error' => null,
        ]);

        $account->destinations()->get()->each(function ($destination) {
            $metadata = $destination->metadata ?? [];
            unset($metadata['access_token']);

            $destination->update([
                'status' => 'inactive',
                'metadata' => $metadata,
            ]);
        });

        return true;
    }

    /**
     * Sync destinations (boards, pages, business accounts) for a social account.
     */
    public function syncDestinations(SocialAccount $account): void
    {
        // Mock Mode Sync
        if (SettingsService::usesMockMode() || ($account->metadata['mock'] ?? false)) {
            $account->destinations()->delete();

            $destinationType = match ($account->provider) {
                ProviderType::FACEBOOK => DestinationType::FACEBOOK_PAGE,
                ProviderType::INSTAGRAM => DestinationType::INSTAGRAM_ACCOUNT,
                ProviderType::PINTEREST => DestinationType::PINTEREST_BOARD,
            };

            SocialDestination::create([
                'organization_id' => $account->organization_id,
                'social_account_id' => $account->id,
                'provider' => $account->provider,
                'destination_type' => $destinationType,
                'provider_destination_id' => 'mock_dest_' . $account->id,
                'name' => $account->name,
                'avatar_url' => $account->avatar_url,
                'status' => 'active',
                'metadata' => $account->provider === ProviderType::FACEBOOK
                    ? ['access_token' => 'mock_page_token_' . $account->id]
                    : [],
            ]);

            return;
        }

        // Real API integrations
        try {
            $apiUrl = config("social-platforms.platforms.{$account->provider->value}.api_url");
            $version = config("social-platforms.platforms.{$account->provider->value}.api_version");

            if ($account->provider === ProviderType::FACEBOOK) {
                // Fetch user pages
                $response = Http::withToken($account->access_token)
                    ->get("{$apiUrl}/{$version}/me/accounts");

                if ($response->successful()) {
                    $pages = $response->json()['data'] ?? [];
                    $page = $pages[0] ?? null;
                    if ($page) {
                        SocialDestination::updateOrCreate(
                            [
                                'organization_id' => $account->organization_id,
                                'provider' => ProviderType::FACEBOOK,
                                'provider_destination_id' => $page['id'],
                            ],
                            [
                                'social_account_id' => $account->id,
                                'destination_type' => DestinationType::FACEBOOK_PAGE,
                                'name' => $page['name'],
                                'avatar_url' => "https://graph.facebook.com/{$page['id']}/picture",
                                'status' => 'active',
                                'metadata' => ['access_token' => $page['access_token'] ?? null],
                            ]
                        );
                        $destination = SocialDestination::where('organization_id', $account->organization_id)
                            ->where('provider', ProviderType::FACEBOOK)
                            ->where('provider_destination_id', $page['id'])
                            ->first();
                        if ($destination) {
                            PostTarget::syncAccountForDestination($destination);
                        }
                    }

                    $account->destinations()
                        ->where('provider', ProviderType::FACEBOOK)
                        ->whereNotIn('provider_destination_id', collect($pages)->pluck('id')->filter()->all())
                        ->update(['status' => 'inactive']);
                }
            } elseif ($account->provider === ProviderType::INSTAGRAM) {
                // Instagram Business Accounts require fetching Facebook Pages, then getting their connected IG business account
                $response = Http::withToken($account->access_token)
                    ->get("{$apiUrl}/{$version}/me/accounts", [
                        'fields' => 'instagram_business_account{id,name,username,profile_picture_url}'
                    ]);

                if ($response->successful()) {
                    $pages = $response->json()['data'] ?? [];
                    $syncedIds = [];

                    foreach ($pages as $page) {
                        if (! isset($page['instagram_business_account'])) {
                            continue;
                        }
                        $ig = $page['instagram_business_account'];
                        $syncedIds[] = $ig['id'];

                        SocialDestination::updateOrCreate(
                            [
                                'organization_id' => $account->organization_id,
                                'provider' => ProviderType::INSTAGRAM,
                                'provider_destination_id' => $ig['id'],
                            ],
                            [
                                'social_account_id' => $account->id,
                                'destination_type' => DestinationType::INSTAGRAM_ACCOUNT,
                                'name' => $ig['name'] ?? $ig['username'] ?? $account->name,
                                'avatar_url' => $ig['profile_picture_url'] ?? $account->avatar_url,
                                'status' => 'active',
                                'metadata' => ['connected_page_id' => $page['id']],
                            ]
                        );
                        $destination = SocialDestination::where('organization_id', $account->organization_id)
                            ->where('provider', ProviderType::INSTAGRAM)
                            ->where('provider_destination_id', $ig['id'])
                            ->first();
                        if ($destination) {
                            PostTarget::syncAccountForDestination($destination);
                        }
                        break;
                    }

                    if ($syncedIds !== []) {
                        $account->destinations()
                            ->where('provider', ProviderType::INSTAGRAM)
                            ->whereNotIn('provider_destination_id', $syncedIds)
                            ->update(['status' => 'inactive']);
                    }
                }
            } elseif ($account->provider === ProviderType::PINTEREST) {
                // Pinterest publish destinations are chosen manually on the board picker.
                return;
            }

            $this->finalizeDestinationSync($account);
        } catch (\Exception $e) {
            Log::error("Error syncing destinations for account {$account->id}: " . $e->getMessage());
            $account->update([
                'status' => SocialAccountStatus::ERROR,
                'last_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPinterestBoards(SocialAccount $account): array
    {
        return PinterestApiClient::listBoards($account->access_token, $account);
    }

    public function assignPinterestBoard(SocialAccount $account, string $boardId): SocialDestination
    {
        $this->savePinterestBoards($account, [$boardId]);

        return $account->destinations()
            ->where('provider_destination_id', $boardId)
            ->firstOrFail();
    }

    /**
     * @param  array<int, string>  $selectedBoardIds
     */
    public function savePinterestBoards(SocialAccount $account, array $selectedBoardIds): int
    {
        $selectedBoardIds = array_values(array_unique(array_filter($selectedBoardIds)));

        if ($selectedBoardIds === []) {
            throw new \RuntimeException('Select at least one board.');
        }

        $apiBoards = collect($this->listPinterestBoards($account))->keyBy('id');

        foreach ($selectedBoardIds as $boardId) {
            $board = $apiBoards->get($boardId);

            if (!$board) {
                $board = $this->fetchPinterestBoard($account, $boardId);
            }

            $this->upsertPinterestBoard($account, $board, true);
        }

        $account->destinations()
            ->where('provider', ProviderType::PINTEREST)
            ->where('status', 'active')
            ->whereNotIn('provider_destination_id', $selectedBoardIds)
            ->update(['status' => 'inactive']);

        $account->update([
            'status' => SocialAccountStatus::ACTIVE,
            'last_error' => null,
        ]);

        return count($selectedBoardIds);
    }

  /**
   * @return array<string, mixed>
   */
    protected function fetchPinterestBoard(SocialAccount $account, string $boardId): array
    {
        $response = PinterestApiClient::api($account->access_token)
            ->get(PinterestApiClient::apiUrlForAccount($account) . '/' . PinterestApiClient::version() . '/boards/' . $boardId);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Selected board was not found: ' . PinterestApiClient::errorMessage($response, 'Unknown error')
            );
        }

        return $response->json();
    }

  /**
   * @param  array<string, mixed>  $board
   */
    protected function upsertPinterestBoard(SocialAccount $account, array $board, bool $active = true): SocialDestination
    {
        $boardId = (string) ($board['id'] ?? '');

        $destination = SocialDestination::updateOrCreate(
            [
                'organization_id' => $account->organization_id,
                'provider' => ProviderType::PINTEREST,
                'provider_destination_id' => $boardId,
            ],
            [
                'social_account_id' => $account->id,
                'destination_type' => DestinationType::PINTEREST_BOARD,
                'name' => $board['name'] ?? 'Pinterest Board',
                'avatar_url' => $board['media']['image_cover_url'] ?? $account->avatar_url,
                'status' => $active ? 'active' : 'inactive',
            ]
        );

        PostTarget::syncAccountForDestination($destination);

        return $destination;
    }

    protected function finalizeDestinationSync(SocialAccount $account): void
    {
        $account->refresh();

        if ($account->primaryDestination()) {
            $account->update([
                'status' => SocialAccountStatus::ACTIVE,
                'last_error' => null,
            ]);

            return;
        }

        $message = match ($account->provider) {
            ProviderType::PINTEREST => 'Select a Pinterest board to publish to (Accounts → Select board).',
            ProviderType::INSTAGRAM => 'No Instagram business account found. Link Instagram to a Facebook Page in Meta, then sync.',
            ProviderType::FACEBOOK => 'No Facebook Page found for this account. Add a Page in Meta, then sync.',
            default => 'No publish destination is available for this account.',
        };

        $account->update([
            'status' => SocialAccountStatus::ACTIVE,
            'last_error' => $message,
        ]);
    }

    /**
     * Refresh OAuth access tokens if refresh token is available and token is expired.
     */
    public function refreshTokens(): void
    {
        $expiredAccounts = SocialAccount::connected()
            ->whereNotNull('refresh_token')
            ->where(function ($query) {
                $query->where('token_expires_at', '<=', now()->addMinutes(10))
                      ->orWhere('status', SocialAccountStatus::EXPIRED);
            })
            ->get();

        foreach ($expiredAccounts as $account) {
            if ($account->metadata['mock'] ?? false) {
                $account->update([
                    'token_expires_at' => now()->addDays(60),
                    'status' => SocialAccountStatus::ACTIVE
                ]);
                continue;
            }

            try {
                // Refresh OAuth tokens using credentials defined in config
                $provider = $account->provider->value;
                $apiUrl = config("social-platforms.platforms.{$provider}.api_url");

                $response = null;
                if ($account->provider === ProviderType::PINTEREST) {
                    $oauth = SettingsService::platformOAuth('pinterest');
                    $response = Http::asForm()
                        ->acceptJson()
                        ->withBasicAuth(
                            $oauth['client_id'],
                            $oauth['client_secret']
                        )
                        ->post(PinterestApiClient::tokenUrlForAccount($account), [
                            'grant_type' => 'refresh_token',
                            'refresh_token' => $account->refresh_token,
                        ]);
                }

                if ($response && $response->successful()) {
                    $data = $response->json();
                    $account->update([
                        'access_token' => $data['access_token'],
                        'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                        'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                        'status' => SocialAccountStatus::ACTIVE
                    ]);
                    Log::info("Refreshed Pinterest token for Account ID: {$account->id}");
                } else {
                    $account->update(['status' => SocialAccountStatus::EXPIRED]);
                    Log::warning("Failed to refresh token for Account ID: {$account->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to refresh token for Account ID: {$account->id}. Error: " . $e->getMessage());
            }
        }
    }
}
