@echo off
echo ============================================
echo  Tracking Posindo - Queue Worker
echo  Memproses import Excel + bot tracking
echo ============================================
echo.

:: Tambahkan PHP 8.4 ke PATH (hanya untuk session ini)
set PATH=C:\xampp3\php84;%PATH%

php -r "echo 'PHP: ' . PHP_VERSION . PHP_EOL;"

echo.
echo Worker berjalan. Biarkan jendela ini terbuka selama proses import.
echo Tekan Ctrl+C untuk berhenti.
echo.

php artisan queue:work --queue=tracking,default --tries=3 --timeout=1800
