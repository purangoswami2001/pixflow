<?php

namespace App\Models;

use App\Enums\DestinationType;
use App\Enums\ProviderType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialDestination extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'social_account_id',
        'provider',
        'destination_type',
        'provider_destination_id',
        'name',
        'avatar_url',
        'metadata',
        'status',
    ];

    protected $casts = [
        'provider' => ProviderType::class,
        'destination_type' => DestinationType::class,
        'metadata' => 'array',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
