# AGENT.md - Aturan & Panduan Pengembangan Posindo Tracking System

Dokumen ini adalah pedoman baku bagi AI Agent saat mengembangkan, melakukan debugging, atau memodifikasi sistem **Posindo Tracking & CS Follow-Up System**.

---

## 1. Arsitektur & Lingkungan Sistem
- **Framework Backend**: Laravel 11 / PHP 8.2+
- **Database Engine**: **MySQL (MariaDB di XAMPP)**
  - Host: `127.0.0.1:3306`
  - Database: `tracking_posindo`
  - User: `root`, Password: `` (kosong)
  - *DILARANG* mengganti database kembali ke SQLite.
- **Frontend**: React 18, TypeScript, Inertia.js v2, Tailwind CSS, Lucide Icons, Radix UI.
- **Job & Queue**: Database Queue (`QUEUE_CONNECTION=database` di tabel `jobs`).

---

## 2. Aturan Bisnis & Klasifikasi Status (Strict Rules)

### A. Aturan Klasifikasi Status Pengiriman
| Status POS / Keterangan | Kategori Sistem | Warna Badge / Kartu | Keterangan Bisnis |
|---|---|---|---|
| `DELIVERED (RETURN DELIVERY)` / `DITERIMA PENGIRIM` / `DITERIMA MITRA` / `RETUR` | **`RETUR`** | **ORANGE (`#FBBC04`)** | Paket gagal serah & dikembalikan ke penjual/mitra. **Wajib override status DELIVERED biasa.** |
| `DELIVERED` (murni) / `DITERIMA YANG BERSANGKUTAN` / `DITERIMA ORANG SERUMAH` / `DITERIMA KELUARGA` | **`SUKSES`** | **BIRU (`#46BDC6`)** | Paket berhasil sampai ke tangan pembeli. |
| `FAILEDTODELIVERED` / `GAGAL ANTAR` / `KENDALA` / `FOLLOW UP` | **`FOLLOW_UP`** | **KUNING / HIJAU / BIRU TUA** | Paket terkendala di kurir dan memerlukan tindakan CS. |
| `ON PROCESS` / `RUNSHEET` / `INVEHICLE` / `INLOCATION` / `UNBAG` | **`IN_PROCESS`** | **PUTIH (`#FFFFFF`)** | Paket sedang dalam perjalanan logistik POS. |

### B. Aturan Proteksi Data (Upsert)
- Jika data baru dari Google Sheets atau NIPOS berstatus **RETUR / DELIVERED (RETURN DELIVERY)**, status database **HARUS** di-update ke RETUR dan warnanya menjadi **ORANGE**.
- Jangan menimpa data yang sudah berstatus final dengan status default `ON PROCESS`.

---

## 3. Aturan Filter Bulan & Query (Anti Off-by-One Bug)
- Indeks bulan di sistem adalah **1-based (1 = Januari, 2 = Februari, ..., 12 = Desember)**.
- Query database **WAJIB** konsisten:
  ```php
  // BENAR:
  $statsBaseQuery->whereMonth('tanggal_kirim', $mNum);
  $query->whereMonth('tanggal_kirim', $mNum);

  // SALAH (JANGAN DILAKUKAN):
  // $mNum = $mInt + 1; // Menyebabkan pergeseran 1 bulan ke depan!
  ```
- Angka pada Tab Bulan (atas) dan Kartu Statistik (bawah) **HARUS selalu 100% sinkron**.

---

## 4. Google Sheets Sync & Bot Tracking
- **Alur Sinkronisasi Google Sheets**:
  - Dijalankan via AJAX berurutan per sheet (`/sync/discover` lalu `/sync/sheet`) dari frontend (`TopBar.tsx`).
  - Menampilkan progress bar bertema **Emerald (Hijau)** di bawah navbar.
  - Menggunakan Google Apps Script Webhook untuk *Reverse Sync* (mengirim status tracking kembali ke spreadsheet).
