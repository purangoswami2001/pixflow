<?php

namespace App\Models;

use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'provider',
        'provider_user_id',
        'name',
        'username',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'metadata',
        'status',
        'last_error',
    ];

    protected $casts = [
        'provider' => ProviderType::class,
        'status' => SocialAccountStatus::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'scopes' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(SocialDestination::class);
    }

    public function primaryDestination(): ?SocialDestination
    {
        return $this->destinations()->where('status', 'active')->orderBy('id')->first();
    }

    public function activeDestinations()
    {
        return $this->destinations()->where('status', 'active')->orderBy('name');
    }

    public function isReadyForPublishing(): bool
    {
        return $this->status === SocialAccountStatus::ACTIVE
            && $this->activeDestinations()->exists();
    }

    public function scopeConnected($query)
    {
        return $query->where('status', '!=', SocialAccountStatus::DISCONNECTED);
    }

    public function isDisconnected(): bool
    {
        return $this->status === SocialAccountStatus::DISCONNECTED;
    }

    public function publishLabel(?SocialDestination $destination = null): string
    {
        $destination ??= $this->primaryDestination();

        if ($this->provider === ProviderType::PINTEREST && $destination) {
            return $this->name . ' → ' . $destination->name;
        }

        return $this->displayHandle();
    }

    public function displayHandle(): string
    {
        if ($this->username) {
            return '@' . ltrim($this->username, '@');
        }

        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function missingPublishScopes(): array
    {
        $required = config("social-platforms.platforms.{$this->provider->value}.publish_scopes", []);
        $granted = $this->scopes ?? [];

        return array_values(array_diff($required, $granted));
    }

    public function canPublish(): bool
    {
        return $this->missingPublishScopes() === [];
    }
}
