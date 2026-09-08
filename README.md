# 📦 Posindo Tracking & CS Follow-Up System

Sistem manajemen dan pelacakan resi Pos Indonesia (NIPOS) terintegrasi dengan Google Spreadsheet dua arah (*bidirectional sync*), dashboard operasional Customer Service (CS), notifikasi WhatsApp otomatis, riwayat audit aktivitas (*audit trail*), serta deteksi keterlambatan SLA.

---

## 🌟 Fitur Utama Sistem

1. **Pelacakan NIPOS Otomatis & Cerdas:**
   - Melacak ratusan hingga ribuan nomor resi Pos Indonesia menggunakan bot latar belakang (*Laravel Queue Worker*).
   - Klasifikasi otomatis status pengiriman: `SUKSES`, `RETUR`, `FOLLOW_UP`, dan `IN_PROCESS`.
   - Mengabaikan resi final agar tidak membebani server logistik.

2. **Sinkronisasi Dua Arah Google Sheets (*Bidirectional Sync*):**
   - **Pull:** Mengambil resi baru dari Google Sheets per tab bulan secara berkala (*chunking* anti-timeout).
   - **Push:** Mengirim kembali pembaruan status, warna baris, dan catatan CS ke Google Sheets secara real-time via Google Apps Script Webhook.

3. **WhatsApp Follow-Up CS (2 Target):**
   - **🏢 Ke Kantor Pos (KC Tujuan):** Template permohonan antaran ulang, konfirmasi alamat, atau tahan retur ke petugas helpdesk/kurir KC tujuan.
   - **👤 Ke Penerima / Pembeli (Customer):** Template chat kendala kurir (*Alamat Belum Jelas/Patokan*, *Rumah Kosong/Antar Ulang*, *Konfirmasi Uang Tunai COD*, dan *Pemberitahuan Darurat Retur*).
   - Otomatis memperbarui status warna resi setelah WhatsApp dibuka.

4. **Timeline Pelacakan Internal & Audit Trail (Shipment Logs):**
   - **Internal Tracking Modal:** Klik nomor resi untuk melihat rute milestone perjalanan paket tanpa perlu login ke web NIPOS eksternal.
   - **Audit Trail CS:** Mencatat siapa staf CS yang memperbarui status, tanggal/jam perubahan, dan riwayat catatan penanganan komplain.

5. **Peringatan Proaktif & Notifikasi Sistem:**
   - **Peringatan Cookie NIPOS Mati:** Banner otomatis jika session cookie NIPOS kedaluwarsa agar Admin segera memperbaruinya.
   - **Peringatan Overdue SLA:** Notifikasi cerdas paket macet (>4 hari belum terkirim) lengkap dengan tombol 1-klik filter.

6. **Manajemen Pengguna & Keamanan:**
   - Otorisasi Role: **Administrator** vs **Customer Service (CS)**.
   - Menu kelola pengguna (Admin dapat menambah staf CS, mengubah role, mereset password, dan menghapus akun).
   - Fitur ganti password mandiri untuk seluruh pengguna.

7. **Pencadangan Database Otomatis (Auto Backup):**
   - Perintah `php artisan db:backup --compress` menyimpan arsip database MySQL terkompresi gzip ke `storage/backups/`.

---

## 🎨 Standar Warna & Aturan Bisnis Klasifikasi

| Kategori Sistem | Warna Badge | Kode Hex | Arti Bisnis & Aksi CS |
|---|---|---|---|
| **`SUKSES`** | **BIRU** | `#46BDC6` | Paket berhasil diserahkan ke penerima / keluarga serumah. Status final. |
| **`RETUR`** | **ORANGE** | `#FBBC04` | Paket gagal serah & dalam proses / telah kembali ke pengirim (*Hold/Return Delivery*). Status final. |
| **`FOLLOW_UP`** | **KUNING** | `#FFFF00` | Sudah di-follow up 1 kali oleh tim CS ke kurir / pembeli. |
| **`FOLLOW_UP`** | **HIJAU** | `#93C47D` | Telah di-follow up 2 kali karena masih ada kendala lanjutan. |
| **`FOLLOW_UP`** | **BIRU TUA** | `#1C4587` | Sudah dieskalasi ke Kantor Pos Pusat / KC Tujuan (*FU POS*). |
| **`IN_PROCESS`** | **PUTIH** | `#FFFFFF` | Paket sedang dalam perjalanan logistik POS (*Manifest / Runsheet / In Location*). |

