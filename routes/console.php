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
| Console Schedule Tasks — Real-Time Bidirectional Sync
|--------------------------------------------------------------------------
|
| Jalankan: php artisan schedule:work
| (atau tambahkan ke Windows Task Scheduler agar otomatis berjalan)
|
| ALUR SYNC:
|   1. sheets:sync        → Pull data baru dari Seller (Google Sheets → DB)
|   2. sheets:push-updates → Push status update dari DB → Google Sheets (via Webhook)
|   3. nipos:track-all   → Bot NIPPOS otomatis (DB → NIPPOS → DB → Sheets)
|
*/

// 1. Auto-pull data baru dari Seller (Sheet → DB) setiap 5 menit
//    Hanya sync bulan berjalan untuk hemat waktu
Schedule::command('sheets:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)  // Skip jika masih ada yang berjalan (max 10 menit)
    ->appendOutputTo(storage_path('logs/sheets_sync_schedule.log'));

// 2. Push status update terbaru dari DB ke Sheet via Webhook setiap 5 menit
Schedule::command('sheets:push-updates')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/sheets_push_schedule.log'));

// 3. Bot NIPPOS otomatis setiap 15 menit (lebih berat karena akses ke NIPPOS API)
Schedule::command('nipos:track-all')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->appendOutputTo(storage_path('logs/nipos_track_schedule.log'));

// 4. Backup database MySQL harian ke storage/backups (dijalankan otomatis setiap malam pukul 02:00)
Schedule::command('db:backup --compress')
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/db_backup_schedule.log'));


