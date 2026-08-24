<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Console Schedule Tasks
|--------------------------------------------------------------------------
|
| Scheduled task for automatic Google Sheets sync every 15 minutes.
| Run locally with: php artisan schedule:work
|
*/
Schedule::command('sheets:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sheets_sync_schedule.log'));
