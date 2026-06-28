<?php

namespace App\Jobs;

use App\Models\AutomationCampaign;
use App\Services\Automation\AutomationPlannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAutomationCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(AutomationPlannerService $planner): void
    {
        if (! config('automation.enabled', true)) {
            return;
        }

        $campaign = AutomationCampaign::with('category')->find($this->campaignId);
        if (! $campaign || ! $campaign->is_active) {
            return;
        }

        $max = (int) config('automation.max_posts_per_run', 10);
        $plan = array_slice($planner->buildDailyPlan($campaign), 0, $max);

        if (empty($plan)) {
            Log::info('RunAutomationCampaignJob: empty plan', ['campaign_id' => $campaign->id]);

            return;
        }

        $run = $planner->createRun($campaign, $plan);

        foreach ($run->items as $item) {
            GenerateAutomatedPostJob::dispatch($item->id);
        }
    }
}
