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

    protected static ?\Illuminate\Database\Eloquent\Collection $cachedOffices = null;

    /**
     * Get or load cached post offices collection
     */
    public static function getCachedOffices(): \Illuminate\Database\Eloquent\Collection
    {
        if (self::$cachedOffices === null) {
            self::$cachedOffices = self::all();
        }
        return self::$cachedOffices;
    }

    /**
     * Clear post offices in-memory cache
     */
    public static function clearCache(): void
    {
        self::$cachedOffices = null;
    }

    /**
     * Find best matching PostOffice given destination name or customer address text (Fast in-memory matching)
     */
    public static function matchByDestinationOrAddress(?string $destination, ?string $address = null): ?self
    {
        $target = trim(($destination ?: '') . ' ' . ($address ?: ''));
        if (empty($target)) {
            return null;
        }

        $upper = strtoupper($target);
        $offices = self::getCachedOffices();

        // 1. Exact or partial Match on Post Office Name / Code from cached collection
        if (!empty($destination)) {
            $destClean = strtoupper(trim($destination));
            $byName = $offices->first(function ($item) use ($destClean) {
                return !empty($item->name) && (
                    strcasecmp($item->name, $destClean) === 0 ||
                    str_contains(strtoupper($item->name), $destClean) ||
                    str_contains($destClean, strtoupper($item->name))
                );
            });
            if ($byName) {
                return $byName;
            }
        }

        // 2. Extract 5-digit postal code
        if (preg_match('/\b\d{5}\b/', $target, $m)) {
            $code = $m[0];
            $byCode = $offices->first(function ($item) use ($code) {
                return (!empty($item->code) && $item->code === $code) ||
                       (!empty($item->name) && str_contains(strtoupper($item->name), $code));
            });
            if ($byCode) {
                return $byCode;
            }
        }

        // 3. Search by city name keywords from in-memory collection
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

        // 4. Check Regional Alias Dictionary
        $aliases = [
            'LABUSEL' => 'Rantauprapat',
            'LABURA' => 'Rantauprapat',
            'LABUHANBATU' => 'Rantauprapat',
            'KOTAPINANG' => 'Rantauprapat',
            'BIREUN' => 'Bireuen',
            'GANDAPURA' => 'Bireuen',
            'TAKENGON' => 'Takengon',
            'BENER MERIAH' => 'Takengon',
            'PADALARANG' => 'Cimahi',
            'LEMBANG' => 'Cimahi',
            'TOAYA' => 'Palu',
            'DONGGALA' => 'Palu',
            'SIGI' => 'Palu',
            'SINGKOYO' => 'Luwuk',
            'BANGGAI' => 'Luwuk',
            'SUMENEP' => 'Sumenep',
            'BATUAN' => 'Sumenep',
            'PAMEKASAN' => 'Pamekasan',
            'SAMPANG' => 'Pamekasan',
            'BANGKALAN' => 'Pamekasan',
            'BANJARBARU' => 'Banjarbaru',
            'SUNGAI ULIN' => 'Banjarbaru',
            'MARTAPURA' => 'Banjarbaru',
            'BARITO UTARA' => 'Muara Teweh',
            'MUARA TEWEH' => 'Muara Teweh',
            'INDRAGIRI HILIR' => 'Tembilahan',
            'INHIL' => 'Tembilahan',
            'BAGAN JAYA' => 'Tembilahan',
            'TEMBILAHAN' => 'Tembilahan',
            'BINTAN' => 'Tanjungpinang',
            'PENAGA' => 'Tanjungpinang',
            'TANGSEL' => 'Tangerang Selatan',
            'CIPUTAT' => 'Tangerang Selatan',
            'PAMULANG' => 'Tangerang Selatan',
            'BSD' => 'Tangerang Selatan',
            'SERPONG' => 'Tangerang Selatan',
            'BINTARO' => 'Tangerang Selatan',
            'CIKARANG' => 'Bekasi',
            'TAMBUN' => 'Bekasi',
            'CIBINONG' => 'Bogor',
        ];

        foreach ($aliases as $keyword => $targetCity) {
            if (str_contains($upper, $keyword)) {
                $matchedByAlias = $offices->first(function ($item) use ($targetCity) {
                    return !empty($item->city) && strcasecmp($item->city, $targetCity) === 0;
                });
                if ($matchedByAlias) {
                    return $matchedByAlias;
                }
            }
        }

        return null;
    }
}
