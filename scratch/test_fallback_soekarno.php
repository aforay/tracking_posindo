<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$resi = 'BAC021026437AAC93B23';
$req = Illuminate\Http\Request::create('/dashboard', 'GET', ['search' => $resi]);
$resp = app(\App\Http\Controllers\DashboardController::class)->index($req);
$page = $resp->toResponse($req)->getOriginalContent()->getData()['page'] ?? [];
$data = $page['props']['shipments']['data'][0] ?? null;

echo json_encode([
    'resi' => $data['resi'] ?? null,
    'kantorTujuan' => $data['kantorTujuan'] ?? null,
    'lastLocation' => $data['lastLocation'] ?? null,
    'kantorPosPhone' => $data['kantorPosPhone'] ?? null,
    'kantorPosPic' => $data['kantorPosPic'] ?? null,
    'namaCs' => $data['namaCs'] ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;
