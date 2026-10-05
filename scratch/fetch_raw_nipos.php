<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cookie = \App\Models\SystemSetting::getNiposCookie();
$url = 'https://pid.posindonesia.co.id/lacak/admin/lacak_item_banyakzaref.php';

$resp = \Illuminate\Support\Facades\Http::timeout(15)
    ->withHeaders([
        'Cookie' => $cookie,
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ])
    ->asForm()
    ->post($url, ['awb' => 'BAC021026416B8347020']);

echo "Batch response status: " . $resp->status() . "\n";
$html = $resp->body();

// Save to scratch file to inspect
file_put_contents(__DIR__ . '/raw_batch_response.html', $html);

// Also fetch detail timeline
$detailUrl = 'https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php?id=' . base64_encode('BAC021026416B8347020');
$detailResp = \Illuminate\Support\Facades\Http::timeout(15)
    ->withHeaders([
        'Cookie' => $cookie,
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ])
    ->get($detailUrl);

echo "Detail response status: " . $detailResp->status() . "\n";
file_put_contents(__DIR__ . '/raw_detail_response.html', $detailResp->body());

echo "Done saving HTMLs\n";
