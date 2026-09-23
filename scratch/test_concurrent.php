<?php

$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < 5; $i++) {
    $ch = curl_init('http://127.0.0.1:8000/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

$t0 = microtime(true);
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

$tEnd = microtime(true);

foreach ($handles as $ch) {
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

echo "Time for 5 CONCURRENT requests: " . round(($tEnd - $t0) * 1000, 2) . " ms\n";
