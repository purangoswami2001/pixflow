<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\OrganizationContext;

#[Fillable(['name', 'email', 'password', 'theme', 'current_organization_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const THEME_LIGHT = 'light';

    public const THEME_DARK = 'dark';

    public const THEME_SYSTEM = 'system';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
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

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps()
            ->using(OrganizationMembership::class);
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
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

    public function membershipFor(?Organization $organization = null): ?OrganizationMembership
    {
        $organizationId = $organization?->id
            ?? app(OrganizationContext::class)->id()
            ?? $this->current_organization_id;

        if (! $organizationId) {
            return null;
        }

        return $this->organizationMemberships
            ->first(fn (OrganizationMembership $membership) => (int) $membership->organization_id === (int) $organizationId
                && $membership->status === OrganizationMembership::STATUS_ACTIVE)
            ?? OrganizationMembership::query()
                ->where('user_id', $this->id)
                ->where('organization_id', $organizationId)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->first();
    }

    public function organizationRole(?Organization $organization = null): ?string
    {
        return $this->membershipFor($organization)?->role;
    }

    /**
     * Organization owner/admin for the active firm (or optional firm).
     * Platform super-admins are separate and do not imply tenant admin rights.
     */
    public function isAdmin(?Organization $organization = null): bool
    {
        return $this->membershipFor($organization)?->isAdmin() ?? false;
    }

    public function isOrganizationOwner(?Organization $organization = null): bool
    {
        return $this->membershipFor($organization)?->isOwner() ?? false;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function belongsToOrganization(Organization|int $organization): bool
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return OrganizationMembership::query()
            ->where('user_id', $this->id)
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->exists();
    }

    public function roleLabel(?Organization $organization = null): string
    {
        $role = $this->organizationRole($organization);

        if ($role) {
            return OrganizationMembership::roleLabel($role);
        }

        if ($this->isPlatformAdmin()) {
            return 'Platform Admin';
        }

        return 'User';
    }

    public function roleBadgeClass(?Organization $organization = null): string
    {
        $role = $this->organizationRole($organization);

        return match ($role) {
            OrganizationMembership::ROLE_OWNER,
            OrganizationMembership::ROLE_ADMIN => 'pp-sidebar-role--admin',
            OrganizationMembership::ROLE_CREATOR => 'pp-sidebar-role--creator',
            default => 'pp-sidebar-role--user',
        };
    }
}
