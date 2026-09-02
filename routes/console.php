<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tracking', function () {
    $this->comment('Menjalankan pelacakan NIPos untuk semua resi...');
    $this->call('nipos:track', ['--all' => true]);
})->purpose('Alias untuk nipos:track --all');

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

Schedule::command('nipos:track-all')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nipos_track_schedule.log'));

Schedule::command('sheets:push-updates')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sheets_push_schedule.log'));

