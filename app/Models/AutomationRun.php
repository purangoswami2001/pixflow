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

    /**
     * Recompute created/failed counters and terminal status from current item rows.
     */
    public function syncCountsFromItems(bool $finalize = false): void
    {
        $this->loadMissing('items');

        $created = $this->items->where('status', 'post_created')->count();
        $failed = $this->items->where('status', 'failed')->count();
        $pending = $this->items->whereNotIn('status', ['post_created', 'failed'])->count();

        $payload = [
            'created_count' => $created,
            'failed_count' => $failed,
        ];

        if ($finalize || $pending === 0) {
            if ($pending > 0) {
                $payload['status'] = 'running';
                $payload['finished_at'] = null;
            } elseif ($failed > 0 && $created > 0) {
                $payload['status'] = 'partial';
                $payload['finished_at'] = $this->finished_at ?? now();
            } elseif ($failed > 0) {
                $payload['status'] = 'failed';
                $payload['finished_at'] = $this->finished_at ?? now();
            } else {
                $payload['status'] = 'completed';
                $payload['finished_at'] = $this->finished_at ?? now();
            }
        }

        $this->forceFill($payload)->save();
    }
}
