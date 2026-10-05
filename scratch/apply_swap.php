<?php
$file = 'app/Models/PostOffice.php';
$code = file_get_contents($file);

$posStep3 = strpos($code, '// 3. Search by city name keywords');
$posStep4 = strpos($code, '// 4. Check Regional & KCP Alias Dictionary');
$posStep5 = strpos($code, '// 5. Fallback via Postal Code Prefix');

if ($posStep3 === false || $posStep4 === false || $posStep5 === false) {
    die("Error locating steps!\n");
}

$step3Code = substr($code, $posStep3, $posStep4 - $posStep3);
$step4Code = substr($code, $posStep4, $posStep5 - $posStep4);

// Relabel headers
$step4Code = str_replace(
    '// 4. Check Regional & KCP Alias Dictionary (Mapping sub-districts and KCPs to governing KC/KCU)',
    '// 3. Check Regional & KCP Alias Dictionary (Mapping sub-districts and KCPs to governing KC/KCU)',
    $step4Code
);

// Add Kalbar / Kubu Raya / Padang Tikar aliases
$step4Code = str_replace(
    "'PADANG TUALANG' => 'BINJAI',",
    "'PADANG TIKAR' => 'PONTIANAK',\n            'PADANGTIKAR' => 'PONTIANAK',\n            'BATU AMPAR' => 'PONTIANAK',\n            'BATUAMPAR' => 'PONTIANAK',\n            'RASAU JAYA' => 'PONTIANAK',\n            'RASAUJAYA' => 'PONTIANAK',\n            'KUBU RAYA' => 'PONTIANAK',\n            'KUBU' => 'PONTIANAK',\n            'SUNGAI KAKAP' => 'PONTIANAK',\n            'SUNGAI RAYA' => 'PONTIANAK',\n            'TERENTANG' => 'PONTIANAK',\n            'KUALA MANDOR' => 'PONTIANAK',\n            'TELUK PAKEDAI' => 'PONTIANAK',\n            'PADANG TUALANG' => 'BINJAI',",
    $step4Code
);

$step3Code = str_replace(
    '// 3. Search by city name keywords from in-memory collection (KC / KCU / SPP only, never DC)',
    '// 4. Search by city name keywords from in-memory collection (KC / KCU / SPP only, never DC)',
    $step3Code
);

// Reassemble code
$newCode = substr($code, 0, $posStep3) . $step4Code . "\n        " . $step3Code . substr($code, $posStep5);

// Add 78385 to postalPrefixes
$newCode = str_replace(
    "'78' => 'PONTIANAK',",
    "'78385' => 'PONTIANAK', '78382' => 'PONTIANAK', '78383' => 'PONTIANAK', '78384' => 'PONTIANAK',\n            '78' => 'PONTIANAK',",
    $newCode
);

file_put_contents($file, $newCode);
echo "Successfully updated PostOffice.php!\n";
