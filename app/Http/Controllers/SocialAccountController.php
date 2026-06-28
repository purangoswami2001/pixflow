<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\SocialAccountManager;
use App\Services\Social\PinterestApiClient;
use App\Services\SettingsService;
use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialAccountController extends Controller
{
    protected SocialAccountManager $manager;

    public function __construct(SocialAccountManager $manager)
    {
        $this->manager = $manager;
    }

    public function index()
    {
        $connectedAccounts = SocialAccount::with(['destinations'])
            ->connected()
            ->where('user_id', auth()->id())
            ->orderBy('provider')
            ->get();

        $platformSandboxes = collect(SettingsService::allPlatformsFromEnv())
            ->mapWithKeys(fn ($config, $provider) => [$provider => (bool) ($config['sandbox'] ?? false)])
            ->all();

        return view('accounts.index', [
            'connectedAccounts' => $connectedAccounts,
            'mockMode' => SettingsService::usesMockMode(),
            'platformSandboxes' => $platformSandboxes,
        ]);
    }

    public function connectMock(Request $request)
    {
        $request->validate([
            'mockName' => 'required|string|max:50',
            'mockUsername' => 'nullable|string|max:50',
            'mockProvider' => 'required|in:facebook,instagram,pinterest',
        ]);

        $this->manager->connectMock(
            auth()->user(),
            $request->mockProvider,
            $request->mockName,
            $request->mockUsername ?: null
        );

        return redirect()->route('accounts.index')
            ->with('status', 'Mock ' . ucfirst($request->mockProvider) . ' profile connected successfully!');
    }

    public function sync($id)
    {
        $account = SocialAccount::where('user_id', auth()->id())->findOrFail($id);

        if ($account->provider === ProviderType::PINTEREST) {
            return redirect()->route('accounts.pinterest.boards', $account->id);
        }

        $this->manager->syncDestinations($account);
        $account->refresh();

        if ($account->primaryDestination()) {
            return redirect()->route('accounts.index')
                ->with('status', "Destinations synced successfully for {$account->name}!");
        }

        return redirect()->route('accounts.index')
            ->with('error', $account->last_error ?? "Could not sync a publish destination for {$account->name}.");
    }

    public function selectPinterestBoard($id)
    {
        $account = $this->findOwnedPinterestAccount($id);
        $boards = [];
        $loadError = null;

        try {
            $boards = $this->manager->listPinterestBoards($account);
        } catch (\Throwable $e) {
            Log::error('Pinterest board list failed', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);
            $loadError = $e->getMessage();
        }

        $selectedBoardIds = $account->activeDestinations()
            ->pluck('provider_destination_id')
            ->all();

        return view('accounts.pinterest-boards', [
            'account' => $account,
            'boards' => $boards,
            'selectedBoardIds' => $selectedBoardIds,
            'loadError' => $loadError,
            'pinterestSandbox' => SettingsService::platformUsesSandbox('pinterest'),
        ]);
    }

    public function storePinterestBoard(Request $request, $id)
    {
        $account = $this->findOwnedPinterestAccount($id);

        $request->validate([
            'board_ids' => 'required|array|min:1',
            'board_ids.*' => 'string|max:64',
        ]);

        try {
            $count = $this->manager->savePinterestBoards($account, $request->board_ids);
            $names = $account->fresh()->activeDestinations()->pluck('name')->join(', ');

            return redirect()->route('accounts.index')
                ->with('status', "Connected {$count} Pinterest board(s): {$names}");
        } catch (\Throwable $e) {
            Log::error('Pinterest board selection failed', [
                'account_id' => $account->id,
                'board_id' => $request->input('board_ids.0'),
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('accounts.pinterest.boards', $account->id)
                ->with('error', $e->getMessage());
        }
    }

    protected function findOwnedPinterestAccount($id): SocialAccount
    {
        $account = SocialAccount::where('user_id', auth()->id())->findOrFail($id);
        abort_unless($account->provider === ProviderType::PINTEREST, 404);

        return $account;
    }

    public function disconnect($id)
    {
        $account = SocialAccount::where('user_id', auth()->id())->findOrFail($id);
        $this->manager->disconnect($account);

        return redirect()->route('accounts.index')
            ->with('status', 'Social account disconnected successfully.');
    }

    public function redirectToProvider($provider)
    {
        $providerType = ProviderType::tryFrom($provider);
        if (!$providerType) {
            return redirect()->route('accounts.index')->with('error', "Unsupported platform '{$provider}'");
        }

        $oauth = SettingsService::platformOAuth($provider);
        $clientId = $oauth['client_id'];
        $redirectUri = $oauth['redirect_uri'];
        $scopes = $oauth['scopes'];

        if (empty($clientId)) {
            return redirect()->route('accounts.index')->with('error', "API credentials not configured for {$provider}. Set them in your .env file.");
        }

        $state = bin2hex(random_bytes(16));
        session([
            'oauth_state' => $state,
            'oauth_user_id' => auth()->id(),
            'oauth_provider' => $provider,
        ]);

        if ($provider === 'facebook' || $provider === 'instagram') {
            $query = [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'state' => $state,
                'scope' => implode(',', $scopes),
                'response_type' => 'code',
                'auth_type' => 'rerequest',
            ];

            $url = 'https://www.facebook.com/v20.0/dialog/oauth?' . http_build_query($query);
        } elseif ($provider === 'pinterest') {
            $scopeList = implode(',', PinterestApiClient::oauthScopes());
            $url = 'https://www.pinterest.com/oauth/?' . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'state' => $state,
                'scope' => $scopeList,
                'response_type' => 'code',
            ]);
        } else {
            return redirect()->route('accounts.index')->with('error', "Unsupported platform redirect");
        }

        return redirect()->away($url);
    }

    public function handleProviderCallback($provider, Request $request)
    {
        $providerType = ProviderType::tryFrom($provider);
        if (!$providerType) {
            return redirect()->route('accounts.index')->with('error', "Unsupported platform '{$provider}'");
        }

        if (!auth()->check() && session('oauth_user_id')) {
            auth()->loginUsingId(session('oauth_user_id'));
        }

        if (!auth()->check() || !auth()->user()->isAdmin()) {
            return redirect()->route('login')
                ->with('error', 'Please sign in as an admin to complete connecting your social account.');
        }

        // Facebook & Instagram share Meta OAuth — session records which connect was started
        $sessionProvider = session('oauth_provider');
        if (
            in_array($provider, ['facebook', 'instagram'], true)
            && in_array($sessionProvider, ['facebook', 'instagram'], true)
        ) {
            $provider = $sessionProvider;
            $providerType = ProviderType::from($provider);
        }

        // Validate state
        $state = $request->query('state');
        if (empty($state) || $state !== session('oauth_state')) {
            return redirect()->route('accounts.index')->with('error', 'OAuth state verification failed. Possible CSRF attempt.');
        }

        session()->forget(['oauth_state', 'oauth_user_id', 'oauth_provider']);

        $code = $request->query('code');
        if (empty($code)) {
            $errorDesc = $request->query('error_description') ?? $request->query('error') ?? 'User cancelled authorize authorization.';
            return redirect()->route('accounts.index')->with('error', "Connection failed: {$errorDesc}");
        }

        $oauth = SettingsService::platformOAuth($provider);
        $clientId = $oauth['client_id'];
        $clientSecret = $oauth['client_secret'];
        $redirectUri = $oauth['redirect_uri'];

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('accounts.index')->with('error', "API Credentials not configured for {$provider}. Add App ID and Secret in Admin → Settings.");
        }

        try {
            $accessToken = null;
            $refreshToken = null;
            $expiresAt = null;
            $scopes = $oauth['scopes'];

            if ($provider === 'facebook' || $provider === 'instagram') {
                $apiUrl = $oauth['api_url'] ?? config('social-platforms.platforms.facebook.api_url');
                $version = $oauth['api_version'] ?? config('social-platforms.platforms.facebook.api_version');
                
                // Exchange code for short-lived access token
                $response = Http::get("{$apiUrl}/{$version}/oauth/access_token", [
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                ]);

                if ($response->failed()) {
                    throw new \Exception("Token exchange failed: " . ($response->json()['error']['message'] ?? 'Unknown error'));
                }

                $tokenData = $response->json();
                $accessToken = $tokenData['access_token'];
                $expiresIn = $tokenData['expires_in'] ?? null;
                $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;

                // Optionally exchange for long-lived access token
                $longLivedResponse = Http::get("{$apiUrl}/{$version}/oauth/access_token", [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'fb_exchange_token' => $accessToken,
                ]);

                if ($longLivedResponse->successful()) {
                    $llData = $longLivedResponse->json();
                    $accessToken = $llData['access_token'];
                    $expiresIn = $llData['expires_in'] ?? null;
                    $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;
                }

                $scopes = $this->fetchMetaGrantedScopes($apiUrl, $version, $accessToken, $oauth['scopes']);
                $meResponse = Http::withToken($accessToken)
                    ->get("{$apiUrl}/{$version}/me", [
                        'fields' => 'id,name,picture'
                    ]);

                if ($meResponse->failed()) {
                    throw new \Exception("Me details fetch failed");
                }

                $meData = $meResponse->json();
                $providerUserId = $meData['id'];
                $name = $meData['name'];
                $username = strtolower(str_replace(' ', '', $name)) . '_' . $providerUserId;
                // Short stable URL — Facebook CDN picture URLs exceed VARCHAR(191).
                $avatarUrl = "https://graph.facebook.com/{$providerUserId}/picture?type=large";

            } elseif ($provider === 'pinterest') {
                $response = Http::asForm()
                    ->acceptJson()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->post(PinterestApiClient::tokenUrl(), [
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirectUri,
                    ]);

                if ($response->failed()) {
                    Log::error('Pinterest token exchange failed', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);
                    throw new \Exception('Token exchange failed: ' . PinterestApiClient::errorMessage($response, 'Unknown error'));
                }

                $tokenData = $response->json();
                $accessToken = $tokenData['access_token'];
                $refreshToken = $tokenData['refresh_token'] ?? null;
                $expiresIn = $tokenData['expires_in'] ?? null;
                $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;
                $scopes = PinterestApiClient::parseGrantedScopes($tokenData['scope'] ?? null);

                $meResponse = PinterestApiClient::api($accessToken)
                    ->get(PinterestApiClient::apiUrl() . '/' . PinterestApiClient::version() . '/user_account');

                if ($meResponse->failed()) {
                    Log::error('Pinterest user_account failed', [
                        'status' => $meResponse->status(),
                        'body' => $meResponse->json() ?? $meResponse->body(),
                        'granted_scopes' => $scopes,
                    ]);
                    throw new \Exception(
                        'User profile fetch failed: ' . PinterestApiClient::errorMessage($meResponse, 'Missing user_accounts:read scope or invalid token')
                    );
                }

                $meData = $meResponse->json();
                $providerUserId = $meData['username'] ?? uniqid('pinterest_', true);
                $name = $meData['business_name'] ?? $meData['username'] ?? 'Pinterest User';
                $username = $meData['username'] ?? 'pinterest_user';
                $avatarUrl = $meData['profile_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($name);
            }

            // Create/Update SocialAccount
            $account = SocialAccount::updateOrCreate([
                'user_id' => auth()->id(),
                'provider' => $providerType,
                'provider_user_id' => $providerUserId,
            ], [
                'name' => $name,
                'username' => $username,
                'avatar_url' => $avatarUrl,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => $expiresAt,
                'scopes' => $scopes,
                'status' => SocialAccountStatus::ACTIVE,
            ]);

            $account->update([
                'metadata' => array_merge($account->metadata ?? [], [
                    "{$provider}_api_env" => SettingsService::platformUsesSandbox($provider) ? 'sandbox' : 'production',
                ]),
            ]);

            // Facebook / Instagram: auto-sync first page/account. Pinterest: pick a board.
            if ($provider === 'pinterest') {
                return redirect()->route('accounts.pinterest.boards', $account->id)
                    ->with('status', "Connected as {$name}. Select a board to publish to.");
            }

            $this->manager->syncDestinations($account);
            $account->refresh();

            if (!$account->primaryDestination()) {
                return redirect()->route('accounts.index')
                    ->with('error', $account->last_error ?? 'Connected, but no publish destination was synced.');
            }

            $account = $account->fresh();
            $missingScopes = $account->missingPublishScopes();
            $redirect = redirect()->route('accounts.index')
                ->with('status', "Successfully connected live {$providerType->name} profile: {$name}!");

            if ($missingScopes !== []) {
                $requestedScopes = SettingsService::oauthScopesFor($provider);
                $notInRequest = array_values(array_diff($missingScopes, $requestedScopes));

                if ($notInRequest !== []) {
                    $redirect->with(
                        'warning',
                        'Facebook is connected for listing Pages, but publishing is not configured yet. '
                        . 'Set FACEBOOK_REQUEST_PUBLISH_SCOPES=true and add pages_manage_posts to FACEBOOK_OAUTH_SCOPES in .env, '
                        . 'run php artisan config:clear, then disconnect and reconnect.'
                    );
                } else {
                    $redirect->with(
                        'warning',
                        'Facebook connected, but Meta did not grant: ' . implode(', ', $missingScopes) . '. '
                        . 'In Meta App Dashboard → App Review → Permissions and Features, add pages_manage_posts to your app. '
                        . 'Your Facebook user must be an Admin/Developer on the app (Development mode). '
                        . 'Then disconnect and reconnect, and approve all Page permissions in the Facebook dialog.'
                    );
                }
            }

            return $redirect;

        } catch (\Exception $e) {
            Log::error("OAuth callback failed for provider {$provider}: " . $e->getMessage());
            return redirect()->route('accounts.index')
                ->with('error', "Authentication failed: " . $e->getMessage());
        }
    }

    /**
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    protected function fetchMetaGrantedScopes(string $apiUrl, string $version, string $accessToken, array $fallback): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$apiUrl}/{$version}/me/permissions");

        if ($response->failed()) {
            return $fallback;
        }

        $granted = [];
        foreach ($response->json()['data'] ?? [] as $perm) {
            if (($perm['status'] ?? '') === 'granted') {
                $granted[] = $perm['permission'];
            }
        }

        return $granted !== [] ? $granted : $fallback;
    }
}
