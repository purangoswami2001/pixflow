<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
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
        ?int $organizationId = null,
    ): InAppNotification {
        $userId = $user instanceof User ? $user->id : $user;
        $organizationId = $organizationId ?? app(OrganizationContext::class)->id();

        return InAppNotification::create([
            'user_id' => $userId,
            'organization_id' => $organizationId,
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
        ?int $organizationId = null,
    ): Collection {
        $organizationId = $organizationId ?? app(OrganizationContext::class)->id();

        if (! $organizationId) {
            return collect();
        }

        $adminUserIds = OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->whereIn('role', [
                OrganizationMembership::ROLE_OWNER,
                OrganizationMembership::ROLE_ADMIN,
            ])
            ->pluck('user_id');

        if ($adminUserIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $adminUserIds)
            ->get(['id'])
            ->map(fn (User $admin) => $this->notifyUser(
                $admin,
                $type,
                $title,
                $body,
                $level,
                $link,
                $data,
                $organizationId,
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
        return $this->queryForUser($user)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return Collection<int, InAppNotification>
     */
    public function recentFor(User $user, int $limit = 10): Collection
    {
        return $this->queryForUser($user)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function paginateFor(User $user, int $perPage = 30)
    {
        return $this->queryForUser($user)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function markAllRead(User $user): int
    {
        return $this->queryForUser($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    protected function queryForUser(User $user)
    {
        $query = InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $this->typesFor($user));

        $organizationId = app(OrganizationContext::class)->id() ?? $user->current_organization_id;
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query;
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
