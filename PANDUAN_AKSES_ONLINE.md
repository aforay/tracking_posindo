# 🌐 Panduan Akses Online Domain Resmi & Jaringan Lokal (TRACKO)

Aplikasi **Tracko - Tracking Posindo** telah resmi terhubung dengan domain internet permanen **`https://tracko.my.id`**. Siapapun (baik menggunakan Wi-Fi kantor, LAN, maupun kuota internet HP di luar kantor) dapat langsung mengakses website ini secara online, aman (HTTPS), dan tanpa batas waktu.

---

## 🚀 Cara Menyalakan Server Setiap Pagi

Cukup lakukan **1 langkah mudah**:
1. Double-click file **`run-online.bat`**.
2. Jendela hitam/biru akan otomatis terbuka dan menyiapkan semuanya:
   - Menyalakan Database MySQL secara otomatis jika belum menyala.
   - Menjalankan server Laravel Multi-Worker (Port 8000).
   - Menjalankan Queue Worker otomatis di latar belakang.
   - Menghubungkan tunnel ke Cloudflare Edge Network untuk domain resmi `tracko.my.id`.
3. Setelah beberapa detik, browser akan otomatis terbuka mengarah ke **`https://tracko.my.id`**.
4. Link `https://tracko.my.id` juga otomatis tersalin ke Clipboard laptop Anda, sehingga bisa langsung di-*paste* (Ctrl+V) ke grup WhatsApp tim.

> ⚠️ **PENTING**: Biarkan jendela `run-online.bat` tetap terbuka selama jam kerja. Jangan ditutup (close) karena jendela inilah yang bertindak sebagai mesin server online Anda.

---

## 🔗 Pilihan Jalur Akses

### 1. 🌍 Jalur Utama: Domain Resmi Internet (Rekomendasi Utama)
- **Link**: **`https://tracko.my.id`** atau **`https://www.tracko.my.id`**
- **Kelebihan**:
  - Resmi, permanen selamanya, aman dengan enkripsi SSL/HTTPS.
  - Bisa diakses dari mana saja (HP, laptop lain, di dalam atau di luar kantor).
  - Tidak perlu setting router atau IP manual.

### 2. 🏢 Jalur Cadangan: Jaringan Lokal (Wi-Fi Kantor)
- Jika suatu saat internet kantor mengalami gangguan, rekan satu Wi-Fi tetap bisa mengakses langsung lewat IP lokal yang tertera di layar terminal (contoh: `http://192.168.x.x:8000`).

### 3. 💻 Laptop Server Ini:
- Bisa membuka **`https://tracko.my.id`** maupun **`http://localhost:8000`**.

---

## 🛡️ Akun Login Aplikasi

| Role | Email Login | Kata Sandi Bawaan |
|---|---|---|
| **Administrator** | `admin@posindo.com` | `password` |
| **Customer Service** | `cs@posindo.com` | `password` |

> *Admin dapat mengelola, menambah akun, atau mengubah password pengguna melalui menu Manajemen Pengguna di aplikasi.*
