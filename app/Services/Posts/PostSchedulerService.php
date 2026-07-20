<?php

namespace App\Services\Posts;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\RecurringSchedule;
use App\Models\User;
use App\Enums\PostStatus;
use App\Enums\PostType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Posts\PostMediaArchiveService;
use App\Support\OrganizationContext;

class PostSchedulerService
{
    public function __construct(
        protected PostMediaArchiveService $postMediaArchive
    ) {
    }

    /**
     * Create a scheduled post.
     */
    public function createPost(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data) {
            $organizationId = $data['organization_id']
                ?? $user->current_organization_id
                ?? app(OrganizationContext::class)->id();

            if (! $organizationId) {
                throw new \RuntimeException('Cannot create a post without an organization.');
            }

            $post = Post::create([
                'user_id' => $user->id,
                'organization_id' => $organizationId,
                'title' => $data['title'],
                'caption' => $data['caption'] ?? null,
                'status' => $data['is_draft'] ? PostStatus::DRAFT : ($data['status'] ?? PostStatus::SCHEDULED),
                'hashtags' => $data['hashtags'] ?? [],
                'tags' => $data['tags'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Add targets
            foreach ($data['targets'] as $targetData) {
                // Determine scheduled date
                $scheduledAt = null;
                if (!$data['is_draft']) {
                    if ($data['schedule_type'] === 'now') {
                        $scheduledAt = now();
                    } elseif (isset($targetData['scheduled_at'])) {
                        $scheduledAt = Carbon::parse($targetData['scheduled_at'], $targetData['timezone'] ?? 'UTC')
                            ->utc();
                    }
                }

                // If post is APPROVED, targets should be SCHEDULED
                $targetStatus = $post->status === PostStatus::APPROVED ? PostStatus::SCHEDULED : $post->status;

                $mediaAssetId = $targetData['media_asset_id'] ?? null;

                $target = PostTarget::create([
                    'post_id' => $post->id,
                    'organization_id' => $organizationId,
                    'social_account_id' => $targetData['social_account_id'],
                    'social_destination_id' => $targetData['social_destination_id'],
                    'provider' => $targetData['provider'],
                    'post_type' => $targetData['post_type'],
                    'media_asset_id' => null,
                    'platform_media_path' => null,
                    'platform_payload' => $targetData['platform_payload'] ?? [],
                    'scheduled_at' => $scheduledAt,
                    'timezone' => $targetData['timezone'] ?? 'UTC',
                    'status' => $targetStatus,
                ]);

                if ($mediaAssetId && ($asset = MediaAsset::query()
                    ->where('organization_id', $organizationId)
                    ->find($mediaAssetId))) {
                    $this->postMediaArchive->copyFromLibrary($target, $asset);
                }
            }

            // Recurring Weekly Schedule
            if (isset($data['recurring']) && $data['recurring']['enabled']) {
                $rec = $data['recurring'];
                
                $schedule = RecurringSchedule::create([
                    'post_id' => $post->id,
                    'frequency' => 'weekly',
                    'days_of_week' => $rec['days_of_week'], // Array of integers [0-6] (0=Sunday)
                    'time' => $rec['time'], // string "14:30"
                    'timezone' => $rec['timezone'] ?? 'UTC',
                    'starts_at' => Carbon::parse($rec['starts_at'])->startOfDay(),
                    'ends_at' => isset($rec['ends_at']) ? Carbon::parse($rec['ends_at'])->endOfDay() : null,
                    'status' => 'active',
                ]);

                $nextRun = $this->calculateNextRun($schedule);
                $schedule->update(['next_run_at' => $nextRun]);

                // Update targets' scheduled times to match the first recurring date if it exists
                if ($nextRun) {
                    foreach ($post->targets as $target) {
                        $target->update([
                            'scheduled_at' => $nextRun,
                            'status' => PostStatus::SCHEDULED
                        ]);
                    }
                    $post->update(['status' => PostStatus::SCHEDULED]);
                }
            }

            return $post;
        });
    }

    /**
     * Calculate next run time for recurring schedule.
     */
    public function calculateNextRun(RecurringSchedule $schedule): ?Carbon
    {
        if ($schedule->status !== 'active') {
            return null;
        }

        $now = now()->timezone($schedule->timezone);
        $timeParts = explode(':', $schedule->time);
        $hour = (int)$timeParts[0];
        $minute = (int)$timeParts[1];

        $startDate = $schedule->starts_at->timezone($schedule->timezone);
        $baseDate = $now->gt($startDate) ? $now : $startDate;

        $nextRun = null;
        $daysOfWeek = $schedule->days_of_week; // e.g. [1, 3, 5]

        // Look ahead 14 days to find the next valid occurrence
        for ($i = 0; $i < 14; $i++) {
            $candidate = $baseDate->copy()->addDays($i)->hour($hour)->minute($minute)->second(0);
            
            // Candidate must be in future relative to now, and day of week must match
            if ($candidate->gt($now) && in_array($candidate->dayOfWeek, $daysOfWeek)) {
                $nextRun = $candidate;
                break;
            }
        }

        // If next run exceeds end date, finish the schedule
        if ($nextRun && $schedule->ends_at && $nextRun->gt($schedule->ends_at->timezone($schedule->timezone))) {
            $schedule->update(['status' => 'finished']);
            return null;
        }

        return $nextRun ? $nextRun->utc() : null;
    }

    /**
     * Re-schedule post after a successful run.
     */
    public function rescheduleRecurring(Post $post): void
    {
        $schedule = $post->recurringSchedule;
        if (!$schedule || $schedule->status !== 'active') {
            return;
        }

        $nextRun = $this->calculateNextRun($schedule);
        
        if ($nextRun) {
            $schedule->update(['next_run_at' => $nextRun]);

            // Re-schedule all targets
            foreach ($post->targets as $target) {
                $target->update([
                    'scheduled_at' => $nextRun,
                    'status' => PostStatus::SCHEDULED,
                    'attempts_count' => 0,
                    'error_message' => null,
                    'published_at' => null,
                ]);
            }
            $post->update(['status' => PostStatus::SCHEDULED]);
            Log::info("Rescheduled recurring post ID {$post->id} for next run: {$nextRun}");
        } else {
            $schedule->update(['status' => 'finished']);
            Log::info("Finished recurring schedule for post ID {$post->id}");
        }
    }
}
