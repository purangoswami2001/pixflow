<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'platform',
        'step',
        'name',
        'template',
        'is_default',
        'is_active',
        'version',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
