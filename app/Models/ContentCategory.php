<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'niche_prompt',
        'safety_rules',
        'audience',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(ContentTopic::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AutomationCampaign::class);
    }
}