---

## 💻 Kebutuhan Lingkungan Sistem

- **PHP**: 8.2 atau lebih baru (ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl`, `xml`, `zip` aktif).
- **Database Engine**: **MySQL / MariaDB** (Default di XAMPP port `3306`).
- **Node.js**: v18.x atau v20.x dan **NPM**.
- **Composer**: Dependency manager PHP.

---

## 🚀 Cara Menjalankan Aplikasi

### Metode 1: Otomatis via `run.bat` (Rekomendasi di Windows)
Cukup klik dua kali file `run.bat` di root direktori proyek. Skrip ini otomatis:
1. Mendeteksi PHP di sistem atau folder XAMPP.
2. Memastikan file `.env` dan `APP_KEY` terkonfigurasi.
3. Menjalankan migrasi database MySQL (`php artisan migrate --force`).
4. Membangun aset frontend jika belum ada.
5. Menjalankan Queue Worker di background (`php artisan queue:work`).
6. Membuka browser otomatis ke `http://localhost:8000`.

---

### Metode 2: Menjalankan Secara Manual

1. **Clone dan Install Dependencies:**
   ```bash
   composer install
   npm install
   ```

2. **Konfigurasi File `.env`:**
   Salin file `.env.example` ke `.env`:
   ```env
   APP_NAME="Tracking Posindo"
   APP_ENV=local
   APP_KEY=base64:...
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=tracking_posindo
   DB_USERNAME=root
   DB_PASSWORD=

   QUEUE_CONNECTION=database
   ```

3. **Migrasi Database & Seeding Akun Awal:**
   ```bash
   php artisan migrate --seed
   ```

4. **Kompilasi Aset Frontend (Vite):**
   ```bash
   npm run build
   # Atau mode development:
   # npm run dev
   ```

5. **Jalankan Background Worker & Web Server (2 Terminal):**
   - **Terminal 1 (Queue Worker):**
     ```bash
     php artisan queue:work --timeout=300 --tries=3
     ```
   - **Terminal 2 (Web Server):**
     ```bash
     php artisan serve --port=8000
     ```

---

## 🔑 Akun Login Default

| Role Pengguna | Email Login | Kata Sandi Awal | Hak Akses |
|---|---|---|---|
| **Administrator** | `admin@posindo.com` | `password` | Akses penuh: manajemen user, sync Google Sheets, upload Excel, bot pelacakan, kontak KC pos. |
| **Customer Service** | `cs@posindo.com` | `password` | Akses operasional: follow-up WA, update status & warna, catatan resi, timeline, direktori KC pos. |

> **Catatan:** Kata sandi dapat segera diubah melalui menu profil pengguna (ikon kunci) di pojok kanan atas setelah login.

---

## ⏰ Konfigurasi Otomasi Jadwal (Cron Scheduler)

Jalankan perintah ini di background atau jadwalkan via **Windows Task Scheduler**:
```bash
php artisan schedule:work
```

Daftar tugas otomatis yang berjalan:
- **`sheets:sync`** (Setiap 5 menit) — Menarik data resi baru dari Google Sheets.
- **`sheets:push-updates`** (Setiap 5 menit) — Mengirim status update dari database ke Google Sheets.
- **`nipos:track-all`** (Setiap 15 menit) — Bot pelacakan otomatis NIPOS.
- **`db:backup --compress`** (Setiap hari pukul 02:00) — Backup database MySQL ke `storage/backups`.

---

## 🧪 Pengujian Sistem (Automated Tests)

Jalankan rangkaian test otomatis dengan perintah:
```bash
php artisan test
```
Seluruh skenario pengujian autentikasi, hak akses role, parsing resi NIPOS, sinkronisasi Google Sheets, audit log, dan pencadangan database berstatus **100% PASS**.

---

*Dikembangkan untuk efisiensi logistik, akurasi data pengiriman, dan akselerasi penanganan komplain COD Pos Indonesia.*
