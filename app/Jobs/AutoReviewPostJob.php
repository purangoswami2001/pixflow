<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Automation\AutoReviewAgentService;
use App\Services\Automation\AutoReviewLogger;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoReviewPostJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function __construct(
        public int $postId
    ) {}

    public function uniqueId(): string
    {
        return 'auto-review-post-'.$this->postId;
    }

    public function handle(AutoReviewAgentService $agent): void
    {
        if (! $agent->enabled()) {
            AutoReviewLogger::info('Job skipped — auto-review disabled', ['post_id' => $this->postId]);

            return;
        }

        $post = Post::with(['targets.mediaAsset', 'targets'])->find($this->postId);
        if (! $post) {
            AutoReviewLogger::warning('Post not found for auto-review', ['post_id' => $this->postId]);

            return;
        }

        if (! $agent->isEligibleForAutoReview($post)) {
            AutoReviewLogger::info('Job skipped — not an automation pipeline post', [
                'post_id' => $this->postId,
                'source' => $post->metadata['source'] ?? null,
                'reason' => $agent->ineligibilityReason($post),
            ]);

            return;
        }

        try {
            $result = $agent->reviewPost($post);
            AutoReviewLogger::info('Job finished', [
                'post_id' => $this->postId,
                'skipped' => $result['skipped'] ?? false,
                'decision' => $result['decision'] ?? null,
                'reason' => $result['reason'] ?? null,
            ]);
        } catch (\Throwable $e) {
            AutoReviewLogger::error('Job failed', [
                'post_id' => $this->postId,
                'error' => $e->getMessage(),
            ]);

            try {
                app(\App\Services\Notifications\InAppNotificationService::class)->notifyAdmins(
                    'auto_review_failed',
                    'Agent review failed',
                    'AutoReview could not finish post #'.$this->postId.': '.$e->getMessage(),
                    'danger',
                    route('admin.automation.auto-review'),
                    ['post_id' => $this->postId, 'error' => $e->getMessage()],
                );
            } catch (\Throwable $notifyError) {
                report($notifyError);
            }

            throw $e;
        }
    }
}
