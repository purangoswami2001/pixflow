<?php

namespace App\Jobs;

use App\Services\Posts\QueueDispatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $dispatcher = new QueueDispatcherService();
        $dispatcher->dispatchDueSlots();
        $dispatcher->dispatchManualDuePosts();
    }
}
