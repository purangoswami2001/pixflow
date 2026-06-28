<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    protected $fillable = [
        'automation_campaign_id',
        'status',
        'planned_count',
        'created_count',
        'failed_count',
        'started_at',
        'finished_at',
        'log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'log' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AutomationCampaign::class, 'automation_campaign_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AutomationRunItem::class);
    }
}
