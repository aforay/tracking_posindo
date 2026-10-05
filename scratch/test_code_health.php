<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "1. Bootstrapping Laravel: OK\n";

// Test syntax of all PHP files
$paths = ['app', 'config', 'routes'];
$errors = 0;
foreach ($paths as $p) {
    $dir = __DIR__ . '/../' . $p;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $out = [];
            $ret = 0;
            exec('php -l "' . $f->getPathname() . '"', $out, $ret);
            if ($ret !== 0) {
                echo "SYNTAX ERROR: " . $f->getPathname() . "\n" . implode("\n", $out) . "\n";
                $errors++;
            }
        }
    }
}
if ($errors === 0) {
    echo "2. All PHP Files Syntax: 100% OK\n";
}

// Test database connection and queries
try {
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "3. Database Connection: OK\n";
    $count = \App\Models\OutgoingShipment::count();
    echo "   Total Shipments in DB: " . $count . "\n";
    $poCount = \App\Models\PostOffice::count();
    echo "   Total Post Offices in DB: " . $poCount . "\n";
} catch (\Throwable $e) {
    echo "3. Database Connection ERROR: " . $e->getMessage() . "\n";
}

// Test route list
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    echo "4. Registered Routes Count: " . count($routes) . " - OK\n";
} catch (\Throwable $e) {
    echo "4. Routes ERROR: " . $e->getMessage() . "\n";
}

echo "HEALTH CHECK COMPLETED!\n";
