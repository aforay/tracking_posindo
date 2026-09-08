<?php

namespace App\Console\Commands;

use App\Models\OutgoingShipment;
use App\Models\PostOffice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPostOfficeContactsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:import-contacts {--link : Link existing shipments to post offices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import 129 official Post Office (KC/KCU/SPP/DC) contact directory and link them to outgoing shipments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Memulai impor 129 Direktori Kontak Kantor Pos (KC/KCU/SPP/DC)...");

        $contacts = [
            // Page 1
            ['name' => 'SPP SEMARANG', 'city' => 'Semarang', 'phone_wa' => '+62 813-9337-1751'],
            ['name' => 'SPP YOGYAKARTA', 'city' => 'Yogyakarta', 'phone_wa' => '+62 818-0260-3716'],
            ['name' => 'SPP BANDUNG', 'city' => 'Bandung', 'phone_wa' => '+62 852-4583-9495'],
            ['name' => 'SPP MEDAN', 'city' => 'Medan', 'phone_wa' => '+62 813-7538-8700'],
            ['name' => 'JAKARTA PREMIER', 'city' => 'Jakarta Timur', 'code' => '13000', 'phone_wa' => '+62 813-1676-1786'],
            ['name' => 'JAKARTA UTARA', 'city' => 'Jakarta Utara', 'code' => '14000', 'phone_wa' => '+62 812-9360-8434'],
            ['name' => 'JAKARTA CENTRUM', 'city' => 'Jakarta Pusat', 'code' => '10000', 'phone_wa' => '+62 812-8311-6986'],
            ['name' => 'SPP DENPASAR', 'city' => 'Denpasar', 'phone_wa' => '+62 821-3936-2679'],
            ['name' => 'SPP MAKASSAR', 'city' => 'Makassar', 'phone_wa' => '+62 822-9272-3551', 'phone_wa_2' => '+62 823-4479-7024'],
            ['name' => 'SPP SURABAYA', 'city' => 'Surabaya', 'phone_wa' => '+62 813-3199-227'],
            ['name' => 'DC SENTANI', 'city' => 'Sentani', 'phone_wa' => '+62 852-5877-6539'],
            ['name' => 'SPP BANJARMASIN', 'city' => 'Banjarmasin', 'phone_wa' => '+62 852-5140-9141'],
            ['name' => 'KCU PALU', 'city' => 'Palu', 'phone_wa' => '+62 821-3039-7691'],
            ['name' => 'KCU PONTIANAK', 'city' => 'Pontianak', 'phone_wa' => '+62 811-5666-006'],
            ['name' => 'KCU KUPANG', 'city' => 'Kupang', 'phone_wa' => '+62 813-5389-6087'],
            ['name' => 'KCU BALIKPAPAN', 'city' => 'Balikpapan', 'phone_wa' => '+62 896-0115-7507'],
            ['name' => 'KCU MANADO', 'city' => 'Manado', 'phone_wa' => '+62 823-5342-0290'],
            ['name' => 'KCU JAYAPURA', 'city' => 'Jayapura', 'phone_wa' => '+62 813-4484-9970', 'phone_wa_2' => '+62 813-1805-3051'],
            ['name' => 'KCU PALEMBANG', 'city' => 'Palembang', 'phone_wa' => '+62 812-7109-4764'],
            ['name' => 'KCU MATARAM', 'city' => 'Mataram', 'phone_wa' => '+62 852-5803-0880'],
            ['name' => 'KCU PALANGKARAYA', 'city' => 'Palangka Raya', 'phone_wa' => '+62 823-5301-4850', 'phone_wa_2' => '+62 812-4160-3407'],
            ['name' => 'KCU AMBON', 'city' => 'Ambon', 'phone_wa' => '+62 822-3895-2958'],
            ['name' => 'KCU JAMBI', 'city' => 'Jambi', 'phone_wa' => '+62 831-0186-7297'],
            ['name' => 'KCU PADANG', 'city' => 'Padang', 'phone_wa' => '+62 823-8210-5607'],

            // Page 2
            ['name' => 'KCU BANDARLAMPUNG', 'city' => 'Bandar Lampung', 'phone_wa' => '+62 852-6949-9505'],
            ['name' => 'KCU TANGGERANG', 'city' => 'Tangerang', 'phone_wa' => '+62 813-1187-1420'],
            ['name' => 'KCU BOGOR', 'city' => 'Bogor', 'phone_wa' => '+62 857-8072-4886'],
            ['name' => 'KCU PURWOKERTO', 'city' => 'Purwokerto', 'phone_wa' => '+62 822-4237-9866'],
            ['name' => 'KCU KENDARI', 'city' => 'Kendari', 'telegram_handle' => '@Suryadi93000', 'phone_wa' => '+62 822-5888-8001', 'phone_wa_2' => '+62 822-5888-8001'],
            ['name' => 'KC BONTANG', 'city' => 'Bontang', 'phone_wa' => '+62 851-7515-7530'],
            ['name' => 'KC BIMA', 'city' => 'Bima', 'phone_wa' => '+62 822-5970-4730'],
            ['name' => 'KC SOLOK', 'city' => 'Solok', 'phone_wa' => '+62 823-3424-4802'],
            ['name' => 'KC GUNUNGSITOLI', 'city' => 'Gunungsitoli', 'phone_wa' => '+62 812-6921-2201'],
            ['name' => 'KC DUMAI', 'city' => 'Dumai', 'phone_wa' => '+62 852-6521-2000'],
            ['name' => 'KC TANJUNGSELOR', 'city' => 'Tanjung Selor', 'phone_wa' => '+62 823-5312-6767'],
            ['name' => 'KC BIAK', 'city' => 'Biak', 'phone_wa' => '+62 812-4019-5454', 'phone_wa_2' => '+62 821-4298-1414'],
            ['name' => 'KC SAMPIT', 'city' => 'Sampit', 'phone_wa' => '+62 821-5276-6690'],
            ['name' => 'KC GORONTALO', 'city' => 'Gorontalo', 'phone_wa' => '+62 881-0116-03323'],
            ['name' => 'KC KABANJAHE', 'city' => 'Kabanjahe', 'phone_wa' => '+62 823-6175-9455'],
            ['name' => 'KC CIBINONG', 'city' => 'Cibinong', 'phone_wa' => '+62 852-1791-7320'],
            ['name' => 'KC PANGKALANBUN', 'city' => 'Pangkalan Bun', 'phone_wa' => '+62 811-5282-642'],
            ['name' => 'KC KETAPANG', 'city' => 'Ketapang', 'phone_wa' => '+62 853-3627-6175', 'phone_wa_2' => '+62 858-4558-8769'],
            ['name' => 'KC RENGAT', 'city' => 'Rengat', 'phone_wa' => '+62 821-7303-3259'],
            ['name' => 'KC KOMODO', 'city' => 'Komodo', 'phone_wa' => '+62 822-4790-5848'],
            ['name' => 'KC SINTANG', 'city' => 'Sintang', 'phone_wa' => '+62 858-2051-8336'],
            ['name' => 'KC WAINGAPU', 'city' => 'Waingapu', 'phone_wa' => '+62 812-3786-4002', 'phone_wa_2' => '+62 815-3773-5349'],
            ['name' => 'KC BANTUL', 'city' => 'Bantul', 'phone_wa' => '+62 811-2855-700'],
            ['name' => 'KC MEULABOH', 'city' => 'Meulaboh', 'phone_wa' => '+62 852-7777-6898', 'phone_wa_2' => '+62 851-3587-7766'],
            ['name' => 'KC TEGAL', 'city' => 'Tegal', 'phone_wa' => '+62 813-8491-1009'],
            ['name' => 'KC TABANAN', 'city' => 'Tabanan', 'phone_wa' => '+62 818-582-100'],
            ['name' => 'KC MUAROBUNGO', 'city' => 'Muara Bungo', 'phone_wa' => '+62 853-8410-1828', 'phone_wa_2' => '+62 813-7316-7066'],
            ['name' => 'KC LANGSA', 'city' => 'Langsa', 'phone_wa' => '+62 856-4393-0532'],

            // Page 3
            ['name' => 'KC LUBUKSIKAPING', 'city' => 'Lubuk Sikaping', 'phone_wa' => '+62 812-6793-1817'],
            ['name' => 'KC SIBOLGA', 'city' => 'Sibolga', 'phone_wa' => '+62 812-6546-3817', 'phone_wa_2' => '+62 898-2468-126'],
            ['name' => 'KC TENGGARONG', 'city' => 'Tenggarong', 'phone_wa' => '+62 852-4011-9269'],
            ['name' => 'KC BINJAI', 'city' => 'Binjai', 'phone_wa' => '+62 812-6931-1135'],
            ['name' => 'KC METRO', 'city' => 'Metro', 'phone_wa' => '+62 813-6725-1537', 'phone_wa_2' => '+62 857-6826-8224'],
            ['name' => 'KC BANJARBARU', 'city' => 'Banjarbaru', 'phone_wa' => '+62 821-5353-7400'],
            ['name' => 'KC LHOKSEMAWE', 'city' => 'Lhokseumawe', 'phone_wa' => '+62 852-9695-6609'],
            ['name' => 'KC SALATIGA', 'city' => 'Salatiga', 'phone_wa' => '+62 821-3394-3455'],
            ['name' => 'KC TUAL', 'city' => 'Tual', 'phone_wa' => '+62 852-8257-6081', 'phone_wa_2' => '+62 821-9833-2327'],
            ['name' => 'KC PROBOLINGGO', 'city' => 'Probolinggo', 'phone_wa' => '+62 852-8078-7187'],
            ['name' => 'KC KENDAL', 'city' => 'Kendal', 'phone_wa' => '+62 822-4224-8824'],
            ['name' => 'KC PURBALINGGA', 'city' => 'Purbalingga', 'phone_wa' => '+62 858-6641-4866'],
            ['name' => 'KC PARE PARE', 'city' => 'Parepare', 'phone_wa' => '+62 813-5594-8322'],
            ['name' => 'KC BANGKINANG', 'city' => 'Bangkinang', 'phone_wa' => '+62 812-8901-4772'],
            ['name' => 'KC CILEGON', 'city' => 'Cilegon', 'phone_wa' => '+62 857-1153-3255'],
            ['name' => 'KC RANTAUPRAPAT', 'city' => 'Rantauprapat', 'phone_wa' => '+62 821-6688-2223'],
            ['name' => 'KC JEPARA', 'city' => 'Jepara', 'phone_wa' => '+62 822-2158-1708'],
            ['name' => 'KC SUMENEP', 'city' => 'Sumenep', 'phone_wa' => '+62 819-1956-9640'],
            ['name' => 'KC TANUNGPINANG', 'city' => 'Tanjungpinang', 'phone_wa' => '+62 813-7856-5100'],
            ['name' => 'KCP KOLONEDALE', 'city' => 'Kolonodale', 'phone_wa' => '+62 822-9601-6724'],
            ['name' => 'KC SORONG', 'city' => 'Sorong', 'phone_wa' => '+62 812-4748-8191'],
            ['name' => 'KC SINGKAWANG', 'city' => 'Singkawang', 'phone_wa' => '+62 823-3338-8815'],
            ['name' => 'KC CIANJUR', 'city' => 'Cianjur', 'phone_wa' => '+62 856-2477-7410'],
            ['name' => 'KC SUNGAIPENUH', 'city' => 'Sungai Penuh', 'phone_wa' => '+62 823-7859-7091'],
            ['name' => 'KC PADANGSIDEMPUAN', 'city' => 'Padang Sidempuan', 'phone_wa' => '+62 859-2100-6700'],
            ['name' => 'KC BAUBAU', 'city' => 'Baubau', 'phone_wa' => '+62 821-8830-0841'],
            ['name' => 'KC SAMARINDA', 'city' => 'Samarinda', 'phone_wa' => '+62 812-1426-8405'],
            ['name' => 'KC TIMIKA', 'city' => 'Timika', 'phone_wa' => '+62 823-9873-2231'],

            // Page 4
            ['name' => 'KC GARUT', 'city' => 'Garut', 'phone_wa' => '+62 882-1855-9201'],
            ['name' => 'KC LUBUKLINGGAU', 'city' => 'Lubuklinggau', 'phone_wa' => '+62 853-6810-4567'],
            ['name' => 'KCU BATAM', 'city' => 'Batam', 'phone_wa' => '+62 821-7329-5358'],
            ['name' => 'KC MERAUKE', 'city' => 'Merauke', 'phone_wa' => '+62 812-4739-4028'],
            ['name' => 'KC PALOPO', 'city' => 'Palopo', 'phone_wa' => '+62 813-4223-7611'],
            ['name' => 'KC KISARAN', 'city' => 'Kisaran', 'phone_wa' => '+62 857-6123-4511'],
            ['name' => 'KC BATULICIN', 'city' => 'Batulicin', 'phone_wa' => '+62 857-8746-0709'],
            ['name' => 'KC BLITAR 66100', 'city' => 'Blitar', 'code' => '66100', 'phone_wa' => '+62 857-4605-7377'],
            ['name' => 'KC GRESIK 61100', 'city' => 'Gresik', 'code' => '61100', 'phone_wa' => '+62 811-3336-1100'],
            ['name' => 'KC TULUNGAGUNG 66200', 'city' => 'Tulungagung', 'code' => '66200', 'phone_wa' => '+62 858-5629-1541'],
            ['name' => 'KCU Madiun 63100', 'city' => 'Madiun', 'code' => '63100', 'phone_wa' => '+62 811-3336-3100'],
            ['name' => 'KC MAJALENGKA 45400', 'city' => 'Majalengka', 'code' => '45400', 'phone_wa' => '+62 821-2922-097'],
            ['name' => 'KC CIMAHI 40500', 'city' => 'Cimahi', 'code' => '40500', 'phone_wa' => '+62 811-2440-500'],
            ['name' => 'KCU SOLO 57100', 'city' => 'Solo', 'code' => '57100', 'phone_wa' => '+62 821-3950-1847'],
            ['name' => 'KC PATI 59100', 'city' => 'Pati', 'code' => '59100', 'phone_wa' => '+62 898-8047-175'],
            ['name' => 'KC TANJUNGPINANG 29100', 'city' => 'Tanjungpinang', 'code' => '29100', 'phone_wa' => '+62 813-7856-5100'],
            ['name' => 'KCU TANGERANG 15000', 'city' => 'Tangerang', 'code' => '15000', 'phone_wa' => '+62 813-1187-1420'],
            ['name' => 'KC KEBUMEN 54300', 'city' => 'Kebumen', 'code' => '54300', 'phone_wa' => '+62 877-2929-4386'],
            ['name' => 'KCU JAKARTA PREMIER 13000', 'city' => 'Jakarta Timur', 'code' => '13000', 'phone_wa' => '+62 813-1676-1786'],
            ['name' => 'KCU KUTACANE', 'city' => 'Kutacane', 'phone_wa' => '+62 823-6703-0328'],
            ['name' => 'DC MAROS', 'city' => 'Maros', 'phone_wa' => '+62 812-4469-2002'],
            ['name' => 'KC CIKARANG', 'city' => 'Cikarang', 'phone_wa' => '+62 858-6150-0121'],
            ['name' => 'KC BUNTOK 73700', 'city' => 'Buntok', 'code' => '73700', 'phone_wa' => '+62 822-5328-8228'],
            ['name' => 'MPS JAKARTA PREMIER', 'city' => 'Jakarta', 'phone_wa' => '+62 812-9360-8434'],
            ['name' => 'KC BUKITTINGGI 26100', 'city' => 'Bukittinggi', 'code' => '26100', 'phone_wa' => '+62 852-6593-3118'],
            ['name' => 'KC SAWAHLUNTO 27400', 'city' => 'Sawahlunto', 'code' => '27400', 'phone_wa' => '+62 812-6138-4901'],
            ['name' => 'KC KOTABUMI 34500', 'city' => 'Kotabumi', 'code' => '34500', 'phone_wa' => '+62 821-7548-5926'],
            ['name' => 'KC BULUKUMBA', 'city' => 'Bulukumba', 'phone_wa' => '+62 813-5602-6126'],

            // Page 5
            ['name' => 'KC PAMEKASAN 69300', 'city' => 'Pamekasan', 'code' => '69300', 'phone_wa' => '+62 852-3115-7946'],
            ['name' => 'KC BAU BAU', 'city' => 'Baubau', 'phone_wa' => '+62 852-4063-7934'],
            ['name' => 'BEKASI', 'city' => 'Bekasi', 'code' => '17000', 'phone_wa' => '+62 812-8904-7636'],
            ['name' => 'KC TEMBILAHAN 29200', 'city' => 'Tembilahan', 'code' => '29200', 'phone_wa' => '+62 852-7119-0350'],
            ['name' => 'DEPOK', 'city' => 'Depok', 'code' => '16400', 'phone_wa' => '+62 813-8323-5731'],
            ['name' => 'KC SUBANG', 'city' => 'Subang', 'phone_wa' => '+62 853-5232-9533'],
            ['name' => 'KC KUDUS', 'city' => 'Kudus', 'phone_wa' => '+62 857-4839-5538'],
            ['name' => 'KC PRABUMULIH', 'city' => 'Prabumulih', 'phone_wa' => '+62 813-6800-7300'],
            ['name' => 'KCU JEMBER', 'city' => 'Jember', 'phone_wa' => '+62 812-3481-4156'],
            ['name' => 'SUMBAWA BESAR 84300', 'city' => 'Sumbawa Besar', 'code' => '84300', 'phone_wa' => '+62 877-7678-4300'],
            ['name' => 'KC ATAMBUA 85700', 'city' => 'Atambua', 'code' => '85700', 'phone_wa' => '+62 821-4751-1179'],
            ['name' => 'KC PEMATANGSIANTAR', 'city' => 'Pematangsiantar', 'phone_wa' => '+62 895-4292-15517'],
            ['name' => 'PANGKALPINANG 33100', 'city' => 'Pangkalpinang', 'code' => '33100', 'phone_wa' => '+62 812-7147-3450'],
            ['name' => 'KC GIANYAR', 'city' => 'Gianyar', 'phone_wa' => '+62 813-1325-0792'],
            ['name' => 'KC RANGKASBITUNG 42300', 'city' => 'Rangkasbitung', 'code' => '42300', 'phone_wa' => '+62 859-3008-8199'],
            ['name' => 'PEKANBARU', 'city' => 'Pekanbaru', 'code' => '28000', 'telegram_handle' => '@CSPbr28000'],
            ['name' => 'KC ANDE', 'city' => 'Ende', 'telegram_handle' => '@septianianita'],
            ['name' => 'KC PALOPO', 'city' => 'Palopo', 'telegram_handle' => '@Lasiyem'],
            ['name' => 'TARAKAN', 'city' => 'Tarakan', 'telegram_handle' => '@Davidadinata'],
            ['name' => 'TANJUNGREDEB', 'city' => 'Tanjung Redeb', 'telegram_handle' => '@ervanherianto'],
            ['name' => 'KC KOMODO', 'city' => 'Komodo', 'telegram_handle' => '@CS_PosIND_Kckomodo'],
        ];

        $insertedOrUpdated = 0;

        foreach ($contacts as $c) {
            $name = strtoupper(trim($c['name']));
            $city = $c['city'] ?? null;
            $code = $c['code'] ?? null;
            if (!$code && preg_match('/\b\d{5}\b/', $name, $m)) {
                $code = $m[0];
            }

            // Find existing post office by code, name, or city
            $existing = PostOffice::where('name', $name)
                ->orWhere(function ($q) use ($code, $name) {
                    if ($code) {
                        $q->where('code', $code);
                    }
                })
                ->orWhere(function ($q) use ($city) {
                    if ($city) {
                        $q->where('city', $city);
                    }
                })
                ->first();

            if ($existing) {
                if (!empty($c['phone_wa'])) {
                    $existing->phone_wa = $c['phone_wa'];
                }
                if (!empty($c['phone_wa_2'])) {
                    $existing->phone_wa_2 = $c['phone_wa_2'];
                }
                if (!empty($c['telegram_handle'])) {
                    $existing->telegram_handle = $c['telegram_handle'];
                }
                if ($code && empty($existing->code)) {
                    $existing->code = $code;
                }
                $existing->saveQuietly();
            } else {
                $office = new PostOffice();
                $office->name = $name;
                $office->city = $city;
                $office->code = $code;
                $office->phone_wa = $c['phone_wa'] ?? null;
                $office->phone_wa_2 = $c['phone_wa_2'] ?? null;
                $office->telegram_handle = $c['telegram_handle'] ?? null;
                $office->pic_name = 'CS Antaran ' . $name;
                $office->saveQuietly();
            }

            $insertedOrUpdated++;
        }

        $this->info("Berhasil memproses {$insertedOrUpdated} kontak kantor pos ke database.");

        // Clear cached offices so queries fetch latest
        PostOffice::clearOfficeCache();

        // Automatically link shipments in outgoing_shipments to post_offices
        $this->info("Menautkan kiriman di outgoing_shipments ke kantor pos terkait...");
        $linkedCount = 0;

        // Step 1: Link all shipments by distinct kantor_tujuan
        $distinctTujuan = OutgoingShipment::whereNotNull('kantor_tujuan')
            ->where('kantor_tujuan', '!=', '')
            ->select('kantor_tujuan')
            ->distinct()
            ->pluck('kantor_tujuan');

        foreach ($distinctTujuan as $kt) {
            $matched = PostOffice::matchByDestinationOrAddress($kt);
            if ($matched) {
                $affected = OutgoingShipment::where('kantor_tujuan', $kt)
                    ->update([
                        'kantor_pos_id' => $matched->id,
                        'kantor_tujuan' => $matched->name,
                        'last_location' => DB::raw("CASE WHEN last_location IS NULL OR last_location = '' OR last_location = '{$kt}' THEN '{$matched->name}' ELSE last_location END"),
                    ]);
                $linkedCount += $affected;
            }
        }

        // Step 2: For remaining shipments where kantor_pos_id is still NULL, match by alamat/last_location
        OutgoingShipment::whereNull('kantor_pos_id')
            ->select(['id', 'kantor_tujuan', 'last_location', 'alamat'])
            ->chunkById(2000, function ($chunk) use (&$linkedCount) {
                $updates = [];
                foreach ($chunk as $s) {
                    $dest = $s->kantor_tujuan ?: $s->last_location;
                    $matched = PostOffice::matchByDestinationOrAddress($dest, $s->alamat);
                    if ($matched) {
                        $updates[$matched->id][] = $s->id;
                    }
                }

                foreach ($updates as $officeId => $shipmentIds) {
                    $matchedOffice = PostOffice::find($officeId);
                    if ($matchedOffice) {
                        OutgoingShipment::whereIn('id', $shipmentIds)->update([
                            'kantor_pos_id' => $officeId,
                            'kantor_tujuan' => $matchedOffice->name,
                        ]);
                        $linkedCount += count($shipmentIds);
                    }
                }
            });

        $this->info("Selesai! {$linkedCount} baris kiriman di database berhasil ditautkan ke kontak KC masing-masing!");

        return Command::SUCCESS;
    }
}
