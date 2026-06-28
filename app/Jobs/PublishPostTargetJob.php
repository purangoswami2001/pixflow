<?php

namespace App\Jobs;

use App\Models\PostTarget;
use App\Models\PublishLog;
use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Services\Social\FacebookPublisher;
use App\Services\Social\InstagramPublisher;
use App\Services\Social\PinterestPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublishPostTargetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // 1 minute delay on retry

    protected int $targetId;

    protected bool $immediate;

    /**
     * Create a new job instance.
     */
    public function __construct(int $targetId, bool $immediate = false)
    {
        $this->targetId = $targetId;
        $this->immediate = $immediate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $target = PostTarget::with(['post', 'socialAccount', 'socialDestination'])->find($this->targetId);

        if (!$target || in_array($target->status, [PostStatus::PUBLISHED, PostStatus::CANCELLED])) {
            return;
        }

        // Prevent duplicate execution using Cache Lock
        $lockKey = "publishing_target_{$target->id}";
        $lock = Cache::lock($lockKey, 300); // 5 minutes lock

        if (!$lock->get()) {
            Log::warning("Publishing target {$target->id} is already being processed. Aborting job.");
            return;
        }

        try {
            $target->update(['status' => PostStatus::PUBLISHING]);
            
            // Factory resolver for publisher
            $publisher = match ($target->provider->value) {
                'facebook' => new FacebookPublisher(),
                'instagram' => new InstagramPublisher(),
                'pinterest' => new PinterestPublisher(),
                default => throw new \InvalidArgumentException("Unsupported provider '{$target->provider->value}'"),
            };

            Log::info("Starting publication for Target ID: {$target->id} on {$target->provider->value}");
            $result = $publisher->publish($target);

            // Log response inside Database Audit logs
            PublishLog::create([
                'post_target_id' => $target->id,
                'provider' => $target->provider,
                'status' => $result['status'] === 'success' ? 'success' : 'failed',
                'request_payload' => $result['request_payload'] ?? [
                    'form' => $target->platform_payload,
                    'note' => 'Publisher did not return API request details',
                ],
                'response_payload' => $result['response_payload'] ?? $result['metadata'] ?? [],
                'error_message' => $result['error'] ?? null,
            ]);

            if ($result['status'] === 'success') {
                $target->update([
                    'status' => PostStatus::PUBLISHED,
                    'provider_post_id' => $result['provider_post_id'],
                    'provider_post_url' => $result['provider_post_url'],
                    'published_at' => now(),
                    'error_message' => null,
                ]);

                Log::info("Successfully published Target ID: {$target->id}");

                // Check if all targets are completed for this post
                $this->checkAndCompletePost($target->post);

            } elseif ($result['status'] === 'expired') {
                // Token Expired
                $target->socialAccount->update([
                    'status' => SocialAccountStatus::EXPIRED,
                    'last_error' => $result['error']
                ]);

                $target->update([
                    'status' => PostStatus::FAILED,
                    'error_message' => $result['error']
                ]);

                $target->post->update(['status' => PostStatus::FAILED]);
                Log::error("Access token expired for Account ID: {$target->social_account_id}. Target {$target->id} failed.");

            } else {
                // General Failures
                $target->increment('attempts_count');
                $target->update([
                    'error_message' => $result['error']
                ]);

                if ($this->immediate || $target->attempts_count >= $this->tries) {
                    $target->update(['status' => PostStatus::FAILED]);
                    $target->post->update(['status' => PostStatus::FAILED]);
                    Log::error("Publication target {$target->id} failed" . ($this->immediate ? ' (immediate publish).' : " permanently after {$this->tries} attempts."));
                } else {
                    // Re-queue with a delay
                    $target->update(['status' => PostStatus::SCHEDULED]);
                    $this->release($this->backoff);
                    Log::warning("Publication target {$target->id} failed. Retrying in {$this->backoff} seconds.");
                }
            }

        } catch (\Exception $e) {
            Log::error("Exception in PublishPostTargetJob: " . $e->getMessage());
            $target->update([
                'status' => PostStatus::FAILED,
                'error_message' => $e->getMessage()
            ]);
            $target->post->update(['status' => PostStatus::FAILED]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Check if all targets are published, if so mark the post as published.
     */
    protected function checkAndCompletePost($post): void
    {
        $allTargetsCount = $post->targets()->count();
        $publishedTargetsCount = $post->targets()->where('status', PostStatus::PUBLISHED)->count();

        if ($allTargetsCount === $publishedTargetsCount) {
            $post->update(['status' => PostStatus::PUBLISHED]);

            // Handle weekly recurring reschedule if present
            if ($post->recurringSchedule) {
                app(\App\Services\Posts\PostSchedulerService::class)->rescheduleRecurring($post);
            }
        }
    }
}
