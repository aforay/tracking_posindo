@echo off
title Tracking Posindo - Mode Online & Domain Publik
cd /d "%~dp0"

:: 1. Cek dan picu MySQL XAMPP jika belum menyala
netstat -ano | findstr :3306 >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "MySQL Server (XAMPP)" /D "C:\xampp\mysql" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
    )
)

:: 2. Jalankan PowerShell Runner
echo Memulai Tracking Posindo Online & Domain Publik...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0share_tunnel.ps1"

:: 3. Pembersihan ganda saat jendela selesai / ditutup
for /f "tokens=5" %%p in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING 2^>nul') do (
    taskkill /PID %%p /F >nul 2>nul
)
taskkill /IM cloudflared.exe /F >nul 2>nul

exit /b
