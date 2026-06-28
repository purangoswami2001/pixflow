<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPostsJob;
use Illuminate\Console\Command;

class PublishDuePosts extends Command
{
    protected $signature = 'social:publish-due';
    protected $description = 'Find due scheduled post targets and publish them';

    public function handle(): int
    {
        $this->info('Dispatching ProcessScheduledPostsJob...');
        ProcessScheduledPostsJob::dispatchSync();
        $this->info('Job executed successfully.');
        return 0;
    }
}
