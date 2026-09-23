<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController;

$req = Request::create('/shipments', 'GET', [
    'search' => 'MALIK',
    'seller' => 'Mitra Aliqa',
    'month' => 3
]);
$req->headers->set('X-Inertia', 'true');

$ctrl = app(DashboardController::class);
$res = $ctrl->index($req);
$httpResponse = $res->toResponse($req);
$page = json_decode($httpResponse->getContent(), true);

echo "Total: " . ($page['props']['shipments']['total'] ?? 'N/A') . "\n";
foreach (($page['props']['shipments']['data'] ?? []) as $row) {
    echo "Resi: {$row['resi']} | Penerima: {$row['penerima']}\n";
}
