<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Cek Retur yang masih berstatus non-RETUR / non-ORANGE
$affectedRetur = DB::table('outgoing_shipments')
    ->where(function($q) {
        $q->where('status_pos', 'LIKE', '%RETURN%')
          ->orWhere('status_pos', 'LIKE', '%RETUR%')
          ->orWhere('status_pos', 'LIKE', '%DITOLAK%')
          ->orWhere('keterangan', 'LIKE', '%RETURN%')
          ->orWhere('keterangan', 'LIKE', '%RETUR%')
          ->orWhere('keterangan', 'LIKE', '%DITOLAK%');
    })
    ->where(function($q) {
        $q->where('status_kategori', '!=', 'RETUR')
          ->orWhere('color_code', '!=', 'ORANGE');
    })
    ->update([
        'status_kategori' => 'RETUR',
        'color_code' => 'ORANGE',
    ]);

echo "Updated Retur shipments: {$affectedRetur}\n";

// 2. Cek Sukses DELIVERED yang masih non-SUKSES / non-BIRU
$affectedSukses = DB::table('outgoing_shipments')
    ->where('status_pos', 'DELIVERED')
    ->where('status_pos', 'NOT LIKE', '%RETURN%')
    ->where(function($q) {
        $q->whereNull('keterangan')
          ->orWhere(function($sub) {
              $sub->where('keterangan', 'NOT LIKE', '%RETURN%')
                  ->where('keterangan', 'NOT LIKE', '%RETUR%')
                  ->where('keterangan', 'NOT LIKE', '%DITOLAK%');
          });
    })
    ->where(function($q) {
        $q->where('status_kategori', '!=', 'SUKSES')
          ->orWhere('color_code', '!=', 'BIRU');
    })
    ->update([
        'status_kategori' => 'SUKSES',
        'color_code' => 'BIRU',
    ]);

echo "Updated Sukses shipments: {$affectedSukses}\n";

// 3. Cek sampel resi dari screenshot user: BAC010826569F50B8B06
$sample1 = DB::table('outgoing_shipments')->where('no_resi', 'BAC010826569F50B8B06')->first();
echo "Resi BAC010826569F50B8B06: status_pos={$sample1->status_pos}, status_kategori={$sample1->status_kategori}, color_code={$sample1->color_code}\n";
