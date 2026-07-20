<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const THEME_LIGHT = 'light';

    public const THEME_DARK = 'dark';

    public const THEME_SYSTEM = 'system';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function themePreference(): string
    {
        $theme = strtolower((string) ($this->theme ?? self::THEME_LIGHT));

        return in_array($theme, [self::THEME_LIGHT, self::THEME_DARK, self::THEME_SYSTEM], true)
            ? $theme
            : self::THEME_LIGHT;
    }

    /**
     * Resolve light/dark for rendering. When preference is system, pass the OS dark flag if known.
     */
    public function resolvedTheme(?bool $systemIsDark = null): string
    {
        $pref = $this->themePreference();

        if ($pref === self::THEME_SYSTEM) {
            return $systemIsDark ? self::THEME_DARK : self::THEME_LIGHT;
        }

        return $pref;
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function roleLabel(): string
    {
        if ($this->isAdmin()) {
            return 'Administrator';
        }

        if ($this->hasRole('creator')) {
            return 'Creator';
        }

        return 'User';
    }

    public function roleBadgeClass(): string
    {
        if ($this->isAdmin()) {
            return 'pp-sidebar-role--admin';
        }

        if ($this->hasRole('creator')) {
            return 'pp-sidebar-role--creator';
        }

        return 'pp-sidebar-role--user';
    }
}
