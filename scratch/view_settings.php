<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$settings = \App\Models\SystemSetting::all();
foreach ($settings as $s) {
    echo $s->key . ' => ' . substr($s->value, 0, 90) . PHP_EOL;
}
