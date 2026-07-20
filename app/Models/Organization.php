<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'ai_enabled',
        'automation_enabled',
        'automation_initialized_at',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'automation_enabled' => 'boolean',
        'automation_initialized_at' => 'datetime',
    ];

    public function aiEnabled(): bool
    {
        return (bool) $this->ai_enabled;
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (blank($organization->slug)) {
                $organization->slug = static::uniqueSlug($organization->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $i = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps()
            ->using(OrganizationMembership::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function automationCampaigns(): HasMany
    {
        return $this->hasMany(AutomationCampaign::class);
    }

    public function activeAdmins()
    {
        return $this->memberships()
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->whereIn('role', [
                OrganizationMembership::ROLE_OWNER,
                OrganizationMembership::ROLE_ADMIN,
            ]);
    }
}
