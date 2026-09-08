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
        'phone_wa_2',
        'telegram_handle',
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

        // If it's a handle like @username, return empty for phone
        if (str_starts_with(trim($phone), '@')) {
            return '';
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return '';
        }

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
     * Set formatted second phone number before saving
     */
    public function setPhoneWa2Attribute($value)
    {
        $this->attributes['phone_wa_2'] = self::formatWaPhone($value);
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

    public static function clearOfficeCache(): void
    {
        self::clearCache();
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

        // 2. Extract 5-digit postal code exact match
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
                $cleanOfficeName = trim(preg_replace('/^(KCU|KC|KCP|MPC|DC|KANTOR POS)\s+/i', '', $office->name));
                $cleanOfficeName = trim(preg_replace('/\b\d{5}\b/', '', $cleanOfficeName));
                if (!empty($cleanOfficeName) && mb_strlen($cleanOfficeName) >= 4 && str_contains($upper, strtoupper($cleanOfficeName))) {
                    return $office;
                }
            }
        }

        // 4. Check Regional & KCP Alias Dictionary (Mapping sub-districts and KCPs to governing KC/KCU)
        $aliases = [
            // Maluku Utara -> KC Ternate
            'MOROTAI' => 'TERNATE',
            'TOBELO' => 'TERNATE',
            'WEDA' => 'TERNATE',
            'LABUHA' => 'TERNATE',
            'SANANA' => 'TERNATE',
            'MABA' => 'TERNATE',
            'JAILOLO' => 'TERNATE',
            'TIDORE' => 'TERNATE',
            'SOFIFI' => 'TERNATE',
            'KAO' => 'TERNATE',
            'HALMAHERA' => 'TERNATE',
            'SULA' => 'TERNATE',

            // Maluku -> KCU Ambon / KC Tual
            'DOBO' => 'TUAL',
            'SAUMLAKI' => 'TUAL',
            'TUAL' => 'TUAL',
            'MALUKU TENGGARA' => 'TUAL',
            'ARU' => 'TUAL',
            'TANIMBAR' => 'TUAL',
            'MASOHI' => 'AMBON',
            'NAMLEA' => 'AMBON',
            'BULA' => 'AMBON',
            'SERAM' => 'AMBON',
            'BURU' => 'AMBON',

            // Papua & Papua Barat
            'NABIRE' => 'JAYAPURA',
            'WAMENA' => 'JAYAPURA',
            'YAHUKIMO' => 'JAYAPURA',
            'SARMI' => 'JAYAPURA',
            'MUARATAMI' => 'JAYAPURA',
            'SENTANI' => 'DC SENTANI',
            'ABEPURA' => 'DC SENTANI',
            'MANOKWARI' => 'SORONG',
            'KAIMANA' => 'SORONG',
            'FAKFAK' => 'SORONG',
            'BINTUNI' => 'SORONG',
            'RAJA AMPAT' => 'SORONG',
            'SERUI' => 'BIAK',
            'YAPEN' => 'BIAK',
            'WAROPEN' => 'BIAK',
            'TANAHMERAH' => 'MERAUKE',
            'BOVEN DIGOEL' => 'MERAUKE',
            'ASMAT' => 'MERAUKE',
            'MAPPI' => 'MERAUKE',
            'MIMIKA' => 'TIMIKA',

            // Sulawesi Tenggara -> KC Baubau / KCU Kendari
            'RAHA' => 'BAUBAU',
            'MUNA' => 'BAUBAU',
            'KASIPUTE' => 'BAUBAU',
            'BOMBANA' => 'BAUBAU',
            'SAMPOLAWA' => 'BAUBAU',
            'BUTON' => 'BAUBAU',
            'WAKATOBI' => 'BAUBAU',
            'KOLAKA' => 'KENDARI',
            'POMALAA' => 'KENDARI',
            'KONAWE' => 'KENDARI',

            // Sulawesi Tengah -> KCU Palu / KC Luwuk
            'POSO' => 'PALU',
            'TOLITOLI' => 'PALU',
            'DONGGALA' => 'PALU',
            'SIGI' => 'PALU',
            'TOAYA' => 'PALU',
            'PARIGI' => 'PALU',
            'BINANGGA' => 'PALU',
            'AMPIBABO' => 'PALU',
            'LEOK' => 'PALU',
            'BUOL' => 'PALU',
            'AMPANA' => 'PALU',
            'TOJO UNA' => 'PALU',
            'BUNGKU' => 'LUWUK',
            'BAHODOPI' => 'LUWUK',
            'MOROWALI' => 'LUWUK',
            'BANGGAI' => 'LUWUK',
            'SINGKOYO' => 'LUWUK',
            'KOLONEDALE' => 'KOLONEDALE',

            // Sulawesi Utara & Gorontalo
            'KOTAMOBAGU' => 'MANADO',
            'AMURANG' => 'MANADO',
            'LOLAK' => 'MANADO',
            'GIRIAN' => 'MANADO',
            'BITUNG' => 'MANADO',
            'MINAHASA' => 'MANADO',
            'TOMOHON' => 'MANADO',
            'LIMBOTO' => 'GORONTALO',
            'ISIMU' => 'GORONTALO',
            'BONE BOLANGO' => 'GORONTALO',
            'POHUWATO' => 'GORONTALO',

            // Sulawesi Selatan & Barat
            'SUNGGUMINASA' => 'MAKASSAR',
            'TELLOBARU' => 'MAKASSAR',
            'DAYA' => 'MAKASSAR',
            'JONGAYA' => 'MAKASSAR',
            'TAKALAR' => 'MAKASSAR',
            'MANIMPAHOI' => 'MAKASSAR',
            'GOWA' => 'MAKASSAR',
            'JENEPONTO' => 'MAKASSAR',
            'BANTAENG' => 'BULUKUMBA',
            'BULUKUMBA' => 'BULUKUMBA',
            'MAROS' => 'DC MAROS',
            'PANGKAJENE' => 'PARE PARE',
            'SIDRAP' => 'PARE PARE',
            'PINRANG' => 'PARE PARE',
            'BARRU' => 'PARE PARE',
            'POLEWALI' => 'MAMUJU',
            'WONOMULYO' => 'MAMUJU',
            'MAJENE' => 'MAMUJU',
            'MAMASA' => 'MAMUJU',
            'RANTEPAO' => 'PALOPO',
            'TORAJA' => 'PALOPO',
            'LUWU' => 'PALOPO',
            'MASAMBA' => 'PALOPO',
            'MALILI' => 'PALOPO',

            // Bali & Nusa Tenggara
            'SINGARAJA' => 'DENPASAR',
            'BULELENG' => 'DENPASAR',
            'BANGLI' => 'DENPASAR',
            'SAMPALAN' => 'DENPASAR',
            'KLUNGKUNG' => 'DENPASAR',
            'KARANGASEM' => 'DENPASAR',
            'TABANAN' => 'TABANAN',
            'GIANYAR' => 'GIANYAR',
            'SELONG' => 'MATARAM',
            'LOMBOK' => 'MATARAM',
            'KOPANG' => 'MATARAM',
            'MANDALIKA' => 'MATARAM',
            'PRAYA' => 'MATARAM',
            'SUMBAWA' => 'SUMBAWA BESAR',
            'BIMA' => 'BIMA',
            'DOMPU' => 'BIMA',
            'KALABAHI' => 'KUPANG',
            'ALOR' => 'KUPANG',
            'MAUMERE' => 'KUPANG',
            'SIKKA' => 'KUPANG',
            'LARANTUKA' => 'KUPANG',
            'FLORES' => 'KUPANG',
            'LEWOLEBA' => 'KUPANG',
            'LEMBATA' => 'KUPANG',
            'ROTE' => 'KUPANG',
            'ENDE' => 'ANDE',
            'ANDE' => 'ANDE',
            'WAINGAPU' => 'WAINGAPU',
            'WAIKABUBAK' => 'WAINGAPU',
            'SUMBA' => 'WAINGAPU',
            'KOMODO' => 'KOMODO',
            'LABUAN BAJO' => 'KOMODO',
            'MANGGARAI' => 'KOMODO',
            'ATAMBUA' => 'ATAMBUA',
            'BELU' => 'ATAMBUA',

            // Kalimantan
            'SANGATTA' => 'BONTANG',
            'SANGKULIRANG' => 'BONTANG',
            'KUTAI TIMUR' => 'BONTANG',
            'BARONG TONGKOK' => 'TENGGARONG',
            'KEMBANG JANGGUT' => 'TENGGARONG',
            'KUTAI BARAT' => 'TENGGARONG',
            'KUTAI KARTANEGARA' => 'TENGGARONG',
            'MALINAU' => 'TARAKAN',
            'NUNUKAN' => 'TARAKAN',
            'TANJUNG SELOR' => 'TANJUNGSELOR',
            'BULUNGAN' => 'TANJUNGSELOR',
            'TANJUNG REDEB' => 'TANJUNGREDEB',
            'BERAU' => 'TANJUNGREDEB',
            'BATULICIN' => 'BATULICIN',
            'TANAH BUMBU' => 'BATULICIN',
            'KOTABARU' => 'BATULICIN',
            'BANJARBARU' => 'BANJARBARU',
            'MARTAPURA' => 'BANJARBARU',
            'SUNGAI ULIN' => 'BANJARBARU',
            'SAMPIT' => 'SAMPIT',
            'PARENGGEAN' => 'SAMPIT',
            'PANGKALAN BUN' => 'PANGKALANBUN',
            'KOTAWARINGIN' => 'PANGKALANBUN',
            'PURUK CAHU' => 'BUNTOK',
            'PURUKCAHU' => 'BUNTOK',
            'MURUNG RAYA' => 'BUNTOK',
            'BUNTOK' => 'BUNTOK',
            'BARITO SELATAN' => 'BUNTOK',
            'BARITO UTARA' => 'MUARA TEWEH',
            'MUARA TEWEH' => 'MUARA TEWEH',
            'NGABANG' => 'SINGKAWANG',
            'LANDAK' => 'SINGKAWANG',
            'SANGGAULEDO' => 'SINGKAWANG',
            'BENGKAYANG' => 'SINGKAWANG',
            'SAMBAS' => 'SINGKAWANG',
            'SINTANG' => 'SINTANG',
            'KAPUAS HULU' => 'SINTANG',
            'KETAPANG' => 'KETAPANG',
            'KAYONG' => 'KETAPANG',

            // Sumatera
            'BAGANSIAPIAPI' => 'DUMAI',
            'ROKAN HILIR' => 'DUMAI',
            'ROHIL' => 'DUMAI',
            'DURI' => 'DUMAI',
            'BENGKALIS' => 'DUMAI',
            'KOTATENGAH' => 'BANGKINANG',
            'ROKAN HULU' => 'BANGKINANG',
            'ROHUL' => 'BANGKINANG',
            'DALUDALU' => 'BANGKINANG',
            'FLAMBOYAN' => 'BANGKINANG',
            'SUKARAME' => 'BANGKINANG',
            'KAMPAR' => 'BANGKINANG',
            'PANGKALAN KERINCI' => 'RENGAT',
            'PELALAWAN' => 'RENGAT',
            'PANGKALAN KASAI' => 'RENGAT',
            'INDRAGIRI HULU' => 'RENGAT',
            'INHU' => 'RENGAT',
            'TEMBILAHAN' => 'TEMBILAHAN',
            'INDRAGIRI HILIR' => 'TEMBILAHAN',
            'INHIL' => 'TEMBILAHAN',
            'BAGAN JAYA' => 'TEMBILAHAN',
            'SIAK' => 'PEKANBARU',
            'SIAKSRIINDRAPURA' => 'PEKANBARU',
            'BINTAN' => 'TANJUNGPINANG',
            'PENAGA' => 'TANJUNGPINANG',
            'BATANGTORU' => 'PADANGSIDEMPUAN',
            'TAPANULI SELATAN' => 'PADANGSIDEMPUAN',
            'PENYABUNGAN' => 'PADANGSIDEMPUAN',
            'MANDAILING' => 'PADANGSIDEMPUAN',
            'MADINA' => 'PADANGSIDEMPUAN',
            'SIBOLGA' => 'SIBOLGA',
            'TAPANULI TENGAH' => 'SIBOLGA',
            'TAPANULI UTARA' => 'SIBOLGA',
            'GUNUNGSITOLI' => 'GUNUNGSITOLI',
            'NIAS' => 'GUNUNGSITOLI',
            'RANTAUPRAPAT' => 'RANTAUPRAPAT',
            'LABUHANBATU' => 'RANTAUPRAPAT',
            'LABUSEL' => 'RANTAUPRAPAT',
            'LABURA' => 'RANTAUPRAPAT',
            'KOTAPINANG' => 'RANTAUPRAPAT',
            'NEGERILAMA' => 'RANTAUPRAPAT',
            'KISARAN' => 'KISARAN',
            'ASAHAN' => 'KISARAN',
            'TANJUNGBALAI' => 'KISARAN',
            'BATUBARA' => 'KISARAN',
            'KABANJAHE' => 'KABANJAHE',
            'KARO' => 'KABANJAHE',
            'DAIRI' => 'KABANJAHE',
            'SIDIKALANG' => 'KABANJAHE',
            'BINJAI' => 'BINJAI',
            'STABAT' => 'BINJAI',
            'LANGKAT' => 'BINJAI',
            'SUBULUSSALAM' => 'KUTACANE',
            'KUTACANE' => 'KUTACANE',
            'ACEH TENGGARA' => 'KUTACANE',
            'ACEH SINGKIL' => 'KUTACANE',
            'MEULABOH' => 'MEULABOH',
            'ACEH BARAT' => 'MEULABOH',
            'NAGAN RAYA' => 'MEULABOH',
            'ACEH JAYA' => 'MEULABOH',
            'ACEH SELATAN' => 'MEULABOH',
            'TAPAKTUAN' => 'MEULABOH',
            'LANGSA' => 'LANGSA',
            'ACEH TIMUR' => 'LANGSA',
            'ACEH TAMIANG' => 'LANGSA',
            'BIREUEN' => 'BIREUEN',
            'BIREUN' => 'BIREUEN',
            'GANDAPURA' => 'BIREUEN',
            'TAKENGON' => 'TAKENGON',
            'BENER MERIAH' => 'TAKENGON',
            'LHOKSEUMAWE' => 'LHOKSEUMAWE',
            'ACEH UTARA' => 'LHOKSEUMAWE',
            'BANGKO' => 'MUAROBUNGO',
            'MERANGIN' => 'MUAROBUNGO',
            'SAROLANGUN' => 'MUAROBUNGO',
            'TEBO' => 'MUAROBUNGO',
            'SUNGAI PENUH' => 'SUNGAIPENUH',
            'SUNGAIPENUH' => 'SUNGAIPENUH',
            'KERINCI' => 'SUNGAIPENUH',
            'MUARA ENIM' => 'PRABUMULIH',
            'MUARAENIM' => 'PRABUMULIH',
            'PALI' => 'PRABUMULIH',
            'BABAT' => 'PALEMBANG',
            'MUSI BANYUASIN' => 'PALEMBANG',
            'BANYUASIN' => 'PALEMBANG',
            'OGAN ILIR' => 'PALEMBANG',
            'LUBUKLINGGAU' => 'LUBUKLINGGAU',
            'MUSI RAWAS' => 'LUBUKLINGGAU',
            'EMPAT LAWANG' => 'LUBUKLINGGAU',
            'BANDAR LAMPUNG' => 'BANDAR LAMPUNG',
            'BANDARLAMPUNG' => 'BANDAR LAMPUNG',
            'KOTABUMI' => 'KOTABUMI',
            'LAMPUNG UTARA' => 'KOTABUMI',
            'WAY KANAN' => 'KOTABUMI',
            'METRO' => 'METRO',
            'LAMPUNG TIMUR' => 'METRO',
            'LAMPUNG TENGAH' => 'METRO',
            'SAWAHLUNTO' => 'SAWAHLUNTO',
            'SIJUNJUNG' => 'SAWAHLUNTO',
            'DHARMASRAYA' => 'SAWAHLUNTO',
            'SOLOK' => 'SOLOK',
            'LUBUK SIKAPING' => 'LUBUKSIKAPING',
            'LUBUKSIKAPING' => 'LUBUKSIKAPING',
            'PASAMAN' => 'LUBUKSIKAPING',
            'KINALI' => 'LUBUKSIKAPING',

            // Jawa & SPP Hubs
            'JUANDA' => 'SURABAYA',
            'WONOKROMO' => 'SURABAYA',
            'KETINTANG' => 'SURABAYA',
            'SPP SURABAYA' => 'SURABAYA',
            'GRESIK' => 'GRESIK',
            'SIDOARJO' => 'SURABAYA',
            'BLITAR' => 'BLITAR',
            'TULUNGAGUNG' => 'TULUNGAGUNG',
            'PROBOLINGGO' => 'PROBOLINGGO',
            'LUMAJANG' => 'PROBOLINGGO',
            'KRAKSAAN' => 'PROBOLINGGO',
            'PASURUAN' => 'PASURUAN',
            'BANGIL' => 'PASURUAN',
            'SUMENEP' => 'SUMENEP',
            'BATUAN' => 'SUMENEP',
            'PAMEKASAN' => 'PAMEKASAN',
            'SAMPANG' => 'PAMEKASAN',
            'BANGKALAN' => 'PAMEKASAN',
            'MADURA' => 'PAMEKASAN',
            'PURWOREJO' => 'KEBUMEN',
            'KEBUMEN' => 'KEBUMEN',
            'PURBALINGGA' => 'PURBALINGGA',
            'BANYUMAS' => 'PURWOKERTO',
            'PURWOKERTO' => 'PURWOKERTO',
            'CILACAP' => 'CILACAP',
            'KENDAL' => 'KENDAL',
            'SALATIGA' => 'SALATIGA',
            'SEMARANG' => 'SEMARANG',
            'PATI' => 'PATI',
            'JEPARA' => 'JEPARA',
            'KUDUS' => 'KUDUS',
            'TEGAL' => 'TEGAL',
            'SLAWI' => 'TEGAL',
            'BREBES' => 'TEGAL',
            'PEKALONGAN' => 'PEKALONGAN',
            'BATANG' => 'PEKALONGAN',
            'PEMALANG' => 'PEKALONGAN',
            'BANTUL' => 'BANTUL',
            'SLEMAN' => 'YOGYAKARTA',
            'KULON PROGO' => 'YOGYAKARTA',
            'GUNUNG KIDUL' => 'YOGYAKARTA',
            'WONOGIRI' => 'SOLO',
            'KLATEN' => 'SOLO',
            'BOYOLALI' => 'SOLO',
            'SRAGEN' => 'SOLO',
            'KARANGANYAR' => 'SOLO',
            'SUKOHARJO' => 'SOLO',
            'CIKARANG' => 'CIKARANG',
            'TAMBUN' => 'BEKASI',
            'BEKASI' => 'BEKASI',
            'CIBINONG' => 'CIBINONG',
            'BOGOR' => 'BOGOR',
            'DEPOK' => 'DEPOK',
            'TANGSEL' => 'TANGERANG SELATAN',
            'CIPUTAT' => 'TANGERANG SELATAN',
            'PAMULANG' => 'TANGERANG SELATAN',
            'BSD' => 'TANGERANG SELATAN',
            'SERPONG' => 'TANGERANG SELATAN',
            'BINTARO' => 'TANGERANG SELATAN',
            'CILEGON' => 'CILEGON',
            'SERANG' => 'SERANG',
            'RANGKASBITUNG' => 'RANGKASBITUNG',
            'LEBAK' => 'RANGKASBITUNG',
            'PANDEGLANG' => 'RANGKASBITUNG',
            'SUBANG' => 'SUBANG',
            'PURWAKARTA' => 'SUBANG',
            'KARAWANG' => 'BEKASI',
            'CIANJUR' => 'CIANJUR',
            'GARUT' => 'GARUT',
            'TASIKMALAYA' => 'TASIKMALAYA',
            'CIAMIS' => 'TASIKMALAYA',
            'BANJAR' => 'TASIKMALAYA',
            'PANGANDARAN' => 'TASIKMALAYA',
            'MAJALENGKA' => 'MAJALENGKA',
            'KUNINGAN' => 'CIREBON',
            'INDRAMAYU' => 'CIREBON',
            'CIREBON' => 'CIREBON',
            'CIMAHI' => 'CIMAHI',
            'PADALARANG' => 'CIMAHI',
            'LEMBANG' => 'CIMAHI',
            'BANDUNG BARAT' => 'CIMAHI',
            'SOEKARNO HATTA' => 'BANDUNG',
        ];

        foreach ($aliases as $keyword => $targetKeyword) {
            if (str_contains($upper, $keyword)) {
                $matchedByAlias = $offices->first(function ($item) use ($targetKeyword) {
                    return (!empty($item->name) && str_contains(strtoupper($item->name), $targetKeyword)) ||
                           (!empty($item->city) && strcasecmp($item->city, $targetKeyword) === 0);
                });
                if ($matchedByAlias) {
                    return $matchedByAlias;
                }
            }
        }

        // 5. Fallback via Postal Code Prefix (2-digit or 3-digit)
        $postalPrefixes = [
            '977' => 'TERNATE', '978' => 'TERNATE', '976' => 'TUAL', '975' => 'AMBON',
            '970' => 'AMBON', '971' => 'AMBON', '972' => 'AMBON', '973' => 'AMBON', '974' => 'AMBON',
            '99'  => 'JAYAPURA', '984' => 'SORONG', '985' => 'SORONG', '986' => 'SORONG',
            '983' => 'SORONG', '981' => 'BIAK', '982' => 'BIAK', '987' => 'TIMIKA', '988' => 'JAYAPURA',
            '936' => 'BAUBAU', '937' => 'BAUBAU', '938' => 'BAUBAU', '93' => 'KENDARI',
            '947' => 'LUWUK', '948' => 'LUWUK', '949' => 'LUWUK', '94' => 'PALU',
            '95'  => 'MANADO', '96' => 'GORONTALO', '915' => 'MAMUJU', '913' => 'MAMUJU',
            '918' => 'PALOPO', '919' => 'PALOPO', '91' => 'PARE PARE', '90' => 'MAKASSAR',
            '92'  => 'MAKASSAR', '80' => 'DENPASAR', '81' => 'DENPASAR', '82' => 'DENPASAR',
            '83'  => 'MATARAM', '84' => 'BIMA', '85' => 'KUPANG', '86' => 'KUPANG', '87' => 'WAINGAPU',
            '70'  => 'BANJARMASIN', '71' => 'BANJARMASIN', '72' => 'BATULICIN', '73' => 'PALANGKARAYA',
            '74'  => 'SAMPIT', '75' => 'SAMARINDA', '76' => 'BALIKPAPAN', '77' => 'TARAKAN',
            '78'  => 'PONTIANAK', '79' => 'SINGKAWANG', '60' => 'SURABAYA',
        ];

        if (preg_match('/\b(\d{2,3})\d{2,3}\b/', $target, $m)) {
            $p3 = substr($m[0], 0, 3);
            $p2 = substr($m[0], 0, 2);
            $targetKeyword = $postalPrefixes[$p3] ?? ($postalPrefixes[$p2] ?? null);
            if ($targetKeyword) {
                $matchedByPrefix = $offices->first(function ($item) use ($targetKeyword) {
                    return (!empty($item->name) && str_contains(strtoupper($item->name), $targetKeyword)) ||
                           (!empty($item->city) && strcasecmp($item->city, $targetKeyword) === 0);
                });
                if ($matchedByPrefix) {
                    return $matchedByPrefix;
                }
            }
        }

        return null;
    }
}
