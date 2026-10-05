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
        // Generic placeholders MUST NEVER MATCH as an office
        $genericNames = [
            'KC TUJUAN', 'KC POS TUJUAN', 'KANTOR POS TUJUAN', 'KC PENGANTARAN',
            'KC POS PENGANTARAN', 'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA',
            'KANTOR POS TERKAIT', 'SEDANG MEMBACA NIPOS...', 'SEDANG MEMBACA NIPOS'
        ];

        if (!empty($destination) && in_array(strtoupper(trim($destination)), $genericNames)) {
            $destination = null;
        }

        if (!empty($destination)) {
            $destination = preg_replace('/\bMPS\b/i', 'SPP', $destination);
        }

        $target = trim(($destination ?: '') . ' ' . ($address ?: ''));
        if (empty($target)) {
            return null;
        }

        $upper = strtoupper($target);
        $offices = self::getCachedOffices();

        // 1. Exact or partial Match on Post Office Name / Code from cached collection
        if (!empty($destination)) {
            $destClean = strtoupper(trim($destination));
            // KCP and DC CANNOT handle follow-ups; skip direct match so it resolves to governing KC / KCU / SPP
            $isKcpOrDc = str_contains($destClean, 'KCP') ||
                         str_starts_with($destClean, 'DC ') ||
                         str_contains($destClean, ' DC ') ||
                         str_ends_with($destClean, ' DC') ||
                         str_contains($destClean, 'DC_') ||
                         preg_match('/\b\d{5}B\d\b/i', $destClean);

            // Regional provincial hubs that often appear as manifest transit hubs in NIPOS
            $regionalHubs = [
                'PEKANBARU', 'KCU PEKANBARU', 'SPP PEKANBARU', 'MPC PEKANBARU',
                'MEDAN', 'KCU MEDAN', 'SPP MEDAN',
                'SURABAYA', 'KCU SURABAYA', 'SPP SURABAYA',
                'SEMARANG', 'KCU SEMARANG', 'SPP SEMARANG',
                'BANDUNG', 'KCU BANDUNG', 'SPP BANDUNG',
                'MAKASSAR', 'KCU MAKASSAR', 'SPP MAKASSAR',
                'PALEMBANG', 'KCU PALEMBANG', 'SPP PALEMBANG',
                'PADANG', 'KCU PADANG', 'SPP PADANG',
            ];
            $isRegionalHub = false;
            foreach ($regionalHubs as $hub) {
                if ($destClean === $hub || str_contains($destClean, $hub)) {
                    $isRegionalHub = true;
                    break;
                }
            }

            // Jika bukan KCP/DC dan (BUKAN regional hub atau alamat kosong), ambil nama kantor pos langsung
            if (!$isKcpOrDc && (!$isRegionalHub || empty($address))) {
                $byName = $offices->first(function ($item) use ($destClean) {
                    if (empty($item->name) || str_starts_with(strtoupper($item->name), 'DC ')) {
                        return false;
                    }
                    $iUpper = strtoupper($item->name);
                    return strcasecmp($iUpper, $destClean) === 0 ||
                           str_contains($iUpper, $destClean) ||
                           str_contains($destClean, $iUpper);
                });
                if ($byName) {
                    return $byName;
                }
            }
        }

        // 2. Extract postal code or DC code (KC / KCU / SPP only, never DC)
        // 2a. Pos Indonesia DC code format (e.g. 9040C -> 90000 KCU MAKASSAR, 5040G -> 50000 KCU SEMARANG, 4040A -> 40000 KCU BANDUNG)
        if (preg_match('/\b(\d{4})[A-Z]\b/i', $target, $mDc)) {
            $dcPrefix2 = substr($mDc[1], 0, 2) . '000';
            $byDc2 = $offices->first(function ($item) use ($dcPrefix2) {
                if (str_starts_with(strtoupper($item->name ?? ''), 'DC ')) return false;
                return (!empty($item->code) && $item->code === $dcPrefix2) ||
                       (!empty($item->name) && str_contains(strtoupper($item->name), $dcPrefix2));
            });
            if ($byDc2) {
                return $byDc2;
            }
        }

        // 2b. Extract 5-digit postal code or KCP code (e.g. 20996B1 -> 20996, 75652)
        if (preg_match('/\b(\d{5})(?:[A-Za-z][A-Za-z0-9]*)?\b/', $target, $m)) {
            $code = $m[1];
            // Exact 5-digit match (KC / KCU / SPP only)
            $byCode = $offices->first(function ($item) use ($code) {
                if (str_starts_with(strtoupper($item->name ?? ''), 'DC ')) return false;
                return (!empty($item->code) && $item->code === $code) ||
                       (!empty($item->name) && str_contains(strtoupper($item->name), $code));
            });
            if ($byCode) {
                return $byCode;
            }


        }

        // 3. Check Regional & KCP Alias Dictionary (Mapping sub-districts and KCPs to governing KC/KCU)
        $aliases = [
            // Banten (Serang / Cilegon / Rangkasbitung / Pandeglang)
            'SERDANG' => 'SERANG',
            'KRAMATWATU' => 'SERANG',
            'WARINGINKURUNG' => 'SERANG',
            'KRAKATAU' => 'SERANG',
            'CIRUAS' => 'SERANG',
            'KRAGILAN' => 'SERANG',
            'BAROS' => 'SERANG',
            'CIOMAS' => 'SERANG',
            'PONTANG' => 'SERANG',
            'BOJONEGARA' => 'CILEGON',
            'PULOAMPEL' => 'CILEGON',

            // DKI Jakarta & SPP
            'MPS JAKARTA PREMIER' => 'SPP JAKARTA PREMIER',
            'MPS JAKARTA' => 'SPP JAKARTA',
            'MPS' => 'SPP',
            'SPP JAKARTA' => 'SPP JAKARTA',
            'SPP JAKARTA TIMUR' => 'SPP JAKARTA',
            'JAKARTASOEKARNOHATTA' => 'JAKARTA BARAT',
            'SOEKARNO HATTA' => 'JAKARTA BARAT',
            'DUREN SAWIT' => 'JAKARTA PREMIER',
            'KLAMBIR' => 'MEDAN',

            // Maluku Utara -> KC Ternate
            'MOROTAI' => 'TERNATE',
            'TOBELO' => 'TERNATE',
            'PASTINA' => 'TERNATE',
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

            // Jawa Timur & Sumatera Barat
            'ORO ORO DOWO' => 'MALANG',
            'ORO-ORO DOWO' => 'MALANG',
            'SITUJUH' => 'PAYAKUMBUH',
            'SITUJUAH' => 'PAYAKUMBUH',

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
            'NABIRE' => 'NABIRE',
            'PANIAI' => 'NABIRE',
            'ENAROTALI' => 'NABIRE',
            'DEIYAI' => 'NABIRE',
            'DOGIYAI' => 'NABIRE',
            'WANGGAR' => 'NABIRE',
            'WAMENA' => 'JAYAPURA',
            'YAHUKIMO' => 'JAYAPURA',
            'SARMI' => 'JAYAPURA',
            'MUARATAMI' => 'JAYAPURA',
            'SENTANI' => 'JAYAPURA',
            'ABEPURA' => 'JAYAPURA',
            'PADANG BULAN' => 'JAYAPURA',
            'PADANGBULAN' => 'JAYAPURA',
            'HEDAM' => 'JAYAPURA',
            'USTJ' => 'JAYAPURA',
            'PADANG BATUNG' => 'BANJARMASIN',
            'PADANG RATU' => 'METRO',
            'PADANG CERMIN' => 'BANDAR LAMPUNG',
            'PADANG GUCI' => 'BENGKULU',
            'PADANG LAWAS' => 'PADANGSIDEMPUAN',
            'PADANG BINDU' => 'PRABUMULIH',
            'PADANG HALABAN' => 'RANTAUPRAPAT',
            'MANGGENG' => 'MEULABOH',
            'BLANGPADANG' => 'BANDA ACEH',
            'JAYAPURA' => 'JAYAPURA',
            'MANOKWARI' => 'MANOKWARI',
            'BINTUNI' => 'MANOKWARI',
            'BABO' => 'MANOKWARI',
            'TOFOI' => 'MANOKWARI',
            'TELUK BINTUNI' => 'MANOKWARI',
            'WONDAMA' => 'MANOKWARI',
            'WASIOR' => 'MANOKWARI',
            'RANSIKI' => 'MANOKWARI',
            'KAIMANA' => 'MANOKWARI',
            'FAKFAK' => 'MANOKWARI',
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
            'SAMPOLAWA' => 'BAUBAU',
            'BUTON' => 'BAUBAU',
            'WAKATOBI' => 'BAUBAU',
            'KASIPUTE' => 'KENDARI',
            'BOMBANA' => 'KENDARI',
            'HUKAEA' => 'KENDARI',
            'RUMBIA' => 'KENDARI',
            'POLEANG' => 'KENDARI',
            'RAROWATU' => 'KENDARI',
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
            'BUNGKU' => 'PALU',
            'BAHODOPI' => 'PALU',
            'FATUFIA' => 'PALU',
            'MOROWALI' => 'PALU',
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
            'MAROS' => 'MAKASSAR',
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
            'MUARAANCALONG' => 'SAMARINDA',
            'MUARA ANCALONG' => 'SAMARINDA',
            'MUARABENGKAL' => 'SAMARINDA',
            'MUARA BENGKAL' => 'SAMARINDA',
            'LONG MESANGAT' => 'SAMARINDA',
            'BUSANG' => 'SAMARINDA',
            'SANGATTA' => 'BONTANG',
            'SANGKULIRANG' => 'BONTANG',
            'RANTAUPULUNG' => 'BONTANG',
            'RANTAU PULUNG' => 'BONTANG',
            'TEPIAN INDAH' => 'BONTANG',
            'BENGALON' => 'BONTANG',
            'KONGBENG' => 'BONTANG',
            'WAHAU' => 'BONTANG',
            'MUARA WAHAU' => 'BONTANG',
            'TELUK PANDAN' => 'BONTANG',
            'KUTAI TIMUR' => 'BONTANG',
            'KUTIM' => 'BONTANG',
            '75652' => 'BONTANG',
            '75600' => 'BONTANG',
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

            // Sumatera (Riau)
            'BAGANSIAPIAPI' => 'DUMAI',
            'BAGAN SIAPIAPI' => 'DUMAI',
            'ROKAN HILIR' => 'DUMAI',
            'ROHIL' => 'DUMAI',
            'DURI' => 'DUMAI',
            'MANDAU' => 'DUMAI',
            'BENGKALIS' => 'DUMAI',
            'PINGGIR' => 'DUMAI',
            'BATHIN SOLAPAN' => 'DUMAI',
            'BUKIT BATU' => 'DUMAI',
            'KOTATENGAH' => 'BANGKINANG',
            'ROKAN HULU' => 'BANGKINANG',
            'ROHUL' => 'BANGKINANG',
            'PASIRPENGARAIAN' => 'BANGKINANG',
            'PASIR PENGARAIAN' => 'BANGKINANG',
            'DALUDALU' => 'BANGKINANG',
            'DALU DALU' => 'BANGKINANG',
            'TAMBUSAI' => 'BANGKINANG',
            'TAMBUSAI UTARA' => 'BANGKINANG',
            'TAMBUSAI UTARA KAB ROKAN HULU' => 'BANGKINANG',
            'FLAMBOYAN' => 'BANGKINANG',
            'SUKARAME' => 'BANGKINANG',
            'KAMPAR' => 'BANGKINANG',
            'BANGKINANG' => 'BANGKINANG',
            '28558' => 'BANGKINANG',
            '28511' => 'BANGKINANG',
            '28500' => 'BANGKINANG',
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
            'HINAI' => 'BINJAI',
            'TANJUNG PURA' => 'BINJAI',
            'TANJUNGPURA' => 'BINJAI',
            'PANGKALAN BRANDAN' => 'BINJAI',
            'PANGKALANBRANDAN' => 'BINJAI',
            'BRANDAN' => 'BINJAI',
            'BESITANG' => 'BINJAI',
            'SECANGGANG' => 'BINJAI',
            'GEBANG' => 'BINJAI',
            'BABALAN' => 'BINJAI',
            'KUALA' => 'BINJAI',
            'BAHOROK' => 'BINJAI',
            'BOHOROK' => 'BINJAI',
            'SALAPIAN' => 'BINJAI',
            'SEI BINGAI' => 'BINJAI',
            'SEIBINGAI' => 'BINJAI',
            'PADANG TIKAR' => 'PONTIANAK',
            'PADANGTIKAR' => 'PONTIANAK',
            'BATU AMPAR' => 'PONTIANAK',
            'BATUAMPAR' => 'PONTIANAK',
            'RASAU JAYA' => 'PONTIANAK',
            'RASAUJAYA' => 'PONTIANAK',
            'KUBU RAYA' => 'PONTIANAK',
            'KUBU' => 'PONTIANAK',
            'SUNGAI KAKAP' => 'PONTIANAK',
            'SUNGAI RAYA' => 'PONTIANAK',
            'TERENTANG' => 'PONTIANAK',
            'KUALA MANDOR' => 'PONTIANAK',
            'TELUK PAKEDAI' => 'PONTIANAK',

            // Sanggau & Sekadau (KC Sanggau 78500)
            'SANGGAU' => 'SANGGAU',
            'KEMBAYAN' => 'SANGGAU',
            'ENTIKONG' => 'SANGGAU',
            'SEKADAU' => 'SANGGAU',
            'TAYAN' => 'SANGGAU',
            'BEDUAI' => 'SANGGAU',
            'BATALANG' => 'SANGGAU',
            'BODOK' => 'SANGGAU',
            'PARINDU' => 'SANGGAU',
            'MUKOK' => 'SANGGAU',
            'BALAI SEBUT' => 'SANGGAU',
            'SOSOK' => 'SANGGAU',
            'PADANG TUALANG' => 'BINJAI',
            'PADANGTUALANG' => 'BINJAI',
            'BATANG SERANGAN' => 'BINJAI',
            'SAWIT SEBERANG' => 'BINJAI',
            'SIRAPIT' => 'BINJAI',
            'SELESAI' => 'BINJAI',
            'SEI LEPAN' => 'BINJAI',
            'BERANDAN BARAT' => 'BINJAI',
            'PEMATANG JAYA' => 'BINJAI',
            'WAMPU' => 'BINJAI',
            'KUTAMBARU' => 'BINJAI',
            'KALIORANG' => 'BONTANG',
            'MUARASABAK' => 'JAMBI',
            'MUARA SABAK' => 'JAMBI',
            'BENUAKAYONG' => 'KETAPANG',
            'BENUA KAYONG' => 'KETAPANG',
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
            'DAYEUHKOLOT' => 'BANDUNG',
            'SEKEJATI' => 'BANDUNG',
            'SITUSAEUR' => 'BANDUNG',
            'TELLOBARU' => 'MAKASSAR',
            'SUNGGUMINASA' => 'MAKASSAR',
            'JONGAYA' => 'MAKASSAR',
            'KOLONEDALE' => 'LUWUK',
            'KOLONODALE' => 'LUWUK',
            'MOROWALI' => 'LUWUK',
            'PETASIA' => 'LUWUK',
            'BELITANG' => 'PALEMBANG',
            'MUARADUA' => 'PALEMBANG',
            'BOJONGGEDE' => 'CIBINONG',
            'RANAU' => 'PALEMBANG',
            'PRAMBANAN' => 'YOGYAKARTA',
            'MRANGGEN' => 'SEMARANG',
            'DEMAK' => 'SEMARANG',
            'SAWAHANNGANJUK' => 'KEDIRI',
            'NGANJUK' => 'KEDIRI',
            'GUCIALIT' => 'PROBOLINGGO',
            'LUMAJANG' => 'PROBOLINGGO',
            'MANTINGAN' => 'MADIUN',
            'PARON' => 'MADIUN',
            'NGAWI' => 'MADIUN',
            'KEDUNGGALAR' => 'MADIUN',
            'GEMARANG' => 'MADIUN',
            'BARAT' => 'MADIUN',
            'GENENG' => 'MADIUN',
            'JOGOROGO' => 'MADIUN',
            'SINE' => 'MADIUN',
            'NGRAMBE' => 'MADIUN',
            'WIDODAREN' => 'MADIUN',
            'WALIKUKUN' => 'MADIUN',
            'GENTENG' => 'JEMBER',
            'GENTENG BANYUWANGI' => 'JEMBER',
            'LABUAN' => 'SERANG',
            'PANDEGLANG' => 'SERANG',
            'SAMBENG' => 'GRESIK',
            'TIKUNG' => 'GRESIK',
            'LAMONGAN' => 'GRESIK',
            'SUMBER' => 'CIREBON',
            'LUBUKBASUNG' => 'BUKITTINGGI',
            'PEDAN' => 'SOLO',
            'KLATEN' => 'SOLO',
            'WAWONDULA' => 'PALOPO',
            'PAJUKUKANG' => 'BULUKUMBA',
            'BANTAENG' => 'BULUKUMBA',
            'MUAROBODIPALANGKI' => 'SAWAHLUNTO',
            'SIJUNJUNG' => 'SAWAHLUNTO',
            'PESANGGARAN' => 'JEMBER',
            'BANYUWANGI' => 'JEMBER',
            'SAMBEREJO' => 'BENGKULU',
            'CURUP' => 'BENGKULU',
            'REJANG LEBONG' => 'BENGKULU',
            'JUANDA' => 'SURABAYA',
            'SURABAYA UTARA' => 'SURABAYA',
        ];

        // Sort aliases by length descending so longer/more specific phrases match first
        uksort($aliases, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($aliases as $keyword => $targetKeyword) {
            $isMatched = false;
            if (mb_strlen($keyword) <= 6) {
                $isMatched = (bool) preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $upper);
            } else {
                $isMatched = str_contains($upper, $keyword);
            }

            if ($isMatched) {
                $matchedByAlias = $offices->first(function ($item) use ($targetKeyword) {
                    if (str_starts_with(strtoupper($item->name ?? ''), 'DC ')) return false;
                    return (!empty($item->name) && str_contains(strtoupper($item->name), $targetKeyword)) ||
                           (!empty($item->city) && strcasecmp($item->city, $targetKeyword) === 0);
                });
                if ($matchedByAlias) {
                    return $matchedByAlias;
                }
            }
        }

        
        // 4. Postal Code Prefix Resolution (if target or address has a genuine 5-digit postal code)
        // MUST run before generic city search so e.g. "KCP PADANG TIKAR 78385" or Kalbar 78xxx postal codes
        // resolve to PONTIANAK, not KCU PADANG (Sumbar 25xxx)!
        $postalPrefixes = [
            // Jabodetabek & Banten
            '10' => 'JAKARTA PUSAT', '11' => 'JAKARTA BARAT', '12' => 'JAKARTA SELATAN', '13' => 'JAKARTA PREMIER', '14' => 'JAKARTA UTARA',
            '15' => 'TANGERANG', '161' => 'BOGOR', '162' => 'BOGOR', '163' => 'CIBINONG', '166' => 'BOGOR', '167' => 'BOGOR', '168' => 'BOGOR',
            '164' => 'DEPOK', '165' => 'DEPOK', '169' => 'CIBINONG', '16' => 'BOGOR',
            '171' => 'BEKASI', '172' => 'BEKASI', '173' => 'BEKASI', '174' => 'BEKASI',
            '175' => 'CIKARANG', '176' => 'CIKARANG', '17' => 'BEKASI',
            '421' => 'SERANG', '423' => 'RANGKASBITUNG', '424' => 'CILEGON', '42' => 'SERANG',

            // Jawa Barat
            '40' => 'BANDUNG', '41' => 'SUBANG', '413' => 'CIKARANG', '431' => 'SUKABUMI', '432' => 'CIANJUR', '43' => 'CIANJUR',
            '44' => 'GARUT', '451' => 'CIREBON', '454' => 'MAJALENGKA', '45' => 'CIREBON', '46' => 'TASIKMALAYA',

            // Jawa Tengah & DIY
            '501' => 'SEMARANG', '502' => 'SEMARANG', '506' => 'SALATIGA', '513' => 'KENDAL', '50' => 'SEMARANG',
            '511' => 'PEKALONGAN', '51' => 'PEKALONGAN',
            '521' => 'TEGAL', '524' => 'TEGAL', '522' => 'TEGAL', '52' => 'TEGAL',
            '531' => 'PURWOKERTO', '532' => 'CILACAP', '533' => 'PURBALINGGA', '534' => 'PURBALINGGA', '53' => 'PURWOKERTO',
            '543' => 'KEBUMEN', '54' => 'KEBUMEN', '55' => 'YOGYAKARTA', '557' => 'BANTUL',
            '56' => 'MAGELANG',
            '571' => 'SOLO', '572' => 'SOLO', '573' => 'SALATIGA', '57' => 'SOLO',
            '58' => 'SEMARANG',
            '591' => 'PATI', '594' => 'JEPARA', '593' => 'PATI', '59' => 'KUDUS',

            // Jawa Timur
            '60' => 'SURABAYA', '611' => 'GRESIK', '612' => 'SURABAYA', '614' => 'SURABAYA', '61' => 'GRESIK',
            '62' => 'GRESIK',
            '631' => 'MADIUN', '63' => 'MADIUN',
            '641' => 'KEDIRI', '642' => 'KEDIRI', '64' => 'KEDIRI',
            '65' => 'MALANG',
            '661' => 'BLITAR', '662' => 'TULUNGAGUNG', '66' => 'BLITAR',
            '671' => 'PROBOLINGGO', '67' => 'PROBOLINGGO',
            '681' => 'JEMBER', '68' => 'JEMBER',
            '691' => 'PAMEKASAN', '692' => 'PAMEKASAN', '693' => 'PAMEKASAN', '694' => 'SUMENEP', '69' => 'PAMEKASAN',

            // Sumatera Utara & Aceh
            '207' => 'BINJAI', '208' => 'BINJAI',
            '20' => 'MEDAN', '211' => 'PEMATANG SIANTAR', '212' => 'KISARAN', '214' => 'RANTAUPRAPAT', '21' => 'RANTAUPRAPAT',
            '221' => 'KABANJAHE', '228' => 'GUNUNGSITOLI', '224' => 'SIBOLGA', '225' => 'SIBOLGA', '22' => 'SIBOLGA',
            '227' => 'PADANGSIDEMPUAN',
            '23' => 'BANDA ACEH', '236' => 'MEULABOH', '241' => 'BIREUEN', '242' => 'BIREUEN',
            '243' => 'LHOKSEUMAWE', '244' => 'LANGSA', '245' => 'TAKENGON', '246' => 'KUTACANE', '24' => 'LHOKSEUMAWE',

            // Riau, Kepri, Sumbar, Jambi, Sumsel, Bengkulu, Lampung, Babel
            '284' => 'BANGKINANG', '285' => 'PEKANBARU', '288' => 'DUMAI', '28' => 'PEKANBARU',
            '291' => 'TANJUNGPINANG', '292' => 'TEMBILAHAN', '293' => 'RENGAT', '294' => 'BATAM', '29' => 'BATAM',
            '25' => 'PADANG', '261' => 'BUKITTINGGI', '262' => 'BUKITTINGGI', '263' => 'BUKITTINGGI', '264' => 'BUKITTINGGI', '26' => 'BUKITTINGGI',
            '274' => 'SAWAHLUNTO', '273' => 'SOLOK', '27' => 'PADANG',
            '36' => 'JAMBI', '37' => 'MUAROBUNGO', '371' => 'SUNGAIPENUH',
            '30' => 'PALEMBANG', '311' => 'PRABUMULIH', '316' => 'LUBUKLINGGAU', '31' => 'PRABUMULIH', '321' => 'PALEMBANG', '32' => 'PALEMBANG',
            '38' => 'BENGKULU', '39' => 'BENGKULU', '33' => 'PANGKALPINANG',
            '35' => 'BANDAR LAMPUNG', '341' => 'METRO', '345' => 'KOTABUMI', '34' => 'METRO',

            // Bali & Nusa Tenggara
            '80' => 'DENPASAR', '81' => 'DENPASAR', '82' => 'TABANAN', '805' => 'GIANYAR',
            '83' => 'MATARAM', '841' => 'BIMA', '842' => 'BIMA', '843' => 'SUMBAWA BESAR', '84' => 'BIMA',
            '85' => 'KUPANG', '857' => 'ATAMBUA', '861' => 'ENDE', '862' => 'ENDE', '865' => 'ENDE', '868' => 'ENDE', '86' => 'KOMODO', '87' => 'WAINGAPU',

            // Kalimantan
            '70' => 'BANJARMASIN', '707' => 'BANJARBARU', '71' => 'BANJARMASIN', '72' => 'BATULICIN',
            '731' => 'PALANGKARAYA', '737' => 'BUNTOK', '738' => 'MUARA TEWEH', '73' => 'PALANGKARAYA',
            '74' => 'SAMPIT', '741' => 'PANGKALANBUN',
            '751' => 'SAMARINDA', '752' => 'SAMARINDA', '75556' => 'SAMARINDA', '75554' => 'SAMARINDA', '75555' => 'SAMARINDA',
            '75' => 'SAMARINDA', '753' => 'BONTANG', '756' => 'BONTANG', '755' => 'TENGGARONG', '76' => 'BALIKPAPAN',
            '771' => 'TARAKAN', '772' => 'TANJUNGSELOR', '773' => 'TANJUNGREDEB', '77' => 'TARAKAN',
            '78385' => 'PONTIANAK', '78382' => 'PONTIANAK', '78383' => 'PONTIANAK', '78384' => 'PONTIANAK',
            '78' => 'PONTIANAK', '781' => 'PONTIANAK', '785' => 'SANGGAU', '786' => 'SINTANG', '788' => 'KETAPANG', '79' => 'SINGKAWANG',

            // Sulawesi
            '90' => 'MAKASSAR', '915' => 'MAMUJU', '918' => 'PALOPO', '919' => 'PALOPO', '929' => 'PALOPO',
            '91' => 'PARE PARE', '922' => 'MAKASSAR', '924' => 'BULUKUMBA', '92' => 'BULUKUMBA',
            '93771' => 'KENDARI', '93772' => 'KENDARI', '93773' => 'KENDARI', '93774' => 'KENDARI',
            '93775' => 'KENDARI', '93776' => 'KENDARI', '93777' => 'KENDARI', '9377' => 'KENDARI',
            '93' => 'KENDARI', '937' => 'BAUBAU',
            '94' => 'PALU', '947' => 'LUWUK', '955' => 'MANADO', '958' => 'MANADO', '95' => 'MANADO', '96' => 'GORONTALO',

            // Maluku & Papua
            '977' => 'TERNATE', '978' => 'TERNATE', '976' => 'TUAL', '975' => 'AMBON',
            '970' => 'AMBON', '971' => 'AMBON', '972' => 'AMBON', '973' => 'AMBON', '974' => 'AMBON', '97' => 'AMBON',
            '99' => 'JAYAPURA', '984' => 'SORONG', '985' => 'SORONG', '986' => 'SORONG',
            '983' => 'MANOKWARI', '981' => 'BIAK', '982' => 'BIAK', '987' => 'NABIRE', '988' => 'NABIRE',
            '996' => 'MERAUKE', '98' => 'SORONG',
        ];

        // ONLY match genuine 5-digit postal codes (e.g. 75652, 75652B1, 10110).
        if (preg_match('/\b(\d{5})(?:[A-Za-z][A-Za-z0-9]*)?\b/', $target, $m) || (!empty($address) && preg_match('/\b(\d{5})(?:[A-Za-z][A-Za-z0-9]*)?\b/', $address, $m))) {
            $fullCode = $m[1];
            $p5 = $fullCode;
            $p4 = substr($fullCode, 0, 4);
            $p3 = substr($fullCode, 0, 3);
            $p2 = substr($fullCode, 0, 2);
            $targetKeyword = $postalPrefixes[$p5] ?? ($postalPrefixes[$p4] ?? ($postalPrefixes[$p3] ?? ($postalPrefixes[$p2] ?? null)));
            if ($targetKeyword) {
                $matchedByPrefix = $offices->first(function ($item) use ($targetKeyword) {
                    if (str_starts_with(strtoupper($item->name ?? ''), 'DC ')) return false;
                    return (!empty($item->name) && str_contains(strtoupper($item->name), $targetKeyword)) ||
                           (!empty($item->city) && strcasecmp($item->city, $targetKeyword) === 0);
                });
                if ($matchedByPrefix) {
                    return $matchedByPrefix;
                }
            }
        }

        // 5. Search by city name keywords from in-memory collection (KC / KCU / SPP only, never DC)
        // Guard against compound names (e.g. "Padang Tikar", "Padang Batung", "Padang Bulan") hijacking to KCU PADANG!
        $padangExclusions = [
            'PADANG TIKAR', 'PADANG BATUNG', 'PADANG BULAN', 'PADANG TUALANG',
            'PADANG RATU', 'PADANG CERMIN', 'PADANG GUCI', 'PADANG LAWAS',
            'PADANG BINDU', 'PADANG HALABAN', 'PADANG MAHONDANG', 'PADANG MATINGGI',
            'DESA PADANG', 'BLANGPADANG', 'PADANG MAINU', 'PADANG LALANG'
        ];
        $hasPadangExclusion = false;
        foreach ($padangExclusions as $pe) {
            if (str_contains($upper, $pe)) {
                $hasPadangExclusion = true;
                break;
            }
        }

        foreach ($offices as $office) {
            if (str_starts_with(strtoupper($office->name ?? ''), 'DC ')) {
                continue;
            }
            if (!empty($office->city)) {
                $cityClean = strtoupper($office->city);
                // Strict guard for PADANG
                if ($cityClean === 'PADANG' && $hasPadangExclusion) {
                    continue;
                }
                if (preg_match('/\b' . preg_quote($cityClean, '/') . '\b/i', $upper)) {
                    return $office;
                }
            }
            if (!empty($office->name)) {
                $cleanOfficeName = trim(preg_replace('/^(KCU|KC|KCP|MPC|DC|SPP|KANTOR POS)\s+/i', '', $office->name));
                $cleanOfficeName = trim(preg_replace('/\b\d{5}\b/', '', $cleanOfficeName));
                if (!empty($cleanOfficeName) && mb_strlen($cleanOfficeName) >= 3) {
                    if (strtoupper($cleanOfficeName) === 'PADANG' && $hasPadangExclusion) {
                        continue;
                    }
                    if (preg_match('/\b' . preg_quote($cleanOfficeName, '/') . '\b/i', $upper)) {
                        return $office;
                    }
                }
            }
        }

        return null;
    }
}
