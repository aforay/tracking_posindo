<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$code = file_get_contents('app/Models/PostOffice.php');

// Let's swap Step 3 (city search) and Step 4 ($aliases)
// In PostOffice.php:
// Find step 3: "// 3. Search by city name keywords"
// Find step 4: "// 4. Check Regional & KCP Alias Dictionary"
// Find step 5: "// 5. Fallback via Postal Code Prefix"

$posStep3 = strpos($code, '// 3. Search by city name keywords');
$posStep4 = strpos($code, '// 4. Check Regional & KCP Alias Dictionary');
$posStep5 = strpos($code, '// 5. Fallback via Postal Code Prefix');

$step3Code = substr($code, $posStep3, $posStep4 - $posStep3);
$step4Code = substr($code, $posStep4, $posStep5 - $posStep4);

// Add Padang Tikar to step4Code
$step4Code = str_replace(
    "'PADANG TUALANG' => 'BINJAI',",
    "'PADANG TIKAR' => 'PONTIANAK',\n            'PADANGTIKAR' => 'PONTIANAK',\n            'BATU AMPAR' => 'PONTIANAK',\n            'PADANG TUALANG' => 'BINJAI',",
    $step4Code
);

// New code with Step 4 BEFORE Step 3
$newCode = substr($code, 0, $posStep3) . $step4Code . $step3Code . substr($code, $posStep5);
$newCode = str_replace('class PostOffice extends Model', 'class PostOfficePadangTest3 extends \App\Models\PostOffice', $newCode);
$newCode = preg_replace('/namespace\s+App\\\Models;/', '', $newCode);

eval('?>' . $newCode);

$cases = [
    ['KCP PADANG TIKAR 78385', 'Desa Batu ampar dusun sungai limau Kubu Raya'],
    ['KCP RASAU JAYA 78382', 'Rasau Jaya Kubu Raya'],
    ['KCU PADANG 25000', 'Jl Bagindo Aziz Chan Padang'],
    ['KCP PADANG TUALANG 20853', 'Padang Tualang Langkat'],
    ['KCP PADANG RATU 34176', 'Padang Ratu Lampung Tengah'],
    ['KCP MUARAANCALONG 75556', 'Muara Bengkal'],
    ['KCP BINTUNI 98364', 'Babo Bintuni'],
];

echo "--- With Aliases BEFORE Generic City Search ---\n";
foreach ($cases as [$target, $addr]) {
    $matched = PostOfficePadangTest3::matchByDestinationOrAddress($target, $addr);
    echo "$target | $addr => " . ($matched ? $matched->name : 'NULL') . "\n";
}
