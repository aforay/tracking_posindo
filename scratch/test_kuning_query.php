<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\OutgoingShipment;

// Test the query for KUNING filter on month 8
$query = OutgoingShipment::whereYear('tanggal_kirim', 2026)
    ->whereMonth('tanggal_kirim', 8)
    ->where('nama_seller', 'LIKE', '%ALIQA%');

$cUpper = 'KUNING';
$query->where('color_code', 'KUNING')
  ->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])
  ->where(function ($sub) {
      $sub->where('status_pos', '!=', 'DELIVERED')
          ->orWhereNull('status_pos');
  })
  ->where(function ($sub) {
      $sub->where('status_pos', '!=', 'DELIVERED (RETURN DELIVERY)')
          ->where('status_pos', 'NOT LIKE', '%RETURN%')
          ->where('status_pos', 'NOT LIKE', '%RETUR%')
          ->where('status_pos', 'NOT LIKE', '%DITOLAK%')
          ->orWhereNull('status_pos');
  })
  ->where(function ($sub) {
      $sub->where('keterangan', 'NOT LIKE', '%DITERIMA PENGIRIM%')
          ->where('keterangan', 'NOT LIKE', '%RETURN%')
          ->where('keterangan', 'NOT LIKE', '%RETUR%')
          ->where('keterangan', 'NOT LIKE', '%DITOLAK%')
          ->orWhereNull('keterangan');
  });

$count = $query->count();
echo "Count of SUDAH DI FU in August after fix: {$count}\n";

// Check if any of them is retur
$returInKuning = (clone $query)->where(function($q) {
    $q->where('status_pos', 'LIKE', '%RETURN%')
      ->orWhere('keterangan', 'LIKE', '%DITERIMA PENGIRIM%');
})->count();

echo "Any retur inside SUDAH DI FU query? {$returInKuning}\n";

// Check where BAC010826569F50B8B06 is now
$s = OutgoingShipment::where('no_resi', 'BAC010826569F50B8B06')->first();
echo "BAC010826569F50B8B06 status_kategori: {$s->status_kategori}, color_code: {$s->color_code}\n";
