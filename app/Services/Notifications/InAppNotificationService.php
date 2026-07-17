<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class InAppNotificationService
{
    /**
     * Audience: admin sees ops events; creators only see approval outcomes on their posts.
     */
    public const ADMIN_TYPES = [
        'post_published',
        'post_publish_failed',
        'post_created_ai',
        'post_pending_approval',
        'pipeline_success',
        'pipeline_failed',
        'auto_review_success',
        'auto_review_failed',
    ];

    public const CREATOR_TYPES = [
        'post_approved',
        'post_rejected',
        'post_needs_revision',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(
        User|int $user,
        string $type,
        string $title,
        ?string $body = null,
        string $level = 'info',
        ?string $link = null,
        array $data = [],
    ): InAppNotification {
        $userId = $user instanceof User ? $user->id : $user;

        return InAppNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'level' => $level,
            'link' => $link,
            'data' => $data ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, InAppNotification>
     */
    public function notifyAdmins(
        string $type,
        string $title,
        ?string $body = null,
        string $level = 'info',
        ?string $link = null,
        array $data = [],
    ): Collection {
        $admins = User::role('admin')->get(['id']);

        return $admins->map(fn (User $admin) => $this->notifyUser(
            $admin,
            $type,
            $title,
            $body,
            $level,
            $link,
            $data,
        ));
    }

    /**
     * @return list<string>
     */
    public function typesFor(User $user): array
    {
        return $user->isAdmin()
            ? array_values(array_unique(array_merge(self::ADMIN_TYPES, self::CREATOR_TYPES)))
            : self::CREATOR_TYPES;
    }

    public function unreadCount(User $user): int
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $this->typesFor($user))
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return Collection<int, InAppNotification>
     */
    public function recentFor(User $user, int $limit = 10): Collection
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $this->typesFor($user))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function paginateFor(User $user, int $perPage = 30)
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $this->typesFor($user))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function markAllRead(User $user): int
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $this->typesFor($user))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function iconFor(string $type): string
    {
        return match ($type) {
            'post_published', 'pipeline_success', 'auto_review_success', 'post_approved' => 'check_circle',
            'post_publish_failed', 'pipeline_failed', 'auto_review_failed', 'post_rejected' => 'error',
            'post_created_ai' => 'smart_toy',
            'post_pending_approval' => 'rate_review',
            'post_needs_revision' => 'edit_note',
            default => 'notifications',
        };
    }
}
