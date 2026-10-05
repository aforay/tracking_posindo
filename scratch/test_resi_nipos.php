<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SystemSetting;
use App\Services\TrackingBotService;
use Illuminate\Support\Facades\Http;

$bot = app(TrackingBotService::class);
$cookie = SystemSetting::getNiposCookie();
$resp = Http::withoutVerifying()
    ->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
        'Cookie' => $cookie
    ])
    ->get('https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php', [
        'id' => base64_encode('BAC230926325CC947923')
    ]);

echo "HTTP Status: " . $resp->status() . "\n";
$html = $resp->body();

$dom = new \DOMDocument();
@$dom->loadHTML($html);
$xpath = new \DOMXPath($dom);
$tables = $xpath->query('//table');
echo "Found " . $tables->length . " tables\n";
for ($t = 0; $t < $tables->length; $t++) {
    $rows = $xpath->query('.//tr', $tables->item($t));
    echo "--- Table $t (" . $rows->length . " rows) ---\n";
    foreach ($rows as $idx => $r) {
        $txt = trim(preg_replace('/\s+/', ' ', $r->textContent));
        echo "  Row $idx: " . $txt . "\n";
    }
}

$botOutput = $bot->extractKantorTujuan('', $html);
echo "\nBot extracted office: " . var_export($botOutput, true) . "\n";
