<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRunItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'automation_run_id',
        'content_topic_id',
        'platform',
        'topic_type',
        'status',
        'post_id',
        'design_prompt',
        'image_provider',
        'text_provider',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ContentTopic::class, 'content_topic_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
