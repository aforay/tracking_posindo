@echo off
echo ============================================
echo  Tracking Posindo - Dev Server
echo  PHP 8.4.24 + Laravel 12
echo ============================================
echo.

:: Tambahkan PHP 8.4 ke PATH (hanya untuk session ini)
set PATH=C:\xampp3\php84;%PATH%

:: Cek PHP version
php -r "echo 'PHP: ' . PHP_VERSION . PHP_EOL;"

echo.
echo Starting dev server...
echo Buka browser ke: http://localhost:8000
echo Tekan Ctrl+C untuk berhenti.
echo.

php artisan serve
