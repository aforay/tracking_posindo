# 🌐 Panduan Membuka Akses Website (Domain Publik & Jaringan Lokal)

Fitur ini memungkinkan aplikasi **Tracking Posindo** dibuka dan dioperasikan oleh admin atau rekan tim lain secara bersamaan, baik melalui **Domain Publik Internet (HTTPS Aman)** maupun melalui **Jaringan Lokal (Wi-Fi Kantor)**.

---

## 🚀 Cara 1: Membuka Lewat Domain Publik Internet (Bisa Dibuka dari Mana Saja)

Gunakan cara ini jika rekan/admin lain ingin mengakses dari luar kantor, rumah, handphone (HP), atau beda koneksi internet.

### Langkah-langkah:
1. Double-click file **`run-online.bat`** (atau **`buka-akses-online.bat`**).
2. Tunggu 5 - 10 detik hingga sistem membuatkan tunnel resmi Cloudflare.
3. Terminal akan menampilkan tautan domain resmi dengan protokol HTTPS, contohnya:
   ```text
   ========================================================================
      *** TRACKING POSINDO - WEBSITE BERHASIL GO-ONLINE & GO LIVE! ***   
   ========================================================================

    [1] DOMAIN PUBLIK (Untuk admin lain dari rumah / HP / luar kantor):
        --> https://contoh-domain-anda.trycloudflare.com
        * Menggunakan HTTPS aman & resmi (Cloudflare SSL 256-bit)
   ```
4. Link domain publik tersebut **otomatis disalin ke clipboard**, Anda tinggal `Paste / Ctrl+V` dan bagikan ke WhatsApp admin lain!
5. **Penting:** Biarkan jendela terminal tetap terbuka selama admin lain sedang bekerja di web. Jika selesai, tekan sembarang tombol di jendela terminal untuk mematikan tunnel dan server dengan aman.

---

## 🏢 Cara 2: Membuka Lewat Jaringan Lokal (Satu Wi-Fi Kantor)

Gunakan cara ini jika rekan/admin berada dalam satu kantor dan tersambung ke jaringan Wi-Fi / LAN yang sama.

### Langkah-langkah:
1. Double-click file **`run.bat`** seperti biasa.
2. Terminal akan menampilkan IP Wi-Fi komputer Anda, contohnya:
   ```text
   - Komputer Ini         : http://localhost:8000
   - Teman Satu Wi-Fi/LAN : http://192.168.0.156:8000
   ```
3. Berikan link `http://192.168.0.156:8000` kepada admin lain di kantor. Mereka langsung bisa membuka web di browser tanpa perlu koneksi ke internet luar.

---

## 🛡️ Keamanan & Akses Login

Semua admin atau pengguna yang membuka link tersebut akan diarahkan ke halaman login yang aman:

| Role | Email Login | Kata Sandi Awal |
|---|---|---|
| **Administrator** | `admin@posindo.com` | `password` |
| **Customer Service** | `cs@posindo.com` | `password` |

> *Admin dapat menambahkan akun baru khusus untuk masing-masing rekan melalui menu **Manajemen Pengguna**.*
