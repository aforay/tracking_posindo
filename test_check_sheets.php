<?php
require_once __DIR__ . '/vendor/autoload.php';

$aliqaId = '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg';
$url = "https://docs.google.com/spreadsheets/d/{$aliqaId}/gviz/tq?tqx=out:csv&sheet=" . urlencode('AGUSTUS 2026 (FP ALIQA)');
$ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
$csv = file_get_contents($url, false, $ctx);
$lines = str_getcsv($csv, "\n");
echo "Total lines: " . count($lines) . "\n";
echo "Header: " . $lines[0] . "\n\n";

$count = 0;
foreach ($lines as $idx => $line) {
    if ($idx === 0) continue;
    $row = str_getcsv($line);
    // Check if any date or resi is September
    $tgl = $row[0] ?? '';
    $resi = $row[2] ?? '';
    if (str_contains($tgl, '09') || str_contains($tgl, 'Sep') || str_contains($tgl, 'SEP') || str_starts_with($resi, 'P2609')) {
        echo "Line {$idx}:\n";
        echo "  Tanggal: {$tgl}\n";
        echo "  Resi: {$resi}\n";
        echo "  Penerima: " . ($row[3] ?? '') . "\n";
        echo "  Raw: " . substr($line, 0, 150) . "...\n\n";
        $count++;
        if ($count >= 5) break;
    }
}
