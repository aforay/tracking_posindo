<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if (!$user) {
    echo "No user found in database!\n";
    exit(1);
}
echo "Testing with user: " . $user->username . " (role: " . $user->role . ")\n";

$reqAliqa = Illuminate\Http\Request::create('/aliqa', 'GET');
$app->instance('request', $reqAliqa);
$reqAliqa->setUserResolver(function() use ($user) { return $user; });
\Illuminate\Support\Facades\Auth::login($user);
$resAliqa = $kernel->handle($reqAliqa);
echo "HTTP Status for /aliqa: " . $resAliqa->getStatusCode() . "\n";

// Test /zaherba
$reqZaherba = Illuminate\Http\Request::create('/zaherba', 'GET');
$reqZaherba->setUserResolver(function() use ($user) { return $user; });
$resZaherba = $kernel->handle($reqZaherba);
echo "HTTP Status for /zaherba: " . $resZaherba->getStatusCode() . "\n";

// Test /post-offices
$reqPO = Illuminate\Http\Request::create('/post-offices', 'GET');
$reqPO->setUserResolver(function() use ($user) { return $user; });
$resPO = $kernel->handle($reqPO);
echo "HTTP Status for /post-offices: " . $resPO->getStatusCode() . "\n";

if ($resAliqa->getStatusCode() === 200 && $resZaherba->getStatusCode() === 200 && $resPO->getStatusCode() === 200) {
    echo "ALL DASHBOARD & OPERATIONAL ROUTES RETURN HTTP 200 OK!\n";
} else {
    echo "ERROR in some routes!\n";
}
