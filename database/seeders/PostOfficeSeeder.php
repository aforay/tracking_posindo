<?php

namespace Database\Seeders;

use App\Models\PostOffice;
use Illuminate\Database\Seeder;

class PostOfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offices = [
            // DKI Jakarta
            ['code' => '10000', 'name' => 'KCU JAKARTA PUSAT 10000', 'city' => 'Jakarta Pusat', 'province' => 'DKI Jakarta', 'phone_wa' => '6281210001001', 'pic_name' => 'CS Antaran KC Jakpus', 'notes' => 'Gambir, Senen, Tanah Abang, Menteng, Kemayoran'],
            ['code' => '12000', 'name' => 'KC JAKARTA SELATAN 12000', 'city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta', 'phone_wa' => '6281210001200', 'pic_name' => 'Helpdesk KC Jaksel', 'notes' => 'Kebayoran, Mampang, Cilandak, Pasar Minggu, Jagakarsa'],
            ['code' => '11000', 'name' => 'KC JAKARTA BARAT 11000', 'city' => 'Jakarta Barat', 'province' => 'DKI Jakarta', 'phone_wa' => '6281210001100', 'pic_name' => 'CS Delivery KC Jakbar', 'notes' => 'Grogol, Kebon Jeruk, Cengkareng, Kalideres, Palmerah'],
            ['code' => '13000', 'name' => 'KC JAKARTA TIMUR 13000', 'city' => 'Jakarta Timur', 'province' => 'DKI Jakarta', 'phone_wa' => '6281210001300', 'pic_name' => 'CS Antaran KC Jaktim', 'notes' => 'Matraman, Jatinegara, Duren Sawit, Kramat Jati, Ciracas'],
            ['code' => '14000', 'name' => 'KC JAKARTA UTARA 14000', 'city' => 'Jakarta Utara', 'province' => 'DKI Jakarta', 'phone_wa' => '6281210001400', 'pic_name' => 'CS Antaran KC Jakut', 'notes' => 'Tanjung Priok, Kelapa Gading, Pluit, Koja, Cilincing'],

            // Jawa Barat & Banten
            ['code' => '40000', 'name' => 'KCU BANDUNG 40000', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'phone_wa' => '6281240004000', 'pic_name' => 'CS Antaran Bandung Raya', 'notes' => 'Kota Bandung'],
            ['code' => '40500', 'name' => 'KC CIMAHI 40500', 'city' => 'Cimahi', 'province' => 'Jawa Barat', 'phone_wa' => '6281240504050', 'pic_name' => 'CS KC Cimahi & Bandung Barat', 'notes' => 'Cimahi, Padalarang, Lembang, Kab Bandung Barat'],
            ['code' => '16000', 'name' => 'KCU BOGOR 16000', 'city' => 'Bogor', 'province' => 'Jawa Barat', 'phone_wa' => '6281216001600', 'pic_name' => 'CS Antaran Bogor', 'notes' => 'Kota & Kab. Bogor, Cibinong'],
            ['code' => '16400', 'name' => 'KC DEPOK 16400', 'city' => 'Depok', 'province' => 'Jawa Barat', 'phone_wa' => '6281216401640', 'pic_name' => 'CS Delivery KC Depok', 'notes' => 'Margonda, Cimanggis, Sawangan, Cinere'],
            ['code' => '17000', 'name' => 'KCU BEKASI 17000', 'city' => 'Bekasi', 'province' => 'Jawa Barat', 'phone_wa' => '6281217001700', 'pic_name' => 'Helpdesk Antaran Bekasi', 'notes' => 'Kota & Kab. Bekasi, Cikarang, Tambun'],
            ['code' => '15000', 'name' => 'KCU TANGERANG 15000', 'city' => 'Tangerang', 'province' => 'Banten', 'phone_wa' => '6281215001500', 'pic_name' => 'CS Pos KCU Tangerang', 'notes' => 'Kota & Kab. Tangerang, Cikokol, Karawaci, Cikupa'],
            ['code' => '15400', 'name' => 'KC TANGERANG SELATAN 15400', 'city' => 'Tangerang Selatan', 'province' => 'Banten', 'phone_wa' => '6281215401540', 'pic_name' => 'CS Delivery KC Tangsel', 'notes' => 'Ciputat, Pamulang, BSD, Serpong, Bintaro'],
            ['code' => '42100', 'name' => 'KC SERANG 42100', 'city' => 'Serang', 'province' => 'Banten', 'phone_wa' => '6281242104210', 'pic_name' => 'CS KC Serang & Cilegon', 'notes' => 'Serang, Cilegon, Pandeglang, Lebak'],
            ['code' => '45100', 'name' => 'KCU CIREBON 45100', 'city' => 'Cirebon', 'province' => 'Jawa Barat', 'phone_wa' => '6281245104510', 'pic_name' => 'CS Antaran Cirebon', 'notes' => 'Kota & Kab Cirebon, Indramayu, Majalengka, Kuningan'],
            ['code' => '46100', 'name' => 'KC TASIKMALAYA 46100', 'city' => 'Tasikmalaya', 'province' => 'Jawa Barat', 'phone_wa' => '6281246104610', 'pic_name' => 'CS KC Tasikmalaya', 'notes' => 'Tasikmalaya, Ciamis, Banjar, Pangandaran, Garut'],
            ['code' => '43100', 'name' => 'KC SUKABUMI 43100', 'city' => 'Sukabumi', 'province' => 'Jawa Barat', 'phone_wa' => '6281243104310', 'pic_name' => 'CS KC Sukabumi', 'notes' => 'Kota & Kab. Sukabumi, Cianjur'],

            // Jawa Tengah & DIY
            ['code' => '53200', 'name' => 'KC CILACAP 53200', 'city' => 'Cilacap', 'province' => 'Jawa Tengah', 'phone_wa' => '6281253205320', 'pic_name' => 'CS Antaran KC Cilacap', 'notes' => 'Cilacap Kota, Kroya, Majenang, Sidareja'],
            ['code' => '53100', 'name' => 'KCU PURWOKERTO 53100', 'city' => 'Banyumas', 'province' => 'Jawa Tengah', 'phone_wa' => '6281253105310', 'pic_name' => 'CS Delivery KCU Purwokerto', 'notes' => 'Banyumas, Purbalingga, Banjarnegara'],
            ['code' => '50000', 'name' => 'KCU SEMARANG 50000', 'city' => 'Semarang', 'province' => 'Jawa Tengah', 'phone_wa' => '6281250005000', 'pic_name' => 'CS Pos Antaran Semarang (Johar)', 'notes' => 'Kota & Kab. Semarang, Salatiga, Kendal, Demak'],
            ['code' => '57100', 'name' => 'KCU SOLO 57100', 'city' => 'Surakarta', 'province' => 'Jawa Tengah', 'phone_wa' => '6281257105710', 'pic_name' => 'CS Delivery Solo Raya (Gladak)', 'notes' => 'Surakarta, Sukoharjo, Karanganyar, Boyolali, Klaten, Sragen, Wonogiri'],
            ['code' => '55000', 'name' => 'KCU YOGYAKARTA 55000', 'city' => 'Yogyakarta', 'province' => 'DI Yogyakarta', 'phone_wa' => '6281255005500', 'pic_name' => 'CS Antaran Pos DIY (Malioboro)', 'notes' => 'Kota Yogyakarta, Sleman, Bantul, Kulon Progo, Gunungkidul'],
            ['code' => '59300', 'name' => 'KC KUDUS 59300', 'city' => 'Kudus', 'province' => 'Jawa Tengah', 'phone_wa' => '6281259305930', 'pic_name' => 'CS KC Kudus', 'notes' => 'Kudus, Pati, Jepara, Rembang, Blora, Grobogan'],
            ['code' => '51100', 'name' => 'KC PEKALONGAN 51100', 'city' => 'Pekalongan', 'province' => 'Jawa Tengah', 'phone_wa' => '6281251105110', 'pic_name' => 'CS KC Pekalongan', 'notes' => 'Pekalongan, Batang, Pemalang, Tegal, Brebes'],
            ['code' => '56100', 'name' => 'KC MAGELANG 56100', 'city' => 'Magelang', 'province' => 'Jawa Tengah', 'phone_wa' => '6281256105610', 'pic_name' => 'CS KC Magelang', 'notes' => 'Kota & Kab. Magelang, Temanggung, Wonosobo, Purworejo, Kebumen'],

            // Jawa Timur & Madura
            ['code' => '60000', 'name' => 'KCU SURABAYA 60000', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'phone_wa' => '6281260006000', 'pic_name' => 'CS Antaran Pos Surabaya (Kebonrojo)', 'notes' => 'Kota Surabaya, Sidoarjo, Gresik'],
            ['code' => '65100', 'name' => 'KCU MALANG 65100', 'city' => 'Malang', 'province' => 'Jawa Timur', 'phone_wa' => '6281265106510', 'pic_name' => 'CS Delivery KC Malang', 'notes' => 'Kota & Kab. Malang, Batu'],
            ['code' => '69400', 'name' => 'KC SUMENEP 69400', 'city' => 'Sumenep', 'province' => 'Jawa Timur', 'phone_wa' => '6281269406940', 'pic_name' => 'CS KC Sumenep Madura', 'notes' => 'Sumenep, Batuan, Kalianget, Madura Timur'],
            ['code' => '69300', 'name' => 'KC PAMEKASAN 69300', 'city' => 'Pamekasan', 'province' => 'Jawa Timur', 'phone_wa' => '6281269306930', 'pic_name' => 'CS KC Pamekasan & Madura', 'notes' => 'Pamekasan, Sampang, Bangkalan, Madura'],
            ['code' => '68100', 'name' => 'KC JEMBER 68100', 'city' => 'Jember', 'province' => 'Jawa Timur', 'phone_wa' => '6281268106810', 'pic_name' => 'CS KC Jember', 'notes' => 'Jember, Lumajang, Bondowoso, Situbondo, Banyuwangi'],
            ['code' => '64100', 'name' => 'KC KEDIRI 64100', 'city' => 'Kediri', 'province' => 'Jawa Timur', 'phone_wa' => '6281264106410', 'pic_name' => 'CS KC Kediri', 'notes' => 'Kediri, Blitar, Tulungagung, Trenggalek, Nganjuk'],
            ['code' => '63100', 'name' => 'KC MADIUN 63100', 'city' => 'Madiun', 'province' => 'Jawa Timur', 'phone_wa' => '6281263106310', 'pic_name' => 'CS KC Madiun', 'notes' => 'Madiun, Ponorogo, Magetan, Ngawi, Pacitan'],
            ['code' => '67100', 'name' => 'KC PASURUAN 67100', 'city' => 'Pasuruan', 'province' => 'Jawa Timur', 'phone_wa' => '6281267106710', 'pic_name' => 'CS KC Pasuruan & Probolinggo', 'notes' => 'Pasuruan, Probolinggo, Bangil'],

            // Sumatera Utara & Aceh
            ['code' => '20000', 'name' => 'KCU MEDAN 20000', 'city' => 'Medan', 'province' => 'Sumatera Utara', 'phone_wa' => '6281220002000', 'pic_name' => 'CS Antaran Pos KCU Medan', 'notes' => 'Medan, Deli Serdang, Binjai, Langkat, Karo'],
            ['code' => '21400', 'name' => 'KC RANTAUPRAPAT 21400', 'city' => 'Labuhanbatu', 'province' => 'Sumatera Utara', 'phone_wa' => '6281221402140', 'pic_name' => 'CS KC Rantauprapat & Labuhanbatu Raya', 'notes' => 'Labuhanbatu, Labuhanbatu Selatan (Labusel), Labuhanbatu Utara (Labura), Kotapinang'],
            ['code' => '21100', 'name' => 'KC PEMATANG SIANTAR 21100', 'city' => 'Pematangsiantar', 'province' => 'Sumatera Utara', 'phone_wa' => '6281221102110', 'pic_name' => 'CS KC Pematangsiantar', 'notes' => 'Pematangsiantar, Simalungun, Toba, Samosir, Tapanuli'],
            ['code' => '22800', 'name' => 'KC GUNUNGSITOLI 22800', 'city' => 'Gunungsitoli', 'province' => 'Sumatera Utara', 'phone_wa' => '6281222802280', 'pic_name' => 'CS KC Nias', 'notes' => 'Pulau Nias, Gunungsitoli, Nias Selatan'],
            ['code' => '23000', 'name' => 'KCU BANDA ACEH 23000', 'city' => 'Banda Aceh', 'province' => 'Aceh', 'phone_wa' => '6281223002300', 'pic_name' => 'CS Antaran Banda Aceh & Sabang', 'notes' => 'Banda Aceh, Aceh Besar, Sabang, Pidie'],
            ['code' => '24200', 'name' => 'KC BIREUEN 24200', 'city' => 'Bireuen', 'province' => 'Aceh', 'phone_wa' => '6281224202420', 'pic_name' => 'CS KC Bireuen & Gandapura', 'notes' => 'Bireuen, Gandapura, Peusangan, Samalanga'],
            ['code' => '24500', 'name' => 'KC TAKENGON 24500', 'city' => 'Aceh Tengah', 'province' => 'Aceh', 'phone_wa' => '6281224502450', 'pic_name' => 'CS KC Takengon & Dataran Gayo', 'notes' => 'Takengon, Aceh Tengah, Bener Meriah, Gayo Lues'],
            ['code' => '24300', 'name' => 'KC LHOKSEUMAWE 24300', 'city' => 'Lhokseumawe', 'province' => 'Aceh', 'phone_wa' => '6281224302430', 'pic_name' => 'CS KC Lhokseumawe', 'notes' => 'Lhokseumawe, Aceh Utara, Aceh Timur, Langsa, Aceh Tamiang'],
            ['code' => '23600', 'name' => 'KC MEULABOH 23600', 'city' => 'Aceh Barat', 'province' => 'Aceh', 'phone_wa' => '6281223602360', 'pic_name' => 'CS KC Meulaboh & Pantai Barat', 'notes' => 'Meulaboh, Nagan Raya, Aceh Barat Daya, Aceh Selatan, Subulussalam, Singkil'],

            // Riau & Kepri
            ['code' => '28000', 'name' => 'KCU PEKANBARU 28000', 'city' => 'Pekanbaru', 'province' => 'Riau', 'phone_wa' => '6281228002800', 'pic_name' => 'CS Antaran Pos Riau (Sudirman)', 'notes' => 'Pekanbaru, Kampar, Siak, Pelalawan, Rohul, Rohil'],
            ['code' => '29200', 'name' => 'KC TEMBILAHAN 29200', 'city' => 'Indragiri Hilir', 'province' => 'Riau', 'phone_wa' => '6281229202920', 'pic_name' => 'CS KC Tembilahan & Inhil', 'notes' => 'Tembilahan, Bagan Jaya, Indragiri Hilir (Inhil), Rengat, Indragiri Hulu (Inhu)'],
            ['code' => '29400', 'name' => 'KCU BATAM 29400', 'city' => 'Batam', 'province' => 'Kepulauan Riau', 'phone_wa' => '6281229402940', 'pic_name' => 'CS Antaran Pos Batam (Batam Center)', 'notes' => 'Kota Batam, Barelang, Karimun'],
            ['code' => '29100', 'name' => 'KC TANJUNGPINANG 29100', 'city' => 'Tanjungpinang', 'province' => 'Kepulauan Riau', 'phone_wa' => '6281229102910', 'pic_name' => 'CS KC Tanjungpinang & Bintan', 'notes' => 'Tanjungpinang, Bintan, Penaga, Lingga, Natuna, Anambas'],

            // Sumatera Barat, Jambi, Sumsel, Lampung, Bengkulu
            ['code' => '25000', 'name' => 'KCU PADANG 25000', 'city' => 'Padang', 'province' => 'Sumatera Barat', 'phone_wa' => '6281225002500', 'pic_name' => 'CS Pos KCU Padang', 'notes' => 'Padang, Pariaman, Solok, Pesisir Selatan, Mentawai'],
            ['code' => '26100', 'name' => 'KC BUKITTINGGI 26100', 'city' => 'Bukittinggi', 'province' => 'Sumatera Barat', 'phone_wa' => '6281226102610', 'pic_name' => 'CS KC Bukittinggi & Agam', 'notes' => 'Bukittinggi, Agam, Payakumbuh, Limapuluh Kota, Pasaman, Tanah Datar'],
            ['code' => '36100', 'name' => 'KCU JAMBI 36100', 'city' => 'Jambi', 'province' => 'Jambi', 'phone_wa' => '6281236103610', 'pic_name' => 'CS Antaran Jambi', 'notes' => 'Kota Jambi, Muaro Jambi, Batanghari, Bungo, Tebo, Merangin, Kerinci'],
            ['code' => '30000', 'name' => 'KCU PALEMBANG 30000', 'city' => 'Palembang', 'province' => 'Sumatera Selatan', 'phone_wa' => '6281230003000', 'pic_name' => 'CS Antaran Palembang (Merdeka)', 'notes' => 'Palembang, Banyuasin, Ogan Ilir, OKI, Prabumulih, Muara Enim, Lahat, Lubuklinggau'],
            ['code' => '38000', 'name' => 'KC BENGKULU 38000', 'city' => 'Bengkulu', 'province' => 'Bengkulu', 'phone_wa' => '6281238003800', 'pic_name' => 'CS KC Bengkulu', 'notes' => 'Kota Bengkulu, Rejang Lebong, Bengkulu Utara, Mukomuko, Seluma, Manna'],
            ['code' => '35100', 'name' => 'KCU BANDAR LAMPUNG 35100', 'city' => 'Bandar Lampung', 'province' => 'Lampung', 'phone_wa' => '6281235103510', 'pic_name' => 'CS Pos Lampung', 'notes' => 'Bandar Lampung, Metro, Lampung Selatan, Lampung Tengah, Lampung Utara, Pringsewu, Tulang Bawang'],
            ['code' => '33100', 'name' => 'KC PANGKALPINANG 33100', 'city' => 'Pangkalpinang', 'province' => 'Kepulauan Bangka Belitung', 'phone_wa' => '6281233103310', 'pic_name' => 'CS Pos Bangka Belitung', 'notes' => 'Pangkalpinang, Bangka, Belitung, Tanjungpandan'],

            // Bali, NTB, NTT
            ['code' => '80000', 'name' => 'KCU DENPASAR 80000', 'city' => 'Denpasar', 'province' => 'Bali', 'phone_wa' => '6281280008000', 'pic_name' => 'CS Antaran Bali (Renon)', 'notes' => 'Denpasar, Badung, Gianyar, Tabanan, Buleleng, Karangasem, Jembrana, Klungkung, Bangli'],
            ['code' => '83000', 'name' => 'KCU MATARAM 83000', 'city' => 'Mataram', 'province' => 'Nusa Tenggara Barat', 'phone_wa' => '6281283008300', 'pic_name' => 'CS Antaran Lombok & Sumbawa', 'notes' => 'Mataram, Lombok Barat, Lombok Tengah, Lombok Timur, Sumbawa, Bima'],
            ['code' => '85000', 'name' => 'KCU KUPANG 85000', 'city' => 'Kupang', 'province' => 'Nusa Tenggara Timur', 'phone_wa' => '6281285008500', 'pic_name' => 'CS Antaran Pos NTT', 'notes' => 'Kupang, Timor, Flores, Ende, Maumere, Sumba, Labuan Bajo'],

            // Kalimantan
            ['code' => '78000', 'name' => 'KCU PONTIANAK 78000', 'city' => 'Pontianak', 'province' => 'Kalimantan Barat', 'phone_wa' => '6281278007800', 'pic_name' => 'CS Pos Kalbar (Rahadi Usman)', 'notes' => 'Pontianak, Kubu Raya, Singkawang, Sambas, Ketapang, Sanggau, Sintang'],
            ['code' => '70000', 'name' => 'KCU BANJARMASIN 70000', 'city' => 'Banjarmasin', 'province' => 'Kalimantan Selatan', 'phone_wa' => '6281270007000', 'pic_name' => 'CS Pos Banjarmasin & Kalsel', 'notes' => 'Banjarmasin, Banjar, Barito Kuala, Tanah Laut'],
            ['code' => '70700', 'name' => 'KC BANJARBARU 70700', 'city' => 'Banjarbaru', 'province' => 'Kalimantan Selatan', 'phone_wa' => '6281270707070', 'pic_name' => 'CS KC Banjarbaru', 'notes' => 'Banjarbaru, Sungai Ulin, Martapura, Landasan Ulin'],
            ['code' => '73100', 'name' => 'KCU PALANGKARAYA 73100', 'city' => 'Palangka Raya', 'province' => 'Kalimantan Tengah', 'phone_wa' => '6281273107310', 'pic_name' => 'CS Pos Palangka Raya & Kalteng', 'notes' => 'Palangka Raya, Kotawaringin Timur (Sampit), Kotawaringin Barat (Pangkalan Bun), Kapuas'],
            ['code' => '73800', 'name' => 'KC MUARA TEWEH 73800', 'city' => 'Barito Utara', 'province' => 'Kalimantan Tengah', 'phone_wa' => '6281273807380', 'pic_name' => 'CS KC Muara Teweh & Barito Raya', 'notes' => 'Muara Teweh, Barito Utara, Barito Selatan, Barito Timur, Murung Raya'],
            ['code' => '75000', 'name' => 'KCU SAMARINDA 75000', 'city' => 'Samarinda', 'province' => 'Kalimantan Timur', 'phone_wa' => '6281275007500', 'pic_name' => 'CS Antaran Samarinda & IKN', 'notes' => 'Samarinda, Kutai Kartanegara, Bontang, Kutai Timur, Berau'],
            ['code' => '76100', 'name' => 'KCU BALIKPAPAN 76100', 'city' => 'Balikpapan', 'province' => 'Kalimantan Timur', 'phone_wa' => '6281276107610', 'pic_name' => 'CS Antaran Balikpapan', 'notes' => 'Balikpapan, Penajam Paser Utara, Paser'],
            ['code' => '77100', 'name' => 'KC TARAKAN 77100', 'city' => 'Tarakan', 'province' => 'Kalimantan Utara', 'phone_wa' => '6281277107710', 'pic_name' => 'CS Pos Kaltara', 'notes' => 'Tarakan, Bulungan (Tanjung Selor), Nunukan, Malinau'],

            // Sulawesi
            ['code' => '90000', 'name' => 'KCU MAKASSAR 90000', 'city' => 'Makassar', 'province' => 'Sulawesi Selatan', 'phone_wa' => '6281290009000', 'pic_name' => 'CS Antaran Makassar (Slamet Riyadi)', 'notes' => 'Makassar, Gowa, Maros, Pangkep, Barru, Parepare, Bone, Palopo'],
            ['code' => '94000', 'name' => 'KCU PALU 94000', 'city' => 'Palu', 'province' => 'Sulawesi Tengah', 'phone_wa' => '6281294009400', 'pic_name' => 'CS Antaran Pos Sulteng', 'notes' => 'Kota Palu, Donggala, Toaya, Sigi, Parigi Moutong, Poso, Toli-Toli'],
            ['code' => '94700', 'name' => 'KC LUWUK 94700', 'city' => 'Banggai', 'province' => 'Sulawesi Tengah', 'phone_wa' => '6281294709470', 'pic_name' => 'CS KC Luwuk & Banggai Raya', 'notes' => 'Luwuk, Singkoyo, Banggai, Banggai Laut, Banggai Kepulauan, Morowali, Tojo Una-Una'],
            ['code' => '95000', 'name' => 'KCU MANADO 95000', 'city' => 'Manado', 'province' => 'Sulawesi Utara', 'phone_wa' => '6281295009500', 'pic_name' => 'CS Antaran Manado & Sulut', 'notes' => 'Manado, Bitung, Tomohon, Minahasa, Kotamobagu, Sangihe, Talaud'],
            ['code' => '93000', 'name' => 'KCU KENDARI 93000', 'city' => 'Kendari', 'province' => 'Sulawesi Tenggara', 'phone_wa' => '6281293009300', 'pic_name' => 'CS Pos Sultra', 'notes' => 'Kendari, Konawe, Kolaka, Baubau, Muna, Buton, Wakatobi'],
            ['code' => '96100', 'name' => 'KC GORONTALO 96100', 'city' => 'Gorontalo', 'province' => 'Gorontalo', 'phone_wa' => '6281296109610', 'pic_name' => 'CS KC Gorontalo', 'notes' => 'Kota & Kab. Gorontalo, Bone Bolango, Pohuwato, Boalemo'],
            ['code' => '91500', 'name' => 'KC MAMUJU 91500', 'city' => 'Mamuju', 'province' => 'Sulawesi Barat', 'phone_wa' => '6281291509150', 'pic_name' => 'CS Pos Sulbar', 'notes' => 'Mamuju, Majene, Polewali Mandar, Pasangkayu, Mamasa'],

            // Maluku & Papua
            ['code' => '97000', 'name' => 'KCU AMBON 97000', 'city' => 'Ambon', 'province' => 'Maluku', 'phone_wa' => '6281297009700', 'pic_name' => 'CS Antaran Pos Maluku', 'notes' => 'Ambon, Maluku Tengah, Seram, Buru, Tual, Kepulauan Aru'],
            ['code' => '97700', 'name' => 'KC TERNATE 97700', 'city' => 'Ternate', 'province' => 'Maluku Utara', 'phone_wa' => '6281297709770', 'pic_name' => 'CS Pos Malut', 'notes' => 'Ternate, Tidore, Halmahera'],
            ['code' => '99000', 'name' => 'KCU JAYAPURA 99000', 'city' => 'Jayapura', 'province' => 'Papua', 'phone_wa' => '6281299009900', 'pic_name' => 'CS Antaran Pos Papua', 'notes' => 'Jayapura, Keerom, Sarmi, Biak, Yapen, Merauke, Wamena, Timika, Nabire, Sorong, Manokwari'],
            ['code' => '98400', 'name' => 'KC SORONG 98400', 'city' => 'Sorong', 'province' => 'Papua Barat Daya', 'phone_wa' => '6281298409840', 'pic_name' => 'CS Pos Sorong & Raja Ampat', 'notes' => 'Kota & Kab. Sorong, Raja Ampat, Tambrauw, Maybrat'],
        ];

        foreach ($offices as $data) {
            PostOffice::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        PostOffice::clearCache();
    }
}
