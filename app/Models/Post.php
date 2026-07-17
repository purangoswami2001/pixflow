<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'caption',
        'hashtags',
        'tags',
        'metadata',
        'admin_note',
    ];

    protected $casts = [
        'status' => PostStatus::class,
        'hashtags' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    public function recurringSchedule(): HasOne
    {
        return $this->hasOne(RecurringSchedule::class);
    }

    public function canBeEditedBy(User $user): bool
    {
        if (in_array($this->status, [PostStatus::PUBLISHED, PostStatus::PUBLISHING], true)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->user_id === $user->id
            && in_array($this->status, [
                PostStatus::DRAFT,
                PostStatus::PENDING_APPROVAL,
                PostStatus::NEEDS_REVISION,
            ], true);
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->user_id === $user->id
            && in_array($this->status, [PostStatus::DRAFT, PostStatus::PENDING_APPROVAL], true);
    }
}
