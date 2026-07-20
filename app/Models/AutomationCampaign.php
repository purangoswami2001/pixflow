<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationCampaign extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'content_category_id',
        'is_active',
        'posts_per_day',
        'platforms',
        'platform_accounts',
        'platform_counts',
        'platform_image_presets',
        'platform_image_styles',
        'promotional_ratio',
        'default_timezone',
        'generation_time',
        'last_run_at',
        'next_run_at',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'platforms' => 'array',
        'platform_accounts' => 'array',
        'platform_counts' => 'array',
        'platform_image_presets' => 'array',
        'platform_image_styles' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'content_category_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
