<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostOffice extends Model
{
    use HasFactory;

    protected $table = 'post_offices';

    protected $fillable = [
        'code',
        'name',
        'city',
        'province',
        'phone_wa',
        'pic_name',
        'notes',
    ];

    /**
     * Clean and format phone number to international WhatsApp format (e.g. 628123456789)
     */
    public static function formatWaPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    /**
     * Set formatted phone number before saving
     */
    public function setPhoneWaAttribute($value)
    {
        $this->attributes['phone_wa'] = self::formatWaPhone($value);
    }

    /**
     * Find best matching PostOffice given destination name or customer address text
     */
    public static function matchByDestinationOrAddress(?string $destination, ?string $address = null): ?self
    {
        $target = trim(($destination ?: '') . ' ' . ($address ?: ''));
        if (empty($target)) {
            return null;
        }

        $upper = strtoupper($target);

        // 1. Exact or partial Match on Post Office Name / Code
        if (!empty($destination)) {
            $destClean = strtoupper(trim($destination));
            $byName = self::where('name', 'LIKE', "%{$destClean}%")->first();
            if ($byName) {
                return $byName;
            }
        }

        // 2. Extract 5-digit postal code
        if (preg_match('/\b\d{5}\b/', $target, $m)) {
            $code = $m[0];
            $byCode = self::where('code', $code)->orWhere('name', 'LIKE', "%{$code}%")->first();
            if ($byCode) {
                return $byCode;
            }
        }

        // 3. Search by city name keywords from database
        $offices = self::all();
        foreach ($offices as $office) {
            if (!empty($office->city) && str_contains($upper, strtoupper($office->city))) {
                return $office;
            }
            if (!empty($office->name)) {
                $cleanOfficeName = trim(preg_replace('/^(KCU|KC|KCP|MPC|KANTOR POS)\s+/i', '', $office->name));
                $cleanOfficeName = trim(preg_replace('/\b\d{5}\b/', '', $cleanOfficeName));
                if (!empty($cleanOfficeName) && mb_strlen($cleanOfficeName) >= 4 && str_contains($upper, strtoupper($cleanOfficeName))) {
                    return $office;
                }
            }
        }

        return null;
    }
}
