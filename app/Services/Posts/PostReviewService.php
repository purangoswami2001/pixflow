<?php

namespace App\Services\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use InvalidArgumentException;

class PostReviewService
{
    public function __construct(
        protected QueueScheduleCalculatorService $queueSchedule,
    ) {}

    /**
     * Apply an approval decision to a pending post.
     *
     * @param  array<string, mixed>  $context Extra metadata merged into posts.metadata.auto_review
     * @return array{post: Post, message: string}
     */
    public function apply(
        Post $post,
        string $action,
        ?string $adminNote = null,
        string $reviewedBy = 'admin',
        array $context = [],
    ): array {
        if ($post->status !== PostStatus::PENDING_APPROVAL) {
            throw new InvalidArgumentException('Only posts pending approval can be reviewed.');
        }

        if (! in_array($action, ['approve', 'reject', 'revision'], true)) {
            throw new InvalidArgumentException('Invalid review action.');
        }

        $metadata = $post->metadata ?? [];
        $reviewStamp = array_merge($context, [
            'decision' => $action,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now()->toIso8601String(),
            'admin_note' => $adminNote,
        ]);

        // Only the automation agent writes auto_review. Admin/manual approvals use admin_review.
        if ($reviewedBy === 'auto-review-agent') {
            $metadata['auto_review'] = array_merge($metadata['auto_review'] ?? [], $reviewStamp);
        } else {
            $metadata['admin_review'] = array_merge($metadata['admin_review'] ?? [], $reviewStamp);
        }

        if ($action === 'approve') {
            $post->update([
                'status' => PostStatus::APPROVED,
                'admin_note' => $adminNote,
                'metadata' => $metadata,
            ]);
            $post->targets()->update(['status' => PostStatus::SCHEDULED]);
            $this->queueSchedule->refreshDestinationSchedule(
                $post->targets()->pluck('social_destination_id')->all()
            );

            $this->notifyCreatorOutcome($post, 'approve', $adminNote, $reviewedBy);

            return [
                'post' => $post->fresh(['targets']),
                'message' => 'Post approved and scheduled in the publishing queue.',
            ];
        }

        if ($action === 'reject') {
            $post->update([
                'status' => PostStatus::REJECTED,
                'admin_note' => $adminNote,
                'metadata' => $metadata,
            ]);
            $post->targets()->update(['status' => PostStatus::CANCELLED]);

            $this->notifyCreatorOutcome($post, 'reject', $adminNote, $reviewedBy);

            return [
                'post' => $post->fresh(['targets']),
                'message' => 'Post rejected.',
            ];
        }

        $post->update([
            'status' => PostStatus::NEEDS_REVISION,
            'admin_note' => $adminNote,
            'metadata' => $metadata,
        ]);
        $post->targets()->update(['status' => PostStatus::DRAFT]);

        $this->notifyCreatorOutcome($post, 'revision', $adminNote, $reviewedBy);

        return [
            'post' => $post->fresh(['targets']),
            'message' => 'Post returned for revision.',
        ];
    }

    protected function notifyCreatorOutcome(Post $post, string $action, ?string $adminNote, string $reviewedBy): void
    {
        try {
            $notifications = app(\App\Services\Notifications\InAppNotificationService::class);
            $title = $post->title ?: ('Post #'.$post->id);
            $byAgent = $reviewedBy === 'auto-review-agent';

            if ($byAgent) {
                $decisionLabel = match ($action) {
                    'approve' => 'approved',
                    'reject' => 'rejected',
                    default => 'sent for revision',
                };
                $notifications->notifyAdmins(
                    'auto_review_success',
                    'Agent review '.$decisionLabel,
                    "AutoReview {$decisionLabel} \"{$title}\"".($adminNote ? ' — '.$adminNote : '.'),
                    $action === 'approve' ? 'success' : ($action === 'reject' ? 'danger' : 'warning'),
                    route('admin.automation.auto-review.show', $post->id),
                    ['post_id' => $post->id, 'decision' => $action],
                    $post->organization_id,
                );
            }

            // Creator/team member: approval outcomes for their own posts
            if ($post->user_id) {
                $owner = $post->user;
                if ($owner && ! $owner->isAdmin()) {
                    if ($action === 'approve') {
                        $notifications->notifyUser(
                            $owner,
                            'post_approved',
                            'Post approved',
                            "\"{$title}\" was approved and queued to publish.",
                            'success',
                            route('posts.edit', $post->id),
                            ['post_id' => $post->id],
                        );
                    } elseif ($action === 'reject') {
                        $notifications->notifyUser(
                            $owner,
                            'post_rejected',
                            'Post rejected',
                            "\"{$title}\" was rejected.".($adminNote ? ' Note: '.$adminNote : ''),
                            'danger',
                            route('posts.index', ['status' => 'rejected']),
                            ['post_id' => $post->id],
                        );
                    } else {
                        $notifications->notifyUser(
                            $owner,
                            'post_needs_revision',
                            'Revision requested',
                            "\"{$title}\" needs changes.".($adminNote ? ' Note: '.$adminNote : ''),
                            'warning',
                            route('posts.edit', $post->id),
                            ['post_id' => $post->id],
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            // Never break review flow for notifications
            report($e);
        }
    }
}
