<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SystemSetting;
use App\Services\NiposApiService;
use App\Services\NiposFastTracker;
use App\Services\TrackingBotService;
use App\Models\OutgoingShipment;
use Illuminate\Support\Facades\Cache;

echo "=== 1. NIPOS COOKIE SETTINGS ===" . PHP_EOL;
$cookie = SystemSetting::get('nipos_session_cookie');
echo "Cookie length: " . strlen((string)$cookie) . PHP_EOL;
echo "Cookie value: " . substr((string)$cookie, 0, 80) . "..." . PHP_EOL;

echo PHP_EOL . "=== 2. TEST NIPOS API CONNECTION ===" . PHP_EOL;
$api = app(NiposApiService::class);
$connTest = $api->testConnection();
dump($connTest);

echo PHP_EOL . "=== 3. TEST TRACKING 1 REAL RESI ===" . PHP_EOL;
$shipment = OutgoingShipment::whereMonth('tanggal_kirim', 9)
    ->needsTracking()
    ->first();
if ($shipment) {
    echo "Tracking resi: {$shipment->no_resi}..." . PHP_EOL;
    $botService = app(TrackingBotService::class);
    $res = $botService->trackResiList([$shipment->no_resi]);
    dump($res);
} else {
    echo "No pending resi found in September!" . PHP_EOL;
}

echo PHP_EOL . "=== 4. CACHE / BOT STATUS ===" . PHP_EOL;
dump([
    'bot_running' => Cache::get('bot_running'),
    'bot_progress' => Cache::get('bot_progress'),
    'nipos_connected' => Cache::get('nipos_connected'),
]);
