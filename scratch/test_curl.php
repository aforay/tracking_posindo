<?php

$ch = curl_init('http://127.0.0.1:8000/shipments/live-version');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$t0 = microtime(true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);
$tEnd = microtime(true);

echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Total Time: " . round(($tEnd - $t0) * 1000, 2) . " ms\n";
echo "Response: " . substr($res, 0, 100) . "\n";
