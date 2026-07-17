<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('social:publish-due')->everyMinute();
Schedule::command('automation:run-due')->everyFiveMinutes();
Schedule::command('automation:auto-review-pending')->everyFiveMinutes();
Schedule::command('social:refresh-tokens')->daily();
Schedule::command('social:cleanup-old-logs')->daily();
