<?php

namespace App\Models;

use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_target_id',
        'provider',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'provider' => ProviderType::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }
}
