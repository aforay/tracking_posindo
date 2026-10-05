<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ShipmentLog;

$logs = ShipmentLog::where('shipment_id', 2011531)->get()->toArray();
echo "Logs for 2011531:\n" . json_encode($logs, JSON_PRETTY_PRINT) . "\n";

$recentLogs = ShipmentLog::where('action', 'like', '%FU%')->orWhere('action', 'like', '%color%')->latest()->take(10)->get()->toArray();
echo "Recent FU logs:\n" . json_encode($recentLogs, JSON_PRETTY_PRINT) . "\n";
