<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$resi = 'BAC021026416B8347020';

// Test with updated condition
class TestFastTracker extends \App\Services\NiposFastTracker {
    public function resolveGoverningOfficesFromTimelines(array $results): array
    {
        $needTimelineResis = [];
        foreach ($results as $resi => $data) {
            $posisi = strtoupper(trim((string)($data['posisi_akhir'] ?? '')));
            $status = strtoupper(trim((string)($data['status_akhir'] ?? '')));
            $tujuan = strtoupper(trim((string)($data['kantor_tujuan'] ?? '')));

            $isKcpOrDc = str_contains($posisi, 'KCP') ||
                         str_starts_with($posisi, 'DC ') ||
                         str_contains($posisi, ' DC ') ||
                         str_ends_with($posisi, ' DC') ||
                         preg_match('/\b\d{5}B\d\b/i', $posisi);

            $isAirportOrMpc = str_contains($posisi, 'SOEKARNO') ||
                              str_contains($posisi, 'BANDARA') ||
                              str_contains($posisi, 'AIRPORT') ||
                              str_contains($posisi, 'MPC ') ||
                              str_contains($posisi, ' MPC') ||
                              str_contains($tujuan, 'SOEKARNO') ||
                              str_contains($tujuan, 'BANDARA');

            $isInTransit = str_contains($status, 'INVEHICLE') ||
                           str_contains($status, 'MANIFEST') ||
                           str_contains($status, 'TRANSIT');

            if ($isKcpOrDc || $isAirportOrMpc || $isInTransit) {
                $needTimelineResis[] = $resi;
            }
        }

        if (empty($needTimelineResis)) {
            return $results;
        }

        $detailBaseUrl = 'https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php';
        $cookie = \App\Models\SystemSetting::getNiposCookie();
        $botService = app(\App\Services\TrackingBotService::class);

        try {
            $timelineResponses = \Illuminate\Support\Facades\Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($needTimelineResis, $detailBaseUrl, $cookie) {
                foreach ($needTimelineResis as $resi) {
                    $pool->as($resi)
                        ->withoutVerifying()
                        ->connectTimeout(4)
                        ->timeout(10)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'Cookie' => $cookie,
                        ])->get($detailBaseUrl, ['id' => base64_encode($resi)]);
                }
            });

            foreach ($timelineResponses as $resi => $resp) {
                if (isset($results[$resi]) && is_array($results[$resi]) && $resp instanceof \Illuminate\Http\Client\Response && $resp->successful()) {
                    $html = (string)$resp->body();
                    $resolvedKC = $botService->extractKantorTujuan('', $html);
                    if (!empty($resolvedKC) && !str_contains(strtoupper($resolvedKC), 'KCP') && !str_starts_with(strtoupper($resolvedKC), 'DC ')) {
                        $results[$resi]['kantor_tujuan'] = $resolvedKC;
                        $results[$resi]['last_location'] = $resolvedKC;
                    }
                }
            }
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }

        return $results;
    }
}

$tracker = new TestFastTracker();
$res = $tracker->trackChunk([$resi]);
echo "Result:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n";
