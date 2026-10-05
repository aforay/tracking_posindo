<?php

$batchHtml = file_get_contents(__DIR__ . '/raw_batch_response.html');
$dom = new DOMDocument();
@$dom->loadHTML($batchHtml);
$xpath = new DOMXPath($dom);

$rows = $xpath->query('//table//tr');
echo "=== BATCH HTML TABLE ROWS: " . $rows->length . " ===\n";
foreach ($rows as $i => $row) {
    $cols = $xpath->query('.//td | .//th', $row);
    $cTexts = [];
    foreach ($cols as $col) {
        $cTexts[] = trim(preg_replace('/\s+/', ' ', $col->textContent));
    }
    echo "Row {$i}: " . json_encode($cTexts) . "\n";
}

$detailHtml = file_get_contents(__DIR__ . '/raw_detail_response.html');
$dom2 = new DOMDocument();
@$dom2->loadHTML($detailHtml);
$xpath2 = new DOMXPath($dom2);

$dRows = $xpath2->query('//table//tr');
echo "\n=== DETAIL HTML TABLE ROWS: " . $dRows->length . " ===\n";
foreach ($dRows as $i => $row) {
    $cols = $xpath2->query('.//td | .//th', $row);
    $cTexts = [];
    foreach ($cols as $col) {
        $cTexts[] = trim(preg_replace('/\s+/', ' ', $col->textContent));
    }
    echo "Detail Row {$i}: " . json_encode($cTexts) . "\n";
}
