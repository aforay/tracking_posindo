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
            [
                'code' => '10000',
                'name' => 'KCU JAKARTA PUSAT 10000',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'phone_wa' => '6281210001001',
                'pic_name' => 'CS Antaran KC Jakpus',
                'notes' => 'Wilayah Gambir, Senen, Tanah Abang, Menteng, Kemayoran',
            ],
            [
                'code' => '12000',
                'name' => 'KC JAKARTA SELATAN 12000',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'phone_wa' => '6281210001200',
                'pic_name' => 'Helpdesk KC Jaksel (Mampang)',
                'notes' => 'Wilayah Kebayoran, Mampang, Cilandak, Pasar Minggu, Jagakarsa',
            ],
            [
                'code' => '11000',
                'name' => 'KC JAKARTA BARAT 11000',
                'city' => 'Jakarta Barat',
                'province' => 'DKI Jakarta',
                'phone_wa' => '6281210001100',
                'pic_name' => 'CS Delivery KC Jakbar',
                'notes' => 'Wilayah Grogol, Kebon Jeruk, Cengkareng, Kalideres, Palmerah',
            ],
            [
                'code' => '13000',
                'name' => 'KC JAKARTA TIMUR 13000',
                'city' => 'Jakarta Timur',
                'province' => 'DKI Jakarta',
                'phone_wa' => '6281210001300',
                'pic_name' => 'CS Antaran KC Jaktim (Jatinegara)',
                'notes' => 'Wilayah Matraman, Jatinegara, Duren Sawit, Kramat Jati, Ciracas',
            ],
            [
                'code' => '14000',
                'name' => 'KC JAKARTA UTARA 14000',
                'city' => 'Jakarta Utara',
                'province' => 'DKI Jakarta',
                'phone_wa' => '6281210001400',
                'pic_name' => 'CS Antaran KC Jakut',
                'notes' => 'Wilayah Tanjung Priok, Kelapa Gading, Pluit, Koja, Cilincing',
            ],

            // Jawa Barat & Banten
            [
                'code' => '40000',
                'name' => 'KCU BANDUNG 40000',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281240004000',
                'pic_name' => 'CS Antaran Bandung Raya (Asia Afrika)',
                'notes' => 'Wilayah Kota Bandung dan sekitarnya',
            ],
            [
                'code' => '40500',
                'name' => 'KC CIMAHI 40500',
                'city' => 'Cimahi',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281240504050',
                'pic_name' => 'CS KC Cimahi & Kab Bandung Barat',
                'notes' => 'Wilayah Cimahi, Lembang, Padalarang',
            ],
            [
                'code' => '16000',
                'name' => 'KCU BOGOR 16000',
                'city' => 'Bogor',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281216001600',
                'pic_name' => 'CS Antaran Bogor (Ir. H. Juanda)',
                'notes' => 'Wilayah Kota & Kab. Bogor, Cibinong',
            ],
            [
                'code' => '16400',
                'name' => 'KC DEPOK 16400',
                'city' => 'Depok',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281216401640',
                'pic_name' => 'CS Delivery KC Depok',
                'notes' => 'Wilayah Margonda, Cimanggis, Sawangan, Cinere, Pancoran Mas',
            ],
            [
                'code' => '17000',
                'name' => 'KCU BEKASI 17000',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281217001700',
                'pic_name' => 'CS Antaran KC Bekasi',
                'notes' => 'Wilayah Kota & Kab. Bekasi, Cikarang, Tambun',
            ],
            [
                'code' => '15000',
                'name' => 'KCU TANGERANG 15000',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'phone_wa' => '6281215001500',
                'pic_name' => 'CS KC Tangerang Kota & Tangsel',
                'notes' => 'Wilayah Kota Tangerang, BSD, Ciputat, Pamulang, Karawaci',
            ],
            [
                'code' => '45100',
                'name' => 'KC CIREBON 45100',
                'city' => 'Cirebon',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281245104510',
                'pic_name' => 'CS Delivery KC Cirebon',
                'notes' => 'Wilayah Kota & Kab Cirebon, Sumber',
            ],
            [
                'code' => '46100',
                'name' => 'KC TASIKMALAYA 46100',
                'city' => 'Tasikmalaya',
                'province' => 'Jawa Barat',
                'phone_wa' => '6281246104610',
                'pic_name' => 'CS Antaran KC Tasikmalaya',
                'notes' => 'Wilayah Priangan Timur',
            ],

            // Jawa Tengah & DI Yogyakarta
            [
                'code' => '50000',
                'name' => 'KCU SEMARANG 50000',
                'city' => 'Semarang',
                'province' => 'Jawa Tengah',
                'phone_wa' => '6281250005000',
                'pic_name' => 'CS Antaran KC Semarang (Johar)',
                'notes' => 'Wilayah Kota Semarang dan sekitarnya',
            ],
            [
                'code' => '55000',
                'name' => 'KCU YOGYAKARTA 55000',
                'city' => 'Yogyakarta',
                'province' => 'DI Yogyakarta',
                'phone_wa' => '6281255005500',
                'pic_name' => 'CS Delivery KC Yogyakarta (Titik Nol)',
                'notes' => 'Wilayah Kota Yogyakarta, Sleman, Bantul',
            ],
            [
                'code' => '57100',
                'name' => 'KC SURAKARTA (SOLO) 57100',
                'city' => 'Surakarta',
                'province' => 'Jawa Tengah',
                'phone_wa' => '6281257105710',
                'pic_name' => 'CS Antaran KC Solo (Gladag)',
                'notes' => 'Wilayah Solo Raya (Solo, Sukoharjo, Karanganyar)',
            ],
            [
                'code' => '53100',
                'name' => 'KC PURWOKERTO 53100',
                'city' => 'Purwokerto',
                'province' => 'Jawa Tengah',
                'phone_wa' => '6281253105310',
                'pic_name' => 'CS Delivery KC Purwokerto Banyumas',
                'notes' => 'Wilayah Banyumas, Purwokerto',
            ],

            // Jawa Timur
            [
                'code' => '60000',
                'name' => 'KCU SURABAYA 60000',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'phone_wa' => '6281260006000',
                'pic_name' => 'CS Antaran Surabaya (Kebonrojo)',
                'notes' => 'Wilayah Surabaya Pusat, Utara, Timur',
            ],
            [
                'code' => '60400',
                'name' => 'MPC SURABAYA 60400',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'phone_wa' => '6281260406040',
                'pic_name' => 'Mail Processing Center Surabaya',
                'notes' => 'Pusat Pengolahan & Antaran Kiriman Surabaya Selatan/Barat',
            ],
            [
                'code' => '65100',
                'name' => 'KC MALANG 65100',
                'city' => 'Malang',
                'province' => 'Jawa Timur',
                'phone_wa' => '6281265106510',
                'pic_name' => 'CS Antaran KC Malang (Merdeka)',
                'notes' => 'Wilayah Kota Malang, Batu, Kab. Malang',
            ],
            [
                'code' => '61200',
                'name' => 'KC SIDOARJO 61200',
                'city' => 'Sidoarjo',
                'province' => 'Jawa Timur',
                'phone_wa' => '6281261206120',
                'pic_name' => 'CS Antaran KC Sidoarjo',
                'notes' => 'Wilayah Sidoarjo, Waru, Krian',
            ],

            // Sumatera
            [
                'code' => '20000',
                'name' => 'KCU MEDAN 20000',
                'city' => 'Medan',
                'province' => 'Sumatera Utara',
                'phone_wa' => '6281220002000',
                'pic_name' => 'CS Antaran KC Medan (Bukit Barisan)',
                'notes' => 'Wilayah Kota Medan dan sekitarnya',
            ],
            [
                'code' => '30000',
                'name' => 'KCU PALEMBANG 30000',
                'city' => 'Palembang',
                'province' => 'Sumatera Selatan',
                'phone_wa' => '6281230003000',
                'pic_name' => 'CS Delivery KC Palembang (Merdeka)',
                'notes' => 'Wilayah Kota Palembang',
            ],
            [
                'code' => '35000',
                'name' => 'KCU BANDAR LAMPUNG 35000',
                'city' => 'Bandar Lampung',
                'province' => 'Lampung',
                'phone_wa' => '6281235003500',
                'pic_name' => 'CS Antaran KC Bandar Lampung',
                'notes' => 'Wilayah Bandar Lampung dan Lampung Selatan',
            ],
            [
                'code' => '25000',
                'name' => 'KCU PADANG 25000',
                'city' => 'Padang',
                'province' => 'Sumatera Barat',
                'phone_wa' => '6281225002500',
                'pic_name' => 'CS Antaran KC Padang (Bagindo Azizchan)',
                'notes' => 'Wilayah Kota Padang',
            ],
            [
                'code' => '28000',
                'name' => 'KCU PEKANBARU 28000',
                'city' => 'Pekanbaru',
                'province' => 'Riau',
                'phone_wa' => '6281228002800',
                'pic_name' => 'CS Antaran KC Pekanbaru (Jend. Sudirman)',
                'notes' => 'Wilayah Kota Pekanbaru',
            ],

            // Bali & Nusa Tenggara
            [
                'code' => '80000',
                'name' => 'KCU DENPASAR 80000',
                'city' => 'Denpasar',
                'province' => 'Bali',
                'phone_wa' => '6281280008000',
                'pic_name' => 'CS Antaran KC Denpasar (Puputan)',
                'notes' => 'Wilayah Denpasar, Badung, Kuta, Sanur',
            ],
            [
                'code' => '83000',
                'name' => 'KC MATARAM 83000',
                'city' => 'Mataram',
                'province' => 'Nusa Tenggara Barat',
                'phone_wa' => '6281283008300',
                'pic_name' => 'CS KC Mataram Lombok',
                'notes' => 'Wilayah Kota Mataram dan Lombok',
            ],

            // Sulawesi & Kalimantan
            [
                'code' => '90000',
                'name' => 'KCU MAKASSAR 90000',
                'city' => 'Makassar',
                'province' => 'Sulawesi Selatan',
                'phone_wa' => '6281290009000',
                'pic_name' => 'CS Antaran KC Makassar (Slamet Riyadi)',
                'notes' => 'Wilayah Kota Makassar, Gowa, Maros',
            ],
            [
                'code' => '95000',
                'name' => 'KCU MANADO 95000',
                'city' => 'Manado',
                'province' => 'Sulawesi Utara',
                'phone_wa' => '6281295009500',
                'pic_name' => 'CS KC Manado (Sam Ratulangi)',
                'notes' => 'Wilayah Kota Manado dan Minahasa',
            ],
            [
                'code' => '70000',
                'name' => 'KCU BANJARMASIN 70000',
                'city' => 'Banjarmasin',
                'province' => 'Kalimantan Selatan',
                'phone_wa' => '6281270007000',
                'pic_name' => 'CS Antaran KC Banjarmasin (P. Samudera)',
                'notes' => 'Wilayah Kota Banjarmasin, Banjarbaru',
            ],
            [
                'code' => '76100',
                'name' => 'KC BALIKPAPAN 76100',
                'city' => 'Balikpapan',
                'province' => 'Kalimantan Timur',
                'phone_wa' => '6281276107610',
                'pic_name' => 'CS Delivery KC Balikpapan',
                'notes' => 'Wilayah Balikpapan dan IKN',
            ],
            [
                'code' => '75100',
                'name' => 'KC SAMARINDA 75100',
                'city' => 'Samarinda',
                'province' => 'Kalimantan Timur',
                'phone_wa' => '6281275107510',
                'pic_name' => 'CS Antaran KC Samarinda',
                'notes' => 'Wilayah Samarinda dan sekitarnya',
            ],
        ];

        foreach ($offices as $data) {
            PostOffice::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
