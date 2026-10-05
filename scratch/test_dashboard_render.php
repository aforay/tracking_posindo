<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;
use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController;

$controller = app(DashboardController::class);
// We can simulate an HTTP request to index with search
$request = Request::create('/dashboard', 'GET', [
    'search' => 'BAC230926325CC947923',
    'color' => 'ORANGE'
]);

$c1 = DB::table('outgoing_shipments')->where('id', 2020190)->whereBetween('tanggal_kirim', ['2026-01-01', '2026-12-31'])->count();
$c2 = DB::table('outgoing_shipments')->where('id', 2020190)->whereIn('nama_seller', ['Mitra Aliqa', 'Aliqa'])->count();
$c3 = DB::table('outgoing_shipments')->where('id', 2020190)->whereRaw('LOWER(no_resi) = ?', ['bac230926325cc947923'])->count();
$row = DB::table('outgoing_shipments')->where('id', 2020190)->first(['no_resi', 'nama_seller', 'tanggal_kirim']);
echo "Check whereBetween: $c1, check whereIn: $c2, check whereRaw: $c3\n";
echo "Row data: " . json_encode($row) . "\n";
// Emulate the exact query built inside index:
$selectedSeller = 'Mitra Aliqa';
$cleanSeller = 'Aliqa';
$sellerCandidates = [$selectedSeller, $cleanSeller];
$selectedYear = '2026';
$searchQuery = 'BAC230926325CC947923';

$q = OutgoingShipment::query();
$q->whereBetween('tanggal_kirim', ["{$selectedYear}-01-01", "{$selectedYear}-12-31"]);
$q->whereIn('nama_seller', $sellerCandidates);

$raw = trim($searchQuery);
$lowerRaw = mb_strtolower($raw, 'UTF-8');
$terms = preg_split('/[\s,\n;]+/', $raw);
$lowerTerms = array_map(function ($t) { return mb_strtolower($t, 'UTF-8'); }, $terms);

$q->where(function ($b) use ($lowerRaw, $lowerTerms) {
    $b->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(no_resi)'), $lowerTerms)
      ->orWhereRaw('LOWER(no_resi) LIKE ?', ["%{$lowerRaw}%"]);
});

echo "Direct built query count: " . $q->count() . "\n";
echo "SQL: " . $q->toSql() . "\n";
echo "Bindings: " . json_encode($q->getBindings()) . "\n";

$response = $controller->index($request);
$httpResp = $response->toResponse($request);
$view = $httpResp->getOriginalContent();
$pageData = $view->getData()['page'] ?? [];
$inertiaProps = $pageData['props'] ?? [];
echo "Props keys: " . json_encode(array_keys($inertiaProps)) . "\n";
if (isset($inertiaProps['shipments'])) {
    echo "Shipments data count: " . count($inertiaProps['shipments']['data'] ?? []) . "\n";
    echo "Shipments total: " . ($inertiaProps['shipments']['total'] ?? 'N/A') . "\n";
    foreach ($inertiaProps['shipments']['data'] as $row) {
        echo "Resi: " . $row['resi'] . "\n";
        echo "Kantor: " . $row['kantorTujuan'] . "\n";
        echo "LastLoc: " . $row['lastLocation'] . "\n";
        echo "Phone: " . $row['kantorPosPhone'] . "\n";
        echo "PIC: " . $row['kantorPosPic'] . "\n";
        echo "FU: " . $row['fu'] . "\n";
        echo "Status Kategori: " . $row['statusKategori'] . "\n";
        echo "NIPOS: " . $row['nipos'] . "\n";
        echo "Keterangan: " . $row['keterangan'] . "\n";
    }
}
