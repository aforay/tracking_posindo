<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\DashboardController;
use App\Services\TrackingBotService;

DB::enableQueryLog();

$startTotal = microtime(true);

$request = Request::create('/shipments', 'GET', [
    'seller' => 'Mitra Aliqa',
    'month' => '8',
]);

$controller = $app->make(DashboardController::class);

$t0 = microtime(true);
$response = $controller->index($request);
$httpResponse = $response->toResponse($request);
$content = $httpResponse->getContent();
$tEnd = microtime(true);

$queries = DB::getQueryLog();
$totalQueryTime = array_sum(array_column($queries, 'time'));

echo "Controller Execution Time: " . round(($tEnd - $t0) * 1000, 2) . " ms\n";
echo "Total Query Time: " . round($totalQueryTime, 2) . " ms\n";
echo "Number of Queries: " . count($queries) . "\n";
echo "Response payload size: " . round(strlen($content) / 1024, 2) . " KB\n";

// Sort queries by execution time
usort($queries, fn($a, $b) => $b['time'] <=> $a['time']);

echo "\nTop 10 Slowest Queries:\n";
foreach (array_slice($queries, 0, 10) as $i => $q) {
    echo "[" . ($i + 1) . "] Time: " . $q['time'] . " ms\n";
    echo "SQL: " . substr($q['query'], 0, 180) . "...\n\n";
}
