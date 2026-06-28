<?php

namespace App\Console\Commands;

use App\Models\PublishLog;
use Illuminate\Console\Command;

class CleanupOldLogs extends Command
{
    protected $signature = 'social:cleanup-old-logs {days=30 : The number of days of history to retain}';
    protected $description = 'Clean up old publishing logs from the database';

    public function handle(): int
    {
        $days = (int)$this->argument('days');
        $this->info("Cleaning up publishing logs older than {$days} days...");

        $cutoff = now()->subDays($days);
        $deleted = PublishLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} old logs.");
        return 0;
    }
}
