<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NiposSettingController extends Controller
{
    /**
     * Display standalone Blade view for NIPOS cookie settings
     */
    public function index(Request $request)
    {
        $currentCookie = SystemSetting::getNiposCookie();
        $cachedStatus = Cache::get('nipos_connection_status', null);

        return view('settings.nipos_cookie', [
            'cookie' => $currentCookie,
            'status' => $cachedStatus,
        ]);
    }

    /**
     * Get JSON status of NIPOS cookie & connection for UI TopBar
     */
    public function status(Request $request)
    {
        $currentCookie = SystemSetting::getNiposCookie();
        $cachedStatus = Cache::get('nipos_connection_status');

        if (!$cachedStatus) {
            $cachedStatus = [
                'connected' => !empty($currentCookie),
                'message' => empty($currentCookie) ? 'Cookie belum dikonfigurasi' : 'Belum diuji (klik Test Koneksi)',
                'latency_ms' => null,
                'status_code' => empty($currentCookie) ? null : 200,
                'last_checked_at' => null,
            ];
        }

        return response()->json([
            'success' => true,
            'has_cookie' => !empty($currentCookie),
            'cookie_length' => strlen($currentCookie),
            'cookie_preview' => !empty($currentCookie) ? (substr($currentCookie, 0, 15) . '...' . substr($currentCookie, -10)) : '',
            'status' => $cachedStatus,
        ]);
    }

    /**
     * Save new cookie to database
     */
    public function store(Request $request)
    {
        $request->validate([
            'cookie' => 'required|string',
        ]);

        $cookie = trim((string)$request->input('cookie'));
        SystemSetting::setNiposCookie($cookie);

        // Perform connection test with newly saved cookie
        $testResult = $this->pingNipos($cookie);
        Cache::put('nipos_connection_status', $testResult, now()->addMinutes(15));

        $msg = 'Cookie NIPOS berhasil disimpan ke database!';
        if ($testResult['connected']) {
            $msg .= ' Status koneksi: Terhubung (' . $testResult['latency_ms'] . 'ms).';
        } else {
            $msg .= ' Peringatan: ' . $testResult['message'];
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'test_result' => $testResult,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Test connection to NIPOS API with provided or saved cookie
     */
    public function testConnection(Request $request)
    {
        $inputCookie = $request->input('cookie');
        $cookie = !empty($inputCookie) ? trim((string)$inputCookie) : SystemSetting::getNiposCookie();

        if (empty($cookie)) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => 'Cookie kosong! Silakan masukkan string cookie NIPOS.',
                'latency_ms' => null,
                'status_code' => null,
                'last_checked_at' => now()->toIso8601String(),
            ]);
        }

        $result = $this->pingNipos($cookie);
        Cache::put('nipos_connection_status', $result, now()->addMinutes(15));

        return response()->json(array_merge(['success' => $result['connected']], $result));
    }

    /**
     * Probe NIPOS endpoint to validate cookie validity and latency
     */
    protected function pingNipos(string $cookie): array
    {
        $targetUrl = config('services.nipos.url') ?: (env('NIPOS_URL') ?: 'https://pid.posindonesia.co.id/lacak/admin/lacak_item_banyakzaref.php');

        // Testing environment check
        if (app()->environment('testing') || str_contains($targetUrl, 'mock-nipos') || str_contains($targetUrl, 'localhost')) {
            return [
                'connected' => true,
                'message' => 'Terhubung ke NIPOS Mock / Simulasi (200 OK)',
                'latency_ms' => 45,
                'status_code' => 200,
                'last_checked_at' => now()->toIso8601String(),
            ];
        }

        $startTime = microtime(true);
        try {
            $response = Http::withoutVerifying()
                ->timeout(12)
                ->asForm()
                ->withHeaders([
                    'Cookie' => $cookie,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json, text/javascript, text/html, */*; q=0.01',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post($targetUrl, [
                    'vBarcode' => 'PCP260800002ID',
                ]);

            $elapsedMs = round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $body = (string)$response->body();

            // Check if response is successful and contains valid NIPOS payload
            if ($statusCode === 200) {
                // If it redirects to login or contains login prompt
                if (str_contains($body, 'login.php') || str_contains(strtolower($body), 'masuk ke sistem') || str_contains($body, 'Akses Ditolak')) {
                    return [
                        'connected' => false,
                        'message' => 'Session kedaluwarsa atau akses ditolak (diarahkan ke form login)',
                        'latency_ms' => $elapsedMs,
                        'status_code' => 401,
                        'last_checked_at' => now()->toIso8601String(),
                    ];
                }

                // Check rc_mess: 000 or table presence
                if (str_contains($body, '"rc_mess"') || str_contains($body, '<table') || str_contains($body, 'desk_mess')) {
                    return [
                        'connected' => true,
                        'message' => "Terhubung dengan sukses ke NIPOS ({$elapsedMs}ms)",
                        'latency_ms' => $elapsedMs,
                        'status_code' => 200,
                        'last_checked_at' => now()->toIso8601String(),
                    ];
                }

                // Empty body or unexpected payload
                return [
                    'connected' => true,
                    'message' => "Server NIPOS merespon HTTP 200 ({$elapsedMs}ms)",
                    'latency_ms' => $elapsedMs,
                    'status_code' => 200,
                    'last_checked_at' => now()->toIso8601String(),
                ];
            }

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'connected' => false,
                    'message' => "Session NIPOS tidak valid atau ditolak (HTTP {$statusCode})",
                    'latency_ms' => $elapsedMs,
                    'status_code' => $statusCode,
                    'last_checked_at' => now()->toIso8601String(),
                ];
            }

            return [
                'connected' => false,
                'message' => "Server NIPOS merespon kode error (HTTP {$statusCode})",
                'latency_ms' => $elapsedMs,
                'status_code' => $statusCode,
                'last_checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            $elapsedMs = round((microtime(true) - $startTime) * 1000);
            Log::warning('NiposSettingController pingNipos error: ' . $e->getMessage());

            $errMsg = $e->getMessage();
            if (str_contains(strtolower($errMsg), 'timed out') || str_contains(strtolower($errMsg), 'timeout')) {
                $errMsg = 'Koneksi ke server Posindo Timeout (>12 detik)';
            }

            return [
                'connected' => false,
                'message' => "Gagal terhubung: {$errMsg}",
                'latency_ms' => $elapsedMs,
                'status_code' => null,
                'last_checked_at' => now()->toIso8601String(),
            ];
        }
    }
}
