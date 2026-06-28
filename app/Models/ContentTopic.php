<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentTopic extends Model
{
    protected $fillable = [
        'content_category_id',
        'title',
        'topic_type',
        'is_active',
        'times_used',
        'last_used_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'content_category_id');
    }

    public function runItems(): HasMany
    {
        return $this->hasMany(AutomationRunItem::class);
    }

    public function getLinkUrlAttribute(): ?string
    {
        $url = $this->metadata['link_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
