<?php

namespace App\Jobs;

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

        $wasFailed = $item->status === 'failed';

        try {
            $pipeline->processItem($item, resume: $this->resume || $wasFailed);

            $run = $item->run()->first();
            $run?->increment('created_count');

            if ($wasFailed && $run && $run->failed_count > 0) {
                $run->decrement('failed_count');
            }
        } catch (\Throwable $e) {
            Log::error('GenerateAutomatedPostJob failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'resume' => $this->resume || $wasFailed,
            ]);

            $metadata = $item->metadata ?? [];
            $metadata['failed_at_step'] = $item->status;

            $item->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            if (! $wasFailed) {
                $item->run?->increment('failed_count');
            }
        }

        $this->finalizeRunIfComplete($item->automation_run_id);
    }

    protected function finalizeRunIfComplete(int $runId): void
    {
        $run = \App\Models\AutomationRun::with('items')->find($runId);
        if (! $run) {
            return;
        }

        $pending = $run->items->whereNotIn('status', ['post_created', 'failed'])->count();
        if ($pending > 0) {
            if ($run->status !== 'running') {
                $run->update([
                    'status' => 'running',
                    'finished_at' => null,
                ]);
            }

            return;
        }

        $run->update([
            'status' => $run->failed_count > 0 ? 'partial' : 'completed',
            'finished_at' => now(),
        ]);

        $campaign = $run->campaign;
        $campaign?->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addDay()->setTimeFromTimeString(
                is_string($campaign->generation_time) ? $campaign->generation_time : '06:00:00'
            ),
        ]);
    }
}
