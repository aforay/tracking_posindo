@echo off
setlocal enabledelayedexpansion
title Tracking Posindo - Dev Server

echo =======================================================
echo          TRACKING POSINDO - DEV SERVER
echo =======================================================
echo.

:: Deteksi PHP
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set "PATH=C:\xampp\php;!PATH!"
    ) else if exist "C:\xampp3\php84\php.exe" (
        set "PATH=C:\xampp3\php84;!PATH!"
    )
)

php -r "echo 'PHP: ' . PHP_VERSION . PHP_EOL;"

if not exist ".env" (
    copy .env.example .env >nul
    php artisan key:generate
)

echo.
echo Starting dev server...
echo Buka browser ke: http://localhost:8000
echo Tekan Ctrl+C untuk berhenti.
echo.

start /min cmd /c "timeout /t 2 >nul & start http://localhost:8000"

php artisan serve --port=8000
