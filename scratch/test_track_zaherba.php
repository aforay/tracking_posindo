<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SystemSetting;
use App\Services\NiposFastTracker;

// Update cookie if needed
$cookie = 'PHPSESSID=2588dlbs9rvikber4rt0kl5ja6; TS011d97f9=01dc40192a949cd421482ca46a6d75c25c17794c10e7412e3f1bcd27275a8ce21ff2fce97599470e48a621588f8844d776fbd81845';
SystemSetting::setNiposCookie($cookie);

$resis = [
    'BAC01102611804F885B8',
    'BAC01102629DD274FD31',
    'BAC011026117E210C6ED',
    'BAC01102655854FB9CB0',
    'BAC01102658D027AEC79',
];

$tracker = app(NiposFastTracker::class);
$results = $tracker->trackChunk($resis);

echo "Results count: " . count($results) . "\n";
echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
