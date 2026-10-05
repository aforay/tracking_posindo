<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$aliqaId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';

// Subclass to test the fix
class TestShipmentsImport extends \App\Imports\ShipmentsImport {
    protected function buildHeaderMap(array $rowArray): array
    {
        $map = parent::buildHeaderMap($rowArray);
        foreach ($rowArray as $colIdx => $val) {
            $valStr = strtolower(trim((string)$val));
            $clean = preg_replace('/[^a-z0-9]/', '', $valStr);
            if (empty($clean)) continue;

            if (in_array($clean, [
                'ditugaskanke', 'ditugaskan', 'assignedto', 'assignee', 'penugasan',
                'namacs', 'cs', 'crm', 'admincs', 'namaadmin', 'piccs', 'csname', 'customerservice'
            ]) || str_contains($clean, 'ditugaskan') || str_contains($clean, 'namacs')) {
                $map['nama_cs'] = $colIdx;
            }
        }
        return $map;
    }

    protected function parseRowArray(array $rowArray, array $headerMap, string $now, ?string $sheetName = null): ?array
    {
        $parsed = parent::parseRowArray($rowArray, $headerMap, $now, $sheetName);
        if ($parsed) {
            // Check if nama_cs is '1' or numeric or empty
            if (empty($parsed['nama_cs']) || is_numeric($parsed['nama_cs']) || strlen($parsed['nama_cs']) <= 1) {
                // If headerMap has Ditugaskan Ke (index 9 in Aliqa)
                if (isset($rowArray[9]) && !empty(trim((string)$rowArray[9]))) {
                    $cand = trim((string)$rowArray[9]);
                    if (!is_numeric($cand) && strlen($cand) > 1) {
                        $parsed['nama_cs'] = $cand;
                    }
                }
            }
        }
        return $parsed;
    }
}

$importer = new TestShipmentsImport('Aliqa');

function testParseWithFix($spreadsheetId, $sheetName, $seller) {
    global $importer;
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
    $resp = \Illuminate\Support\Facades\Http::timeout(30)->get($csvUrl);
    if (!$resp->successful()) return;
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $resp->body());
    rewind($stream);
    
    $ref = new ReflectionClass($importer);
    $mHeader = $ref->getMethod('buildHeaderMap');
    $mHeader->setAccessible(true);
    $mParse = $ref->getMethod('parseRowArray');
    $mParse->setAccessible(true);
    
    $headerMap = [];
    $count = 0;
    echo "=== TEST WITH FIX: {$sheetName} ({$seller}) ===\n";
    while (($row = fgetcsv($stream)) !== false) {
        $count++;
        if (empty($headerMap)) {
            $possibleMap = $mHeader->invoke($importer, $row);
            if (!empty($possibleMap)) {
                $headerMap = $possibleMap;
                echo "Header Map: " . json_encode($headerMap) . "\n";
                continue;
            }
        }
        $parsed = $mParse->invoke($importer, $row, $headerMap, now()->toDateTimeString(), $sheetName);
        if ($parsed && $count <= 6) {
            echo "Row {$count} Resi: {$parsed['no_resi']} | CS: '{$parsed['nama_cs']}' | Penerima: {$parsed['nama_penerima']}\n";
        }
    }
    fclose($stream);
}

testParseWithFix($aliqaId, 'SEPTEMBER 2026 (FP ALIQA)', 'Aliqa');
testParseWithFix($aliqaId, 'OKTOBER 2026 (FP ALIQA)', 'Aliqa');
