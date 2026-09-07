<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
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
     * Track list of resis chunked into 40 per request
     *
     * @param array $resis
     * @param int $chunkSize
     * @return array Map of [resi => ['resi' => ..., 'status_akhir' => ...]]
     */
    public function trackMany(array $resis, int $chunkSize = 40): array
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
                        ->timeout(25)
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

        return $allResults;
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
                ->timeout(20)
                ->asForm()
                ->withHeaders($headers)
                ->post($this->url, [
                    'vBarcode' => $vBarcode,
                ]);

            if (!$response->successful()) {
                Log::warning("NiposFastTracker: HTTP error {$response->status()} for chunk of " . count($cleanChunk) . " resis.");
                return [];
            }

            $rawBody = $response->body();
            return $this->parseHtmlResponse($rawBody);

        } catch (Throwable $e) {
            Log::error("NiposFastTracker: Request failed: " . $e->getMessage());
            return [];
        }
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

                // Find STATUS AKHIR column
                if ($statusAkhirColIndex === null && str_contains($headerText, 'STATUS AKHIR')) {
                    $statusAkhirColIndex = $index;
                }

                // Find SLA column
                if ($slaColIndex === null && (str_contains($headerText, 'SLA') || str_contains($headerText, 'MASA TAHAN'))) {
                    $slaColIndex = $index;
                }

                // Find Tanggal Kolekting column
                if (!isset($tglKolektingColIndex) && (str_contains($headerText, 'KOLEKTING') || str_contains($headerText, 'TGL KIRIM') || str_contains($headerText, 'TANGGAL KIRIM'))) {
                    $tglKolektingColIndex = $index;
                }
            }
        }

        // Fallbacks if headers were not explicitly matched:
        // Default Pos Indonesia table layout:
        // Index 1 = Barcode
        // Index 4 = Tanggal Kolekting
        // Index 7 or Index 5 = Status Akhir
        // Index 14 = SLA
        if ($barcodeColIndex === null) {
            $barcodeColIndex = 1;
        }
        if (!isset($tglKolektingColIndex)) {
            $tglKolektingColIndex = 4;
        }
        if ($statusAkhirColIndex === null) {
            $statusAkhirColIndex = 7;
        }
        if ($slaColIndex === null) {
            $slaColIndex = 14;
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
            $statusAkhirRaw = $cols->item($statusAkhirColIndex)?->textContent ?? '';

            // Fallback for compact table where Status Akhir might be at index 5
            if (empty(trim($statusAkhirRaw)) && $cols->length > 5) {
                $statusAkhirRaw = $cols->item(5)?->textContent ?? '';
            }

            // Extract SLA raw text from mapped column
            $slaRaw = ($slaColIndex !== null && $cols->length > $slaColIndex)
                ? ($cols->item($slaColIndex)?->textContent ?? '')
                : '';

            // Extract Tanggal Kolekting raw text from mapped column
            $tglKolektingRaw = (isset($tglKolektingColIndex) && $cols->length > $tglKolektingColIndex)
                ? ($cols->item($tglKolektingColIndex)?->textContent ?? '')
                : '';

            $resi = $this->sanitizeText($resiRaw);
            $statusAkhir = $this->sanitizeText($statusAkhirRaw);
            $sla = $this->sanitizeText($slaRaw);
            $tglKolekting = $this->sanitizeText($tglKolektingRaw);

            if (!empty($resi) && !in_array(strtoupper($resi), ['BARCODE', 'NO', 'RESI', 'STATUS AKHIR'])) {
                $results[$resi] = [
                    'resi' => $resi,
                    'status_akhir' => $statusAkhir,
                    'sla' => $sla,
                    'tanggal_kolekting' => $tglKolekting,
                    'barcode_index' => $barcodeColIndex,
                    'status_akhir_index' => $statusAkhirColIndex,
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
