<?php

namespace App\Providers;

use App\Models\OrganizationMembership;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Policies\OrganizationMembershipPolicy;
use App\Policies\PostPolicy;
use App\Policies\SocialAccountPolicy;
use App\Support\OrganizationContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Ai\Cursor\CursorCloudAgentsApi::class);
        $this->app->singleton(\App\Services\Ai\CursorApiClient::class);
        $this->app->singleton(OrganizationContext::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        $this->configureDynamicRootUrl();

        // Keep pagination links on the same subdirectory base (e.g. /pixflow/public via ngrok).
        Paginator::currentPathResolver(function () {
            return url()->current();
        });

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);
        Gate::policy(OrganizationMembership::class, OrganizationMembershipPolicy::class);

        Gate::define('manage-system', function ($user) {
            return $user->isPlatformAdmin();
        });

        Gate::define('manage-organization', function ($user) {
            return $user->isAdmin();
        });
    }

    /**
     * Prefer the live request host so local vhosts (pixflow.local.in) and ngrok
     * both generate correct asset()/route() URLs. APP_URL alone breaks CSS when
     * it points at a different host than the one in the browser.
     */
    protected function configureDynamicRootUrl(): void
    {
        $request = request();
        $configured = rtrim((string) config('app.url'), '/');
        $root = $configured;

        if ($request && $request->getHttpHost()) {
            $root = $request->getSchemeAndHttpHost();

            $scriptName = str_replace('\\', '/', (string) $request->server->get('SCRIPT_NAME', ''));
            $scriptDir = $scriptName !== '' ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/') : '';

            if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.') {
                $root .= $scriptDir;
            }
        }

        if ($root === '') {
            return;
        }

        config([
            'app.url' => $root,
            'app.asset_url' => $root,
        ]);

        \Illuminate\Support\Facades\URL::forceRootUrl($root);

        if (str_starts_with($root, 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
