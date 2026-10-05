<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Check deleted_at
$deletedAt = App\Models\OutgoingShipment::where('nama_seller', 'like', '%Aliqa%')
    ->whereMonth('tanggal_kirim', 9)
    ->whereNotNull('deleted_at')
    ->count();

// 2. Check year counts for month 9
$yearCounts = App\Models\OutgoingShipment::where('nama_seller', 'like', '%Aliqa%')
    ->whereMonth('tanggal_kirim', 9)
    ->selectRaw('YEAR(tanggal_kirim) as yr, count(*) as cnt')
    ->groupBy('yr')
    ->get();

// 3. Check dates on 31 August and 1 October
$aug31 = App\Models\OutgoingShipment::where('nama_seller', 'like', '%Aliqa%')
    ->whereDate('tanggal_kirim', '2026-08-31')
    ->count();
$sep1 = App\Models\OutgoingShipment::where('nama_seller', 'like', '%Aliqa%')
    ->whereDate('tanggal_kirim', '2026-09-01')
    ->count();
$sep30 = App\Models\OutgoingShipment::where('nama_seller', 'like', '%Aliqa%')
    ->whereDate('tanggal_kirim', '2026-09-30')
    ->count();

// 4. Check imports history (did an import log show how many rows were imported?)
$importLogs = DB::table('system_settings')->where('key', 'like', '%import%')->get();

// 5. Total resi for Aliqa across all months
$totalAliqa = App\Models\OutgoingShipment::where('nama_seller', 'like', '%Aliqa%')->count();

echo "Deleted at not null: $deletedAt\n";
echo "Total Aliqa All Months: $totalAliqa\n";
echo "Tahun di bulan September:\n";
foreach ($yearCounts as $y) {
    echo "  Tahun: {$y->yr} -> {$y->cnt} resi\n";
}
echo "Resi tanggal 31 Agustus: $aug31\n";
echo "Resi tanggal 1 September: $sep1\n";
echo "Resi tanggal 30 September: $sep30\n";
