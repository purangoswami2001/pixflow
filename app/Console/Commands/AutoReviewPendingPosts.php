<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Jobs\AutoReviewPostJob;
use App\Models\Post;
use App\Services\Automation\AutoReviewAgentService;
use App\Services\Automation\AutoReviewLogger;
use Illuminate\Console\Command;

class AutoReviewPendingPosts extends Command
{
    protected $signature = 'automation:auto-review-pending
                            {--limit=20 : Max posts to review this run}
                            {--sync : Run reviews synchronously instead of queueing}
                            {--post= : Review a single post id}';

    protected $description = 'Auto-review pending automation posts (approve / revision / reject)';

    public function handle(AutoReviewAgentService $agent): int
    {
        if (! $agent->enabled()) {
            $this->warn('AUTO_REVIEW_ENABLED is false. Enable it in .env to run reviews.');

            return self::SUCCESS;
        }

        $postId = $this->option('post');
        if ($postId) {
            $post = Post::with(['targets.mediaAsset', 'organization'])->find($postId);
            if (! $post) {
                $this->error("Post #{$postId} not found.");

                return self::FAILURE;
            }

            if (! $post->organization?->ai_enabled) {
                $this->warn('AI Mode is disabled for this post\'s firm — skipping.');

                return self::SUCCESS;
            }

            if (! $agent->isEligibleForAutoReview($post)) {
                $this->warn($agent->ineligibilityReason($post));

                return self::SUCCESS;
            }

            return $this->processOne($agent, $post);
        }

        $limit = max(1, (int) $this->option('limit'));
        $query = Post::query()
            ->with(['targets.mediaAsset'])
            ->whereHas('organization', fn ($q) => $q->where('ai_enabled', true))
            ->where('status', PostStatus::PENDING_APPROVAL)
            ->where('metadata->source', 'automation')
            ->whereNotNull('metadata->run_id')
            ->whereNotNull('metadata->run_item_id')
            ->where(function ($q) {
                $q->whereNull('metadata->auto_review->decision')
                    ->orWhere('metadata->auto_review->decision', '');
            })
            ->orderBy('id')
            ->limit($limit);

        $posts = $query->get();
        if ($posts->isEmpty()) {
            $this->info('No pending automation posts need auto-review.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} post(s) to auto-review.");
        $sync = (bool) $this->option('sync');

        foreach ($posts as $post) {
            if ($sync) {
                $this->processOne($agent, $post);
            } else {
                AutoReviewPostJob::dispatch($post->id);
                $this->line("Queued auto-review for post #{$post->id}");
                AutoReviewLogger::info('Queued pending scan item', ['post_id' => $post->id]);
            }
        }

        return self::SUCCESS;
    }

    protected function processOne(AutoReviewAgentService $agent, Post $post): int
    {
        try {
            $result = $agent->reviewPost($post);
            if (! empty($result['skipped'])) {
                $this->line("Skipped #{$post->id}: ".($result['reason'] ?? 'n/a'));

                return self::SUCCESS;
            }

            $this->info("Post #{$post->id} → {$result['decision']} (score ".($result['report']['overall_score'] ?? '?').')');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed #{$post->id}: ".$e->getMessage());
            AutoReviewLogger::error('Command review failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
