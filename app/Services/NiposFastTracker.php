<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NiposFastTracker
{
    /**
     * Endpoint NIPos AJAX internal
     */
    protected string $url;

    public function __construct(?string $url = null)
    {
        $this->url = $url ?: config('services.nipos.url', 'https://pid.posindonesia.co.id/lacak/admin/lacak_item_banyakzaref.php');
    }

    /**
     * Track list of resis chunked into safe 60 per request to avoid Posindo gateway timeouts
     *
     * @param array $resis
     * @param int $chunkSize
     * @return array Map of [resi => ['resi' => ..., 'status_akhir' => ...]]
     */
    public function trackMany(array $resis, int $chunkSize = 60): array
    {
        $cleanResis = array_values(array_unique(array_filter(array_map('trim', $resis))));
        if (empty($cleanResis)) {
            return [];
        }

        $chunks = array_chunk($cleanResis, max(1, $chunkSize));

        // If single chunk, execute directly
        if (count($chunks) === 1) {
            return $this->trackChunk($chunks[0]);
        }

        // Multiple chunks: execute concurrently via Http::pool for blazing speed
        $allResults = [];
        try {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($chunks) {
                $poolRequests = [];
                foreach ($chunks as $idx => $chunk) {
                    $vBarcode = implode("\n", $chunk);
                    $headers = [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'application/json, text/javascript, text/html, */*; q=0.01',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ];
                    $cookie = \App\Models\SystemSetting::getNiposCookie();
                    if (!empty($cookie)) {
                        $headers['Cookie'] = $cookie;
                    }

                    $poolRequests[$idx] = $pool->asForm()
                        ->withoutVerifying()
                        ->connectTimeout(8)
                        ->timeout(30)
                        ->withHeaders($headers)
                        ->post($this->url, [
                            'vBarcode' => $vBarcode,
                        ]);
                }
                return $poolRequests;
            });

            foreach ($responses as $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                    $parsed = $this->parseHtmlResponse((string)$response->body());
                    foreach ($parsed as $resi => $data) {
                        $allResults[$resi] = $data;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("NiposFastTracker: Pool request failed, falling back to sequential: " . $e->getMessage());
            foreach ($chunks as $chunk) {
                $chunkResult = $this->trackChunk($chunk);
                foreach ($chunkResult as $resi => $data) {
                    $allResults[$resi] = $data;
                }
            }
        }

        return $this->resolveGoverningOfficesFromTimelines($allResults);
    }

    /**
     * Track single resi for quick testing
     *
     * @param string $resi
     * @return array|null
     */
    public function trackSingle(string $resi): ?array
    {
        $results = $this->trackChunk([trim($resi)]);
        $cleanResi = trim($resi);
        return $results[$cleanResi] ?? null;
    }

    /**
     * Track a single chunk (up to 40 resis) via POST request
     *
     * @param array $chunk
     * @return array
     */
    public function trackChunk(array $chunk): array
    {
        $cleanChunk = array_values(array_filter(array_map('trim', $chunk)));
        if (empty($cleanChunk)) {
            return [];
        }

        // vBarcode expects resis separated by newline
        $vBarcode = implode("\n", $cleanChunk);

        try {
            // Internal Wi-Fi call: No session cookie required
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/javascript, text/html, */*; q=0.01',
                'X-Requested-With' => 'XMLHttpRequest',
            ];
            $cookie = \App\Models\SystemSetting::getNiposCookie();
            if (!empty($cookie)) {
                $headers['Cookie'] = $cookie;
            }

            $response = Http::withoutVerifying()
                ->timeout(30)
                ->asForm()
                ->withHeaders($headers)
                ->post($this->url, [
                    'vBarcode' => $vBarcode,
                ]);

            // Auto-heal session: jika session kedaluwarsa atau ditolak, auto refresh cookie & coba ulang
            $rawBody = (string)$response->body();
            if (!$response->successful() || $response->status() === 401 || $response->status() === 403 || str_contains($rawBody, 'login.php') || str_contains(strtolower($rawBody), 'masuk ke sistem')) {
                Log::info("NiposFastTracker: Sesi NIPOS kedaluwarsa/ditolak, mengambil cookie baru secara otomatis...");
                $freshCookie = \App\Models\SystemSetting::refreshNiposCookie(true);
                if (!empty($freshCookie)) {
                    $headers['Cookie'] = $freshCookie;
                    $response = Http::withoutVerifying()
                        ->timeout(30)
                        ->asForm()
                        ->withHeaders($headers)
                        ->post($this->url, [
                            'vBarcode' => $vBarcode,
                        ]);
                    $rawBody = (string)$response->body();
                }
            }

            if (!$response->successful()) {
                Log::warning("NiposFastTracker: HTTP error {$response->status()} for chunk of " . count($cleanChunk) . " resis.");
                return [];
            }

            $rawBody = $response->body();
            $parsed = $this->parseHtmlResponse($rawBody);
            return $this->resolveGoverningOfficesFromTimelines($parsed);

        } catch (Throwable $e) {
            Log::error("NiposFastTracker: Request failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * For packages currently at KCP or DC, fetch their live chronological timeline directly from detail_lacak_banyak.php
     * to resolve the true governing KC/KCU/SPP directly preceding the KCP in the physical delivery timeline.
     */
    public function resolveGoverningOfficesFromTimelines(array $results): array
    {
        $kcpResis = [];
        foreach ($results as $resi => $data) {
            $posisi = strtoupper(trim((string)($data['posisi_akhir'] ?? '')));
            $status = strtoupper(trim((string)($data['status_akhir'] ?? '')));
            $tujuan = strtoupper(trim((string)($data['kantor_tujuan'] ?? '')));

            $isKcpOrDc = str_contains($posisi, 'KCP') ||
                         str_starts_with($posisi, 'DC ') ||
                         str_contains($posisi, ' DC ') ||
                         str_ends_with($posisi, ' DC') ||
                         preg_match('/\b\d{5}B\d\b/i', $posisi);

            // Hub bandara/transit (misal: Soekarno-Hatta, Bandara, dll) dan kiriman INVEHICLE / MANIFEST
            $isAirportOrTransit = str_contains($posisi, 'SOEKARNO') ||
                                  str_contains($posisi, 'BANDARA') ||
                                  str_contains($posisi, 'AIRPORT') ||
                                  str_contains($posisi, 'JAKARTASOEKARNO') ||
                                  str_contains($tujuan, 'SOEKARNO') ||
                                  str_contains($tujuan, 'JAKARTASOEKARNO') ||
                                  str_contains($status, 'INVEHICLE') ||
                                  str_contains($status, 'MANIFEST');

            if ($isKcpOrDc || $isAirportOrTransit) {
                $kcpResis[] = $resi;
            }
        }

        if (empty($kcpResis)) {
            return $results;
        }

        $detailBaseUrl = 'https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php';
        $cookie = \App\Models\SystemSetting::getNiposCookie();
        $botService = app(\App\Services\TrackingBotService::class);

        try {
            $timelineResponses = Http::pool(function (Pool $pool) use ($kcpResis, $detailBaseUrl, $cookie) {
                foreach ($kcpResis as $resi) {
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
            Log::warning("NiposFastTracker: Timeline extraction failed: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Parse HTML response using Dynamic Column Mapping based on header names:
     * - "BARCODE"
     * - "STATUS AKHIR"
     *
     * Returns raw status_akhir string without any classification.
     *
     * @param string $rawBody
     * @return array
     */
    public function parseHtmlResponse(string $rawBody): array
    {
        $html = $rawBody;

        // Check if response is JSON containing HTML in desk_mess or similar key
        if (str_starts_with(trim($rawBody), '{')) {
            $json = json_decode($rawBody, true);
            if (is_array($json) && !empty($json['desk_mess'])) {
                $html = $json['desk_mess'];
            }
        }

        if (empty($html) || !str_contains($html, '<table')) {
            return [];
        }

        $results = [];

        // Suppress HTML parsing warnings
        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($dom);

        // 1. DYNAMIC COLUMN MAPPING:
        // Find the header row (th or first tr with th/td)
        $headerNodes = $xpath->query('//table//tr[th]');
        if ($headerNodes->length === 0) {
            $headerNodes = $xpath->query('//table//tr[1]');
        }

        $barcodeColIndex = null;
        $statusAkhirColIndex = null;
        $slaColIndex = null;

        if ($headerNodes->length > 0) {
            $headerRow = $headerNodes->item(0);
            $headerCols = $xpath->query('.//th | .//td', $headerRow);

            foreach ($headerCols as $index => $col) {
                $headerText = strtoupper($this->sanitizeText($col->textContent));

                // Find BARCODE column (avoid index column "NO")
                if ($barcodeColIndex === null && (str_contains($headerText, 'BARCODE') || str_contains($headerText, 'RESI'))) {
                    $barcodeColIndex = $index;
                }

                // Find EXTID column
                if (!isset($extIdColIndex) && str_contains($headerText, 'EXTID')) {
                    $extIdColIndex = $index;
                }

                // Find STATUS AKHIR column
                if ($statusAkhirColIndex === null && str_contains($headerText, 'STATUS AKHIR')) {
                    $statusAkhirColIndex = $index;
                }

                // Find POSISI AKHIR column (Kantor Pos Tujuan / Lokasi Akhir)
                if (!isset($posisiAkhirColIndex) && (str_contains($headerText, 'POSISI AKHIR') || str_contains($headerText, 'POSISI') || str_contains($headerText, 'KANTOR TUJUAN'))) {
                    $posisiAkhirColIndex = $index;
                }

                // Find KANTOR KIRIM column
                if (!isset($kantorKirimColIndex) && str_contains($headerText, 'KANTOR KIRIM')) {
                    $kantorKirimColIndex = $index;
                }

                // Find PENERIMA column
                if (!isset($penerimaColIndex) && str_contains($headerText, 'PENERIMA') && !str_contains($headerText, 'TLP')) {
                    $penerimaColIndex = $index;
                }

                // Find SLA column
                if ($slaColIndex === null && (str_contains($headerText, 'SLA') || str_contains($headerText, 'MASA TAHAN'))) {
                    $slaColIndex = $index;
                }

                // Find Tanggal Kolekting column
                if (!isset($tglKolektingColIndex) && (str_contains($headerText, 'KOLEKTING') || str_contains($headerText, 'TGL KIRIM') || str_contains($headerText, 'TANGGAL KIRIM'))) {
                    $tglKolektingColIndex = $index;
                }

                // Find IRREGULARITY column
                if (!isset($irregularityColIndex) && str_contains($headerText, 'IRREGULARITY')) {
                    $irregularityColIndex = $index;
                }

                // Find STATUS COD column
                if (!isset($statusCodColIndex) && (str_contains($headerText, 'STATUS COD') || (str_contains($headerText, 'COD') && !str_contains($headerText, 'NON')))) {
                    $statusCodColIndex = $index;
                }
            }
        }

        // Fallbacks if headers were not explicitly matched:
        // Default Pos Indonesia table layout:
        // Index 1 = Barcode
        // Index 2 = ExtID
        // Index 4 = Tanggal Kolekting
        // Index 5 = Kantor Kirim
        // Index 7 or Index 5 = Status Akhir
        // Index 9 = Posisi Akhir (Kantor Tujuan)
        // Index 12 = Penerima
        // Index 14 = SLA
        if ($barcodeColIndex === null) {
            $barcodeColIndex = 1;
        }
        if (!isset($extIdColIndex)) {
            $extIdColIndex = 2;
        }
        if (!isset($tglKolektingColIndex)) {
            $tglKolektingColIndex = 4;
        }
        if (!isset($kantorKirimColIndex)) {
            $kantorKirimColIndex = 5;
        }
        if ($statusAkhirColIndex === null) {
            $statusAkhirColIndex = 7;
        }
        if (!isset($posisiAkhirColIndex)) {
            $posisiAkhirColIndex = 9;
        }
        if (!isset($penerimaColIndex)) {
            $penerimaColIndex = 12;
        }
        if ($slaColIndex === null) {
            $slaColIndex = 14;
        }
        if (!isset($irregularityColIndex)) {
            $irregularityColIndex = 8;
        }
        if (!isset($statusCodColIndex)) {
            $statusCodColIndex = 17;
        }

        // 2. EXTRACT DATA ROWS:
        // Find all data rows (tr containing td)
        $dataRows = $xpath->query('//table//tbody//tr | //table//tr[td]');

        foreach ($dataRows as $row) {
            $cols = $xpath->query('.//td', $row);
            if ($cols->length <= max($barcodeColIndex, $statusAkhirColIndex)) {
                continue;
            }

            $resiRaw = $cols->item($barcodeColIndex)?->textContent ?? '';

            // Jika cell Barcode kosong (sering terjadi pada format Posindo), ambil dari kolom ExtID
            if (empty(trim($resiRaw)) && isset($extIdColIndex) && $cols->length > $extIdColIndex) {
                $resiRaw = $cols->item($extIdColIndex)?->textContent ?? '';
            }
            if (empty(trim($resiRaw)) && $cols->length > 2) {
                $resiRaw = $cols->item(2)?->textContent ?? '';
            }

            $statusAkhirRaw = $cols->item($statusAkhirColIndex)?->textContent ?? '';

            // Fallback for compact table where Status Akhir might be at index 5
            if (empty(trim($statusAkhirRaw)) && $cols->length > 5) {
                $statusAkhirRaw = $cols->item(5)?->textContent ?? '';
            }

            // Extract Posisi Akhir (Kantor Tujuan dari NIPOS)
            $posisiAkhirRaw = (isset($posisiAkhirColIndex) && $cols->length > $posisiAkhirColIndex)
                ? ($cols->item($posisiAkhirColIndex)?->textContent ?? '')
                : ($cols->length > 9 ? ($cols->item(9)?->textContent ?? '') : '');

            // Extract Kantor Kirim
            $kantorKirimRaw = (isset($kantorKirimColIndex) && $cols->length > $kantorKirimColIndex)
                ? ($cols->item($kantorKirimColIndex)?->textContent ?? '')
                : '';

            // Extract Penerima
            $penerimaRaw = (isset($penerimaColIndex) && $cols->length > $penerimaColIndex)
                ? ($cols->item($penerimaColIndex)?->textContent ?? '')
                : '';

            // Extract SLA raw text from mapped column
            $slaRaw = ($slaColIndex !== null && $cols->length > $slaColIndex)
                ? ($cols->item($slaColIndex)?->textContent ?? '')
                : '';

            // Extract Tanggal Kolekting raw text from mapped column
            $tglKolektingRaw = (isset($tglKolektingColIndex) && $cols->length > $tglKolektingColIndex)
                ? ($cols->item($tglKolektingColIndex)?->textContent ?? '')
                : '';

            // Extract Irregularity raw text
            $irregularityRaw = (isset($irregularityColIndex) && $cols->length > $irregularityColIndex)
                ? ($cols->item($irregularityColIndex)?->textContent ?? '')
                : '';

            // Extract Status COD raw text
            $statusCodRaw = (isset($statusCodColIndex) && $cols->length > $statusCodColIndex)
                ? ($cols->item($statusCodColIndex)?->textContent ?? '')
                : '';

            $resi = $this->sanitizeText($resiRaw);
            $statusAkhir = $this->sanitizeText($statusAkhirRaw);
            $posisiAkhir = $this->sanitizeText($posisiAkhirRaw);
            $kantorKirim = $this->sanitizeText($kantorKirimRaw);
            $penerima = $this->sanitizeText($penerimaRaw);
            $sla = $this->sanitizeText($slaRaw);
            $tglKolekting = $this->sanitizeText($tglKolektingRaw);
            $irregularity = $this->sanitizeText($irregularityRaw);
            $statusCod = $this->sanitizeText($statusCodRaw);

            // KCP and DC cannot be used for follow-ups; resolve to governing KC/KCU/SPP
            $cleanTujuan = $posisiAkhir;
            if (!empty($posisiAkhir)) {
                $pUpper = strtoupper($posisiAkhir);
                $isKcpOrDc = str_contains($pUpper, 'KCP') ||
                             str_starts_with($pUpper, 'DC ') ||
                             str_contains($pUpper, ' DC ') ||
                             str_ends_with($pUpper, ' DC') ||
                             preg_match('/\b\d{5}B\d\b/i', $pUpper);
                if ($isKcpOrDc) {
                    $matched = \App\Models\PostOffice::matchByDestinationOrAddress($posisiAkhir, $penerima);
                    if ($matched && !str_starts_with(strtoupper($matched->name), 'DC ')) {
                        $cleanTujuan = $matched->name;
                    }
                }
            }

            if (!empty($resi) && !in_array(strtoupper($resi), ['BARCODE', 'NO', 'RESI', 'STATUS AKHIR'])) {
                $slaInt = is_numeric($sla) ? (int)$sla : (preg_match('/(\d+)/', $sla, $sm) ? (int)$sm[1] : null);

                $cleanPenerima = trim(preg_replace('/^[\s\-\,\:]+/', '', (string)$penerima));
                if ($cleanPenerima === '-') {
                    $cleanPenerima = '';
                }

                $isRetur = str_contains(strtoupper($irregularity), 'RETUR') ||
                           str_contains(strtoupper($irregularity), 'IRREGULARITY') ||
                           str_contains(strtoupper($statusCod), 'RETUR') ||
                           str_contains(strtoupper($statusAkhir), 'RETUR') ||
                           str_contains(strtoupper($statusAkhir), 'RETURN') ||
                           str_contains(strtoupper($statusAkhir), 'DITOLAK') ||
                           str_contains(strtoupper($statusAkhir), 'IRREGULARITY') ||
                           str_contains(strtoupper($cleanPenerima), 'DITOLAK') ||
                           str_contains(strtoupper($cleanPenerima), 'RETUR') ||
                           str_contains(strtoupper($cleanPenerima), 'TOLAK');

                $effectiveStatusAkhir = $statusAkhir;
                if ($isRetur && !str_contains(strtoupper($statusAkhir), 'RETUR') && !str_contains(strtoupper($statusAkhir), 'RETURN')) {
                    $effectiveStatusAkhir = !empty($irregularity)
                        ? "RETUR BARANG ({$statusAkhir})"
                        : "RETUR ({$statusAkhir})";
                }

                $results[$resi] = [
                    'resi' => $resi,
                    'status_akhir' => $effectiveStatusAkhir,
                    'is_retur' => $isRetur,
                    'irregularity' => $irregularity,
                    'status_cod' => $statusCod,
                    'posisi_akhir' => $posisiAkhir,
                    'kantor_tujuan' => $cleanTujuan,
                    'last_location' => $posisiAkhir,
                    'kantor_kirim' => $kantorKirim,
                    'penerima' => $cleanPenerima ?: $penerima,
                    'sla' => $sla,
                    'sla_days' => $slaInt,
                    'tanggal_kolekting' => $tglKolekting,
                    'tanggal_kirim' => $tglKolekting,
                    'barcode_index' => $barcodeColIndex,
                    'status_akhir_index' => $statusAkhirColIndex,
                    'posisi_akhir_index' => $posisiAkhirColIndex,
                    'sla_index' => $slaColIndex,
                ];
            }
        }

        return $results;
    }

    /**
     * Clean and normalize raw text: trim and collapse excess whitespace
     */
    public function sanitizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        // Remove non-breaking spaces and collapse multiple spaces/newlines
        $clean = str_replace(["\xc2\xa0", "&nbsp;"], ' ', $text);
        $clean = preg_replace('/\s+/', ' ', $clean);

        return trim($clean);
    }
}
