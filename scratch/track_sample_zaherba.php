<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\NiposFastTracker;

$resis = [
    'BAC01102611804F885B8',
    'BAC011026117E210C6ED',
    'BAC01102655854FB9CB0',
    'BAC01102658D027AEC79',
];

$tracker = app(NiposFastTracker::class);
$results = $tracker->trackChunk($resis);

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
