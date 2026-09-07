<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NiposApiService
{
    /**
     * Default AJAX endpoint URL for bulk tracking
     */
    public const DEFAULT_URL = 'https://pid.posindonesia.co.id/lacak/admin/lacak_item_banyakzaref.php';

    protected string $url;
    protected string $cookie;

    public function __construct(?string $url = null, ?string $cookie = null)
    {
        $this->url = $url 
            ?: (config('services.nipos.url') 
            ?: (env('NIPOS_URL') 
            ?: self::DEFAULT_URL));

        $this->cookie = $cookie 
            ?: (config('services.nipos.cookie') 
            ?: (env('NIPOS_SESSION_COOKIE') 
            ?: ''));
    }

    /**
     * Set or override cookie session
     */
    public function setCookie(string $cookie): self
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * Track multiple resis via AJAX POST endpoint
     *
     * @param array $resis
     * @return array Map of [resi => tracking_data]
     */
    public function trackMany(array $resis): array
    {
        $cleanResis = [];
        foreach ($resis as $r) {
            $val = self::sanitizeText(is_array($r) ? ($r['no_resi'] ?? ($r['resi'] ?? '')) : (string)$r);
            if (!empty($val)) {
                $cleanResis[] = $val;
            }
        }

        $cleanResis = array_values(array_unique($cleanResis));
        if (empty($cleanResis)) {
            return [];
        }

        $vBarcode = implode("\n", $cleanResis);
        $results = [];

        try {
            $response = Http::withoutVerifying()
                ->timeout(25)
                ->withHeaders([
                    'Cookie' => $this->cookie,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                ])
                ->asForm()
                ->post($this->url, [
                    'vBarcode' => $vBarcode,
                ]);

            if ($response->successful()) {
                $json = $response->json();
                $html = $json['desk_mess'] ?? (string)$response->body();
                $results = $this->parseHtmlTable($html);
            } else {
                Log::warning("NiposApiService::trackMany failed with HTTP {$response->status()}");
            }
        } catch (Throwable $e) {
            Log::error("NiposApiService::trackMany exception: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Track a single resi
     *
     * @param string $resi
     * @return array|null
     */
    public function trackSingle(string $resi): ?array
    {
        $clean = self::sanitizeText($resi);
        if (empty($clean)) {
            return null;
        }

        $results = $this->trackMany([$clean]);
        return $results[$clean] ?? null;
    }

    /**
     * Parse HTML table from NIPos response into structured tracking array
     *
     * @param string $html
     * @return array Map of [resi => array]
     */
    public function parseHtmlTable(string $html): array
    {
        if (empty(trim($html))) {
            return [];
        }

        $dom = new DOMDocument();
        // Use XML encoding trick to prevent mangling of UTF-8 characters
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $rows = $dom->getElementsByTagName('tr');

        $results = [];

        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            if ($cols->length < 2) {
                continue;
            }

            // Kolom 1 ($cols->item(1)): no_resi
            $noResi = self::sanitizeText($cols->item(1)?->textContent ?? '');
            if (empty($noResi) || strtoupper($noResi) === 'BARCODE' || strtoupper($noResi) === 'NO. RESI') {
                continue;
            }

            // Kolom 5 ($cols->item(5)): status_akhir_nipos (raw teks dari NIPos)
            $col5Raw = $cols->item(5) ? self::sanitizeText($cols->item(5)->textContent) : '';
            $col7Raw = $cols->item(7) ? self::sanitizeText($cols->item(7)->textContent) : '';

            // Handle column arrangement: item(7) has Status Akhir if 18 columns, item(5) if compact table
            $statusAkhirNipos = !empty($col7Raw) && in_array(strtoupper($col7Raw), [
                'DELIVERED', 'UNBAG', 'INVEHICLE', 'INLOCATION', 'FAILEDTODELIVERED', 
                'ON PROCESS', 'DELIVERED (RETURN DELIVERY)', 'IRREGULARITY', 'INBAG', 'RUNSHEET',
                'DELIVERYRUNSHEET', 'MISROUTE', 'ANTAR ULANG'
            ]) ? $col7Raw : (!empty($col5Raw) ? $col5Raw : $col7Raw);

            $posisiAkhir = $cols->item(9) ? self::sanitizeText($cols->item(9)->textContent) : '';
            $tglUpdate = $cols->item(10) ? self::sanitizeText($cols->item(10)->textContent) : '';
            $petugasUpdate = $cols->item(11) ? self::sanitizeText($cols->item(11)->textContent) : '';
            $penerima = $cols->item(12) ? self::sanitizeText($cols->item(12)->textContent) : '';
            $sla = $cols->item(14) ? self::sanitizeText($cols->item(14)->textContent) : '';

            $results[$noResi] = [
                'no_resi' => $noResi,
                'status_akhir_nipos' => $statusAkhirNipos,
                'status_pos' => $statusAkhirNipos,
                'keterangan' => $penerima ? "{$statusAkhirNipos} - {$penerima}" : $statusAkhirNipos,
                'posisi_akhir' => $posisiAkhir,
                'tanggal_update' => $tglUpdate,
                'petugas_update' => $petugasUpdate,
                'penerima' => $penerima,
                'sla' => $sla,
                'col_1_raw' => $noResi,
                'col_5_raw' => $col5Raw,
                'col_7_raw' => $col7Raw,
            ];
        }

        return $results;
    }

    /**
     * Sanitasi teks sederhana (trim dan hapus spasi berlebih)
     */
    public static function sanitizeText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $decoded = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $decoded));
    }
}
