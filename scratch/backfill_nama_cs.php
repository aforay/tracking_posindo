<?php
require 'vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "Total OutgoingShipment currently: " . OutgoingShipment::count() . PHP_EOL;
echo "With nama_cs: " . OutgoingShipment::whereNotNull('nama_cs')->count() . PHP_EOL;

$syncService = new \App\Services\GoogleSheetsSyncService();

$configs = [
    [
        'seller' => 'Mitra Aliqa',
        'id' => '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac',
    ],
    [
        'seller' => 'Mitra Zaherba',
        'id' => '1AdRkyrZ6_GIKPdk0sn2Bf4g19BhgTUHcm2niznvvv5c',
    ]
];

$totalUpdated = 0;

foreach ($configs as $cfg) {
    $seller = $cfg['seller'];
    $spreadsheetId = $cfg['id'];
    echo "\n=== Processing {$seller} ({$spreadsheetId}) ===" . PHP_EOL;
    
    $sheetNames = $syncService->discoverSheetNames($spreadsheetId, $seller);
    echo "Discovered " . count($sheetNames) . " sheets: " . implode(', ', $sheetNames) . PHP_EOL;

    foreach ($sheetNames as $sheetName) {
        echo "Fetching sheet: {$sheetName}... ";
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
        $resp = Http::timeout(60)->get($csvUrl);
        if ($resp->failed()) {
            echo "FAILED (" . $resp->status() . ")" . PHP_EOL;
            continue;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $resp->body());
        rewind($stream);

        $headerMap = [];
        $headerFound = false;
        $sheetUpdates = [];

        while (($rowArray = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            if (empty(array_filter($rowArray))) continue;

            if (!$headerFound) {
                // Check header
                foreach ($rowArray as $colIdx => $val) {
                    $clean = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$val)));
                    if (in_array($clean, ['noresi', 'resi', 'awb', 'barcode', 'nobarcode', 'nomorresi', 'resiposis', 'barcodeitem'])) {
                        $headerMap['resi'] = $colIdx;
                    } elseif (in_array($clean, ['namacs', 'cs', 'crm', 'admincs', 'namaadmin', 'piccs', 'csname', 'customerservice']) || str_contains($clean, 'namacs')) {
                        $headerMap['nama_cs'] = $colIdx;
                    }
                }
                if (isset($headerMap['resi'])) {
                    $headerFound = true;
                    continue;
                }
            }

            $resi = null;
            if (isset($headerMap['resi']) && isset($rowArray[$headerMap['resi']])) {
                $cand = trim((string)$rowArray[$headerMap['resi']]);
                if (preg_match('/^[A-Za-z0-9]{8,35}$/', $cand)) {
                    $resi = $cand;
                }
            }
            if (!$resi) {
                foreach ($rowArray as $cell) {
                    $cand = trim((string)$cell);
                    if (preg_match('/^[A-Za-z0-9]{8,35}$/', $cand) && (str_starts_with(strtoupper($cand), 'P') || str_starts_with(strtoupper($cand), 'BAC'))) {
                        $resi = $cand;
                        break;
                    }
                }
            }

            if (!$resi) continue;

            // Ignore phone numbers wrongly placed
            if (preg_match('/^0?8\d{8,}/', $resi) || preg_match('/^628\d{8,}/', $resi)) {
                continue;
            }

            $namaCs = null;
            if (isset($headerMap['nama_cs']) && isset($rowArray[$headerMap['nama_cs']])) {
                $namaCs = trim((string)$rowArray[$headerMap['nama_cs']]);
            } elseif (str_contains(strtoupper($seller), 'ZAHERBA') && isset($rowArray[6])) {
                $namaCs = trim((string)$rowArray[6]);
            } elseif (isset($rowArray[5])) {
                $namaCs = trim((string)$rowArray[5]);
            }

            if ($namaCs && !in_array(strtoupper($namaCs), ['NAMA CS', 'CS', 'CRM', 'ADMIN', 'PIC CS', 'NAMA ADMIN', 'SELLER', 'NAMA', 'RESI', '-', '', 'N/A', 'NULL'])) {
                $sheetUpdates[(string)$resi] = (string)$namaCs;
            }
        }
        fclose($stream);

        echo "Found " . count($sheetUpdates) . " resis with CS. ";

        if (!empty($sheetUpdates)) {
            // Group resis by CS name
            $byCs = [];
            foreach ($sheetUpdates as $r => $cs) {
                $byCs[$cs][] = (string)$r;
            }

            $sheetUpdatedCount = 0;
            foreach ($byCs as $csName => $resiList) {
                foreach (array_chunk($resiList, 500) as $chunk) {
                    $affected = DB::table('outgoing_shipments')
                        ->whereIn('no_resi', $chunk)
                        ->update(['nama_cs' => $csName]);
                    $sheetUpdatedCount += $affected;
                }
            }
            echo "Updated: {$sheetUpdatedCount} rows in DB." . PHP_EOL;
            $totalUpdated += $sheetUpdatedCount;
        } else {
            echo PHP_EOL;
        }
    }
}

echo "\n=== BACKFILL COMPLETE! Total rows updated: {$totalUpdated} ===" . PHP_EOL;
echo "With nama_cs now: " . OutgoingShipment::whereNotNull('nama_cs')->count() . PHP_EOL;
