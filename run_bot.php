<?php
/**
 * Background Bot Runner - Runs nipos:track --all in a standalone PHP process.
 * This file bootstraps Laravel and executes the artisan command directly.
 * Designed to be called via: start /B php run_bot.php
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Log;

Log::info('run_bot.php: Background bot process started at ' . now());

try {
    // Run the artisan command
    $exitCode = \Illuminate\Support\Facades\Artisan::call('nipos:track', ['--all' => true]);
    Log::info('run_bot.php: Bot finished with exit code ' . $exitCode);
} catch (\Throwable $e) {
    Log::error('run_bot.php: Bot crashed: ' . $e->getMessage());
}
