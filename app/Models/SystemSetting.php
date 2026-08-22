<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

        $input = trim($input);

        // Match Google Sheet URL pattern /d/{ID}
        if (preg_match('/\/d\/([a-zA-Z0-9-_]{20,})/', $input, $matches)) {
            return $matches[1];
        }

        // Match raw ID pattern
        if (preg_match('/^[a-zA-Z0-9-_]{20,}$/', $input)) {
            return $input;
        }

        return null;
    }
}
