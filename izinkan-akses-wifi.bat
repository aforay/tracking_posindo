@echo off
:: BatchGotAdmin
:-------------------------------------
REM  --> Check for permissions
>nul 2>&1 "%SYSTEMROOT%\system32\cacls.exe" "%SYSTEMROOT%\system32\config\system"

REM --> If error flag set, we do not have admin.
if '%errorlevel%' NEQ '0' (
    echo [INFO] Meminta hak Administrator untuk membuka port Firewall...
    goto UACPrompt
) else ( goto gotAdmin )

:UACPrompt
    echo Set UAC = CreateObject^("Shell.Application"^) > "%temp%\getadmin.vbs"
    set params = %*:"=""
    echo UAC.ShellExecute "cmd.exe", "/c %~s0 %params%", "", "runas", 1 >> "%temp%\getadmin.vbs"

    "%temp%\getadmin.vbs"
    del "%temp%\getadmin.vbs"
    exit /B

:gotAdmin
    pushd "%CD%"
    CD /D "%~dp0"
:--------------------------------------

title Membuka Akses Port Wi-Fi (Port 8000)
echo ==========================================================
echo   MEMBUKA AKSES PORT 8000 DI WINDOWS FIREWALL
echo ==========================================================
echo.
echo Menambahkan aturan Firewall untuk Port 8000...
netsh advfirewall firewall add rule name="Laravel Tracking Posindo Port 8000" dir=in action=allow protocol=TCP localport=8000 profile=any >nul

if %ERRORLEVEL% EQU 0 (
    echo [BERHASIL] Port 8000 sudah diizinkan di Windows Firewall!
    echo Sekarang laptop lain di jaringan Wi-Fi dapat mengakses:
    echo.
    powershell -NoProfile -Command "$ip = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*','Ethernet*' -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127|169)' } | Select-Object -First 1).IPAddress; Write-Host \"  --> http://${ip}:8000\" -ForegroundColor Green"
    echo.
) else (
    echo [GAGAL] Tidak dapat menambahkan aturan firewall.
)

echo Tekan sembarang tombol untuk keluar...
pause >nul
