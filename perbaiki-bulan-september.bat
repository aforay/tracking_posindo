@echo off
title Perbaiki Data Bulan September
cd /d "%~dp0"

echo ========================================================
echo   MENGEMBALIKAN RESI AKHIR AGUSTUS KE TAB AGUSTUS
echo ========================================================
echo.

php artisan tracker:fix-september

echo.
echo ========================================================
echo Selesai! Silakan refresh (F5) browser Anda.
echo Tab September sekarang sudah 0 (bersih kembali).
echo ========================================================
echo.
pause
