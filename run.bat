@echo off
setlocal enabledelayedexpansion
title Tracking Posindo - Server Runner

echo =======================================================
echo          TRACKING POSINDO - AUTOMATIC RUNNER
echo =======================================================
echo.

:: 1. Deteksi PHP
where php >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] PHP terdeteksi di PATH sistem.
) else (
    if exist "C:\xampp\php\php.exe" (
        set "PATH=C:\xampp\php;!PATH!"
        echo [OK] Menggunakan PHP dari C:\xampp\php
    ) else if exist "C:\xampp3\php84\php.exe" (
        set "PATH=C:\xampp3\php84;!PATH!"
        echo [OK] Menggunakan PHP dari C:\xampp3\php84
    ) else if exist "C:\php\php.exe" (
        set "PATH=C:\php;!PATH!"
        echo [OK] Menggunakan PHP dari C:\php
    ) else (
        echo [ERROR] PHP tidak ditemukan! Pastikan XAMPP terinstall di C:\xampp
        pause
        exit /b 1
    )
)

:: Tampilkan Versi PHP
php -r "echo '[INFO] Versi PHP: ' . PHP_VERSION . PHP_EOL;"

:: 2. Pastikan file .env ada
if not exist ".env" (
    echo [INFO] Menyiapkan file konfigurasi .env...
    copy .env.example .env >nul
    php artisan key:generate
)

:: 3. Periksa Status Database MySQL (Port 3306)
netstat -ano | findstr :3306 >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [WARNING] Database MySQL belum menyala di port 3306!
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        echo [INFO] Menyalakan MySQL XAMPP secara otomatis...
        start "MySQL Server (XAMPP)" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
        timeout /t 3 >nul
    ) else (
        echo [PERINGATAN] Silakan buka XAMPP Control Panel dan klik START pada MySQL!
    )
)

:: Jalankan Migrasi Database MySQL
echo [INFO] Memeriksa migrasi database...
php artisan migrate --force
php artisan tracker:fix-september >nul 2>nul


:: 4. Cek apakah frontend build sudah ada
if not exist "public\build\manifest.json" (
    echo [INFO] Build frontend belum ditemukan. Membangun aset dengan NPM...
    where npm >nul 2>nul
    if !ERRORLEVEL! EQU 0 (
        call npm install
        call npm run build
    ) else (
        echo [WARNING] NPM tidak ditemukan. Tampilan frontend mungkin memerlukan build.
    )
)

:: 4.5 Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
set "LOCAL_IP=localhost"
for /f "tokens=*" %%i in ('powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*','Ethernet*' -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127|169)' } | Select-Object -First 1).IPAddress"') do (
    if not "%%i"=="" set "LOCAL_IP=%%i"
)

echo.
echo =======================================================
echo  Server & Queue Worker siap berjalan!
echo  - Komputer Ini         : http://localhost:8000
echo  - Teman Satu Wi-Fi/LAN : http://!LOCAL_IP!:8000
echo  - Queue Worker         : Berjalan otomatis di background
echo.
echo  TIPS: Untuk akses domain publik internet tanpa batas Wi-Fi,
echo        jalankan: run-online.bat
echo.
echo  Tekan Ctrl+C di jendela ini untuk mematikan server.
echo =======================================================
echo.

:: 5. Jalankan Laravel Queue Worker di background
echo [INFO] Menjalankan Queue Worker di background...
start "Tracking Posindo - Queue Worker" /min cmd /c "php artisan queue:work --timeout=300 --tries=3"

:: 6. Buka browser otomatis setelah delay 2 detik di background
start /min cmd /c "timeout /t 2 >nul & start http://localhost:8000"

:: 7. Jalankan server Laravel pada semua interface jaringan (0.0.0.0)
php artisan serve --host=0.0.0.0 --port=8000

:: 8. Bersihkan proses background saat server ditutup
taskkill /FI "WINDOWTITLE eq Tracking Posindo - Queue Worker*" /F >nul 2>nul

pause
