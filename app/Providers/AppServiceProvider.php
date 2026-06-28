<?php

namespace App\Providers;

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
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            \Illuminate\Support\Facades\URL::forceRootUrl($appUrl);
        }

        if (str_starts_with($appUrl, 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Gate::define('manage-system', function ($user) {
            return $user->isAdmin();
        });
    }
}
