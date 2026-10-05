<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OutgoingShipment;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;

$resis = [
    'BAC01102611804F885B8',
    'BAC01102629DD274FD31',
    'BAC011026117E210C6ED',
    'BAC01102655854FB9CB0',
    'BAC01102658D027AEC79',
];

$ctrl = app(DashboardController::class);

foreach ($resis as $r) {
    $req = Request::create('/dashboard', 'GET', ['search' => $r, 'seller' => 'Mitra Zaherba']);
    $resp = $ctrl->index($req);
    $page = $resp->toResponse($req)->getOriginalContent()->getData()['page'] ?? [];
    $data = $page['props']['shipments']['data'][0] ?? null;
    echo "Resi: $r\n";
    echo "  kantorTujuan: " . ($data['kantorTujuan'] ?? 'NULL') . "\n";
    echo "  kantorPosPic: " . ($data['kantorPosPic'] ?? 'NULL') . "\n";
    echo "  kantorPosPhone: " . ($data['kantorPosPhone'] ?? 'NULL') . "\n";
    echo "  lastLocation: " . ($data['lastLocation'] ?? 'NULL') . "\n";
}
