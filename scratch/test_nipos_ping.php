<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SystemSetting;
use App\Http\Controllers\NiposSettingController;

$controller = app(NiposSettingController::class);
$cookie = SystemSetting::getNiposCookie();

echo "Cookie: {$cookie}" . PHP_EOL;

$reflector = new ReflectionObject($controller);
$method = $reflector->getMethod('pingNipos');
$method->setAccessible(true);

$res = $method->invoke($controller, $cookie);
echo "=== PING NIPOS RESULT ===" . PHP_EOL;
dump($res);
