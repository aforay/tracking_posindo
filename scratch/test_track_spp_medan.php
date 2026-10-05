<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$resi = 'BAC021026416B8347020';

// Test what NiposFastTracker returns
$fastTracker = new \App\Services\NiposFastTracker();
$res = $fastTracker->trackSingle($resi);
echo "NiposFastTracker result:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n";

// Test what NiposApiService or timeline scraper returns
$apiService = new \App\Services\NiposApiService();
$timeline = $apiService->trackShipment($resi);
echo "NiposApiService timeline:\n" . json_encode($timeline, JSON_PRETTY_PRINT) . "\n";
