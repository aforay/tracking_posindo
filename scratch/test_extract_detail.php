<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$detailHtml = file_get_contents(__DIR__ . '/raw_detail_response.html');
$botService = app(\App\Services\TrackingBotService::class);

$resolved = $botService->extractKantorTujuan('', $detailHtml, 'PERUMAHAN GUSUNG BARU BLOK C NO 09 DESA GUSUNG KEC DELENG POKHISEN KAB ACEH TENGGARA KUTACANE 24678');
echo "Resolved by extractKantorTujuan: " . json_encode($resolved) . "\n";
