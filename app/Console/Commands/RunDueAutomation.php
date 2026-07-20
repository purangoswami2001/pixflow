<?php

namespace App\Console\Commands;

use App\Jobs\RunAutomationCampaignJob;
use App\Models\AutomationCampaign;
use Illuminate\Console\Command;

class RunDueAutomation extends Command
{
    protected $signature = 'automation:run-due';

    protected $description = 'Run due AI automation campaigns';

    public function handle(): int
    {
        if (! config('automation.enabled', true)) {
            $this->warn('Automation is disabled (AUTOMATION_ENABLED=false).');

            return 0;
        }

        $campaigns = AutomationCampaign::query()
            ->whereHas('organization', fn ($query) => $query
                ->where('ai_enabled', true)
                ->where('automation_enabled', true))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        foreach ($campaigns as $campaign) {
            $this->info("Dispatching campaign #{$campaign->id}: {$campaign->name}");
            RunAutomationCampaignJob::dispatch($campaign->id);
        }

        $this->info('Dispatched ' . $campaigns->count() . ' campaign(s).');

        return 0;
    }
}
