<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting !== null ? $setting->value : $default;
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string)$value]
        );
    }

    /**
     * Extract Google Spreadsheet ID from full URL or raw ID
     */
    public static function extractSpreadsheetId(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $input = trim(urldecode((string)$input));

        // Match Google Sheet URL pattern /d/{ID}
        if (preg_match('/\/d\/([a-zA-Z0-9-_]{15,})/', $input, $matches)) {
            return $matches[1];
        }

        // Match key= parameter pattern (older google spreadsheet URL format)
        if (preg_match('/[?&]key=([a-zA-Z0-9-_]{15,})/', $input, $matches)) {
            return $matches[1];
        }

        // Match raw ID pattern
        if (preg_match('/^[a-zA-Z0-9-_]{15,}$/', $input)) {
            return $input;
        }

        return null;
    }

    /**
     * Get active NIPOS session cookie with database priority, auto-refresh, and .env fallback
     */
    public static function getNiposCookie(bool $autoRefreshIfEmpty = true): string
    {
        $cookie = static::get('nipos_session_cookie');
        if (!empty($cookie) && is_string($cookie) && trim($cookie) !== '') {
            return trim($cookie);
        }

        $envCookie = (string)(config('services.nipos.cookie') ?: env('NIPOS_SESSION_COOKIE', ''));
        if (!empty($envCookie) && trim($envCookie) !== '') {
            return trim($envCookie);
        }

        // Otomatis minta cookie baru langsung dari server NIPOS jika belum ada
        if ($autoRefreshIfEmpty) {
            $freshCookie = static::refreshNiposCookie(true);
            if (!empty($freshCookie)) {
                return $freshCookie;
            }
        }

        return '';
    }

    /**
     * Set active NIPOS session cookie in database
     */
    public static function setNiposCookie(string $cookie): void
    {
        static::set('nipos_session_cookie', trim($cookie));
    }

    /**
     * Fetch fresh session cookie directly from NIPOS server without manual intervention
     */
    public static function refreshNiposCookie(bool $saveToDb = true): ?string
    {
        try {
            $url = config('services.nipos.url') ?: (env('NIPOS_URL') ?: 'https://pid.posindonesia.co.id/lacak/admin/lacak_item_banyakzaref.php');

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);

            if ($response->successful()) {
                $cookies = $response->cookies()->toArray();
                $cookieParts = [];
                foreach ($cookies as $c) {
                    if (!empty($c['Name']) && isset($c['Value'])) {
                        $cookieParts[] = $c['Name'] . '=' . $c['Value'];
                    }
                }

                if (!empty($cookieParts)) {
                    $cookieString = implode('; ', $cookieParts);
                    if ($saveToDb) {
                        static::setNiposCookie($cookieString);
                    }
                    Log::info("SystemSetting: Berhasil refresh cookie NIPOS otomatis (" . strlen($cookieString) . " chars)");
                    return $cookieString;
                }
            } else {
                Log::warning("SystemSetting: Gagal request cookie NIPOS, status: " . $response->status());
            }
        } catch (\Throwable $e) {
            Log::error("SystemSetting: Exception saat refresh cookie NIPOS: " . $e->getMessage());
        }

        return null;
    }
}
