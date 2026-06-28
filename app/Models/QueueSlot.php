<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueSlot extends Model
{
    protected $fillable = [
        'social_destination_id',
        'day_of_week',
        'time',
        'timezone',
        'posts_per_slot',
    ];

    protected $casts = [
        'posts_per_slot' => 'integer',
    ];

    public function socialDestination(): BelongsTo
    {
        return $this->belongsTo(SocialDestination::class);
    }
}
