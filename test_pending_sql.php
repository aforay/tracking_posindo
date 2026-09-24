<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$todayStart = now()->startOfDay()->toDateTimeString();

$oldPending = DB::table('outgoing_shipments')
    ->whereMonth('tanggal_kirim', 8)
    ->whereRaw("
        (status_pos IS NULL OR status_pos = '' OR status_pos LIKE '%PROCESS%' 
        OR status_kategori NOT IN ('SUKSES', 'RETUR') OR status_kategori IS NULL 
        OR color_code NOT IN ('BIRU', 'ORANGE') OR color_code IS NULL 
        OR status_pos IN ('unBag', 'UNBAG', 'INVEHICLE', 'INLOCATION', 'inBag', 'INBAG', 'DELIVERYRUNSHEET', 'FAILEDTODELIVERED', 'ARRIVEDUNPAID', 'Irregularity', 'MANIFEST', 'ARRIVAL', 'DEPARTURE')
        OR ((status_kategori = 'RETUR' OR color_code = 'ORANGE') AND (status_pos IS NULL OR (status_pos NOT LIKE '%RETURN DELIVERY%' AND status_pos NOT LIKE '%RETURN TO SENDER%' AND status_pos NOT LIKE '%DITERIMA PENGIRIM%'))))
        AND (status_pos IS NULL OR status_pos NOT LIKE '%DELIVERED%' OR status_pos LIKE '%RETURN%')
    ")->count();

$newPendingForToday = DB::table('outgoing_shipments')
    ->whereMonth('tanggal_kirim', 8)
    ->whereRaw("
        (status_pos IS NULL OR status_pos = '' OR status_pos LIKE '%PROCESS%' 
        OR status_kategori NOT IN ('SUKSES', 'RETUR') OR status_kategori IS NULL 
        OR color_code NOT IN ('BIRU', 'ORANGE') OR color_code IS NULL 
        OR status_pos IN ('unBag', 'UNBAG', 'INVEHICLE', 'INLOCATION', 'inBag', 'INBAG', 'DELIVERYRUNSHEET', 'FAILEDTODELIVERED', 'ARRIVEDUNPAID', 'Irregularity', 'MANIFEST', 'ARRIVAL', 'DEPARTURE')
        OR ((status_kategori = 'RETUR' OR color_code = 'ORANGE') AND (status_pos IS NULL OR (status_pos NOT LIKE '%RETURN DELIVERY%' AND status_pos NOT LIKE '%RETURN TO SENDER%' AND status_pos NOT LIKE '%DITERIMA PENGIRIM%'))))
        AND (status_pos IS NULL OR status_pos NOT LIKE '%DELIVERED%' OR status_pos LIKE '%RETURN%')
        AND (last_tracked_at IS NULL OR last_tracked_at < '{$todayStart}')
    ")->count();

echo "Old Pending count (ignoring last_tracked_at): {$oldPending}\n";
echo "New Pending count (checking last_tracked_at < today): {$newPendingForToday}\n";