- **Bot NIPOS**:
  - Berjalan via background queue worker (`ProcessNiposTrackingJob`).
  - Progress bar bertema **Biru** di bawah navbar.

---

## 5. Standar Kode & Larangan
1. **Dilarang menggunakan `dump()`, `dd()`, atau `echo`** di dalam Service, Import (`ShipmentsImport.php`), Controller, atau Job queue, karena akan merusak respon JSON/Inertia ke browser.
2. Gunakan `Log::info()` atau `Log::error()` untuk keperluan debugging backend.
3. Setelah melakukan perubahan pada file `.tsx` atau `.ts`, **WAJIB menjalankan `npm run build`** untuk memastikan kompilasi Vite sukses tanpa error tipe TypeScript.
4. Setelah melakukan perubahan file PHP, **WAJIB melakukan pengecekan sintaks `php -l <file>`**.

# 🤖 AGENT GUIDELINES & PROJECT CONTEXT

## 📌 Project Overview
**System Name:** Posindo Tracking & CS Follow-Up System  
**Framework:** Laravel (PHP)  
**Database:** MySQL (Local XAMPP)  
**Primary Goal:** Memproses, melacak, dan menyinkronkan status resi NIPos dari Google Sheets secara otomatis, serta menyediakan dashboard pelaporan untuk follow-up cabang/kantor pos tujuan.

---

## 🚫 STRICT RULES & BOUNDARIES (DO NOT VIOLATE)

1. **NO UNNECESSARY REFACTORS:**
   - DILARANG mengubah arsitektur dasar, struktur tabel database, atau mengganti pustaka utama tanpa persetujuan eksplisit.
   - Tetap gunakan **Laravel Eloquent ORM** untuk semua operasi data.

2. **DATABASE INTEGRITY (MySQL):**
   - Jalur koneksi database utama adalah **MySQL** (`DB_CONNECTION=mysql`). JANGAN mengubah koneksi kembali ke SQLite.
   - Kolom nomor resi (`resi` / `nipos`) HARUS diset sebagai `UNIQUE` index untuk mencegah duplikasi data.
   - Gunakan metode `updateOrCreate()` atau *Batch Chunking* saat melakukan impor/sync data massal..

4. **PERFORMANCE & TIMEOUT HANDLING:**
   - Proses impor/sinkronisasi Google Sheets skala besar (ribuan baris) **TIDAK BOLEH** dijalankan secara synchronous di alur HTTP Request biasa untuk mencegah *timeout* atau *blank screen*.
   - Gunakan **Laravel Queue Job** (`php artisan queue:work`) atau proses *chunking* per 500-1000 baris.

---

## 🏗️ CORE FEATURES & ARCHITECTURE

1. **Google Sheets Sync (`php artisan sheets:sync`):**
   - Membaca data Google Sheets secara terstruktur.
   - Wajib melakukan `trim()` pada teks status dan mengabaikan baris header serta baris kosong.
   - Melakukan pemetaan status yang konsisten ke database MySQL.

2. **CS Follow-Up Dashboard:**
   - Menampilkan filter data berdasarkan Status (Delivered, Retur, Inproses), Bulan, Warna/Kategori, dan Kantor Cabang/Seller.
   - Perhitungan total summary harus 100% presisi dan sinkron dengan jumlah riil data di database.

3. **NIPos API Tracking:**
   - Melakukan update status resi secara berkala dari API NIPos tanpa menimpa data yang sudah berstatus final (Delivered/Retur) secara salah.

---

## 🛠️ WORKFLOW FOR AGENT TASKS

Sebelum melakukan eksekusi perintah atau *bug fixing*:
1. **Analisis Masalah:** Cek log error di `storage/logs/laravel.log` atau layar browser.
2. **Eksekusi Minim Dampak:** Perbaiki kode hanya pada file yang bermasalah (misal: Controller, Service, atau Migration tertentu).
3. **Verifikasi Data:** Pastikan data di database MySQL tetap presisi setelah skrip diperbaiki (`php artisan config:clear` jika mengedit file konfigurasi).
