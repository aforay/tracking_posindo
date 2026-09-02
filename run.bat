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

:: 3. Pastikan database SQLite siap
if not exist "database\database.sqlite" (
    echo [INFO] Menyiapkan database SQLite...
    type nul > "database\database.sqlite"
)

:: 4. Jalankan Migrasi Database
echo [INFO] Memeriksa migrasi database...
php artisan migrate --force

:: 5. Cek apakah frontend build sudah ada
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

echo.
echo =======================================================
echo  Server siap berjalan!
echo  Membuka browser otomatis ke: http://localhost:8000
echo  Tekan Ctrl+C di jendela ini untuk mematikan server.
echo =======================================================
echo.

:: 6. Buka browser otomatis setelah delay 2 detik di background
start /min cmd /c "timeout /t 2 >nul & start http://localhost:8000"

:: 7. Jalankan server Laravel
php artisan serve --port=8000

pause
