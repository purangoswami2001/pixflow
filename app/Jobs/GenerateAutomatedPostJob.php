<?php

namespace App\Jobs;

use App\Models\AutomationRun;
use App\Models\AutomationRunItem;
use App\Services\Automation\AutomationPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAutomatedPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public int $runItemId,
        public bool $resume = false
    ) {
    }

    public function handle(AutomationPipelineService $pipeline): void
    {
        if (! config('automation.enabled', true)) {
            return;
        }

        $item = AutomationRunItem::with('run')->find($this->runItemId);
        if (! $item || $item->status === 'post_created') {
            return;
        }

        $metadata = $item->metadata ?? [];
        $wasFailed = $item->status === 'failed'
            || ! empty($metadata['retried_from_failed'])
            || $this->resume;

        try {
            $pipeline->processItem($item, resume: $this->resume || $item->status === 'failed' || ! empty($metadata['retried_from_failed']));

            $item->refresh();
            $meta = $item->metadata ?? [];
            unset($meta['retried_from_failed']);
            // Keep retry_count + previous_errors for detail history after success.
            $item->update([
                'error_message' => null,
                'metadata' => $meta,
            ]);

            if ($item->status === 'post_created' && (int) ($item->metadata['retry_count'] ?? 0) > 0) {
                try {
                    app(\App\Services\Notifications\InAppNotificationService::class)->notifyAdmins(
                        'pipeline_success',
                        'Pipeline retry succeeded',
                        ucfirst((string) $item->platform).' item succeeded after retry'.($item->post_id ? ' — post #'.$item->post_id : '').'.',
                        'success',
                        route('admin.automation.runs.show', $item->automation_run_id),
                        ['run_id' => $item->automation_run_id, 'item_id' => $item->id, 'post_id' => $item->post_id],
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            Log::error('GenerateAutomatedPostJob failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'resume' => $wasFailed,
            ]);

            $metadata = $item->metadata ?? [];
            $metadata['failed_at_step'] = $item->status;
            $metadata['retried_from_failed'] = false;

            $item->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            try {
                app(\App\Services\Notifications\InAppNotificationService::class)->notifyAdmins(
                    'pipeline_failed',
                    'Pipeline item failed',
                    ucfirst((string) $item->platform).' item failed: '.$e->getMessage(),
                    'danger',
                    route('admin.automation.runs.show', $item->automation_run_id),
                    ['run_id' => $item->automation_run_id, 'item_id' => $item->id, 'error' => $e->getMessage()],
                );
            } catch (\Throwable $notifyError) {
                report($notifyError);
            }
        }

        $this->finalizeRunIfComplete($item->automation_run_id);
    }

    protected function finalizeRunIfComplete(int $runId): void
    {
        $run = AutomationRun::with('items')->find($runId);
        if (! $run) {
            return;
        }

        $pending = $run->items->whereNotIn('status', ['post_created', 'failed'])->count();
        if ($pending > 0) {
            $run->syncCountsFromItems(finalize: false);
            if ($run->status !== 'running') {
                $run->update([
                    'status' => 'running',
                    'finished_at' => null,
                ]);
            }

            return;
        }

        $run->syncCountsFromItems(finalize: true);

        $this->dispatchAutoReviewForRun($run);

        $campaign = $run->campaign;
        $campaign?->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addDay()->setTimeFromTimeString(
                is_string($campaign->generation_time) ? $campaign->generation_time : '06:00:00'
            ),
        ]);
    }

    protected function dispatchAutoReviewForRun(AutomationRun $run): void
    {
        if (! config('automation.auto_review.enabled') || ! config('automation.auto_review.dispatch_after_create', true)) {
            return;
        }

        $postIds = $run->items
            ->where('status', 'post_created')
            ->pluck('post_id')
            ->filter()
            ->unique()
            ->values();

        if ($postIds->isEmpty()) {
            return;
        }

        $posts = \App\Models\Post::query()
            ->whereIn('id', $postIds)
            ->get(['id', 'metadata']);

        foreach ($posts as $post) {
            $decision = $post->metadata['auto_review']['decision'] ?? null;
            if (! empty($decision)) {
                continue;
            }

            AutoReviewPostJob::dispatch($post->id);
            \App\Services\Automation\AutoReviewLogger::info('Dispatched auto-review on pipeline run completion', [
                'post_id' => $post->id,
                'run_id' => $run->id,
            ]);
        }
    }
}
