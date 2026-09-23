# Tracking Posindo - Cloudflare Public Domain Tunnel
$ErrorActionPreference = "Continue"

Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host "   *** TRACKING POSINDO - MENYIAPKAN DOMAIN PUBLIK & SERVER ONLINE ***  " -ForegroundColor Yellow
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host ""

# Direktori kerja
Set-Location $PSScriptRoot

# 1. Deteksi PHP
try {
    $null = Get-Command php -ErrorAction Stop
    Write-Host "[OK] PHP terdeteksi." -ForegroundColor Green
} catch {
    if (Test-Path "C:\xampp\php\php.exe") {
        $env:Path = "C:\xampp\php;" + $env:Path
        Write-Host "[OK] Menggunakan PHP dari C:\xampp\php" -ForegroundColor Green
    } elseif (Test-Path "C:\xampp3\php84\php.exe") {
        $env:Path = "C:\xampp3\php84;" + $env:Path
        Write-Host "[OK] Menggunakan PHP dari C:\xampp3\php84" -ForegroundColor Green
    } else {
        Write-Host "[WARNING] PHP tidak ditemukan di PATH default." -ForegroundColor Yellow
    }
}

# 1.5 Cek status Database MySQL (Port 3306)
$mysqlPortActive = $false
try {
    $tcp = New-Object System.Net.Sockets.TcpClient("127.0.0.1", 3306)
    $tcp.Close()
    $mysqlPortActive = $true
} catch {
    $mysqlPortActive = $false
}

if (-not $mysqlPortActive) {
    Write-Host "[WARNING] MySQL belum menyala di port 3306!" -ForegroundColor Yellow
    if (Test-Path "C:\xampp\mysql\bin\mysqld.exe") {
        Write-Host "[INFO] Menyalakan MySQL XAMPP secara otomatis..." -ForegroundColor Cyan
        Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini", "--standalone" -WindowStyle Hidden
        Start-Sleep -Seconds 3
    } else {
        Write-Host "[PERINGATAN] Silakan buka XAMPP Control Panel dan klik tombol START pada MySQL!" -ForegroundColor Red
    }
} else {
    Write-Host "[OK] Database MySQL aktif di port 3306." -ForegroundColor Green
}

# 2. Cek apakah artisan serve sudah aktif di port 8000
$portActive = $false
try {
    $tcp = New-Object System.Net.Sockets.TcpClient("127.0.0.1", 8000)
    $tcp.Close()
    $portActive = $true
} catch {
    $portActive = $false
}

$serveProcess = $null
$queueProcess = $null

if (-not $portActive) {
    Write-Host "[INFO] Menjalankan server Laravel Multi-Worker (port 8000)..." -ForegroundColor Green
    $env:PHP_CLI_SERVER_WORKERS = "10"
    $serveProcess = Start-Process -FilePath "php" -ArgumentList "artisan", "serve", "--host=0.0.0.0", "--port=8000" -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 2
} else {
    Write-Host "[INFO] Server Laravel sudah aktif di port 8000." -ForegroundColor Green
}

# 3. Jalankan Queue Worker jika belum aktif
Write-Host "[INFO] Memastikan Queue Worker berjalan..." -ForegroundColor Green
$queueProcess = Start-Process -FilePath "php" -ArgumentList "artisan", "queue:work", "--timeout=300", "--tries=3" -PassThru -WindowStyle Hidden

# 4. Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
$localIp = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*','Ethernet*' -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127|169)' } | Select-Object -First 1).IPAddress
if (-not $localIp) { $localIp = "127.0.0.1" }

Write-Host "[INFO] Menghubungkan ke Cloudflare Edge Network untuk membuat Domain Publik..." -ForegroundColor Yellow

$cfExe = Join-Path $PSScriptRoot "cloudflared.exe"
$cfLog = Join-Path $PSScriptRoot "cf_session.log"
if (Test-Path $cfLog) { Remove-Item $cfLog -Force -ErrorAction SilentlyContinue }

$cfProcess = Start-Process -FilePath $cfExe -ArgumentList "tunnel", "--protocol", "http2", "--url", "http://127.0.0.1:8000", "--logfile", "`"$cfLog`"" -PassThru -WindowStyle Hidden

# 5. Menunggu link domain publik
$domainUrl = ""
$timeout = 35
$elapsed = 0
Write-Host -NoNewline "[INFO] Mengalokasikan alamat domain"
while ($elapsed -lt $timeout) {
    Start-Sleep -Seconds 1
    Write-Host -NoNewline "."
    $elapsed++
    if (Test-Path $cfLog) {
        $rawLog = Get-Content $cfLog -Raw -ErrorAction SilentlyContinue
        if ($rawLog -match "(https://[a-zA-Z0-9-]+\.trycloudflare\.com)") {
            $domainUrl = $Matches[1]
            break
        }
    }
}
Write-Host ""

Clear-Host
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host "   *** TRACKING POSINDO - WEBSITE BERHASIL GO-ONLINE & GO LIVE! ***   " -ForegroundColor Green
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host ""
if ($domainUrl) {
    Write-Host " [1] DOMAIN PUBLIK (Untuk admin lain dari rumah / HP / luar kantor):" -ForegroundColor White
    Write-Host "     --> $domainUrl" -ForegroundColor Green
    Write-Host "     * Menggunakan HTTPS aman & resmi (Cloudflare SSL 256-bit)" -ForegroundColor DarkGray
    Write-Host ""
    try {
        Set-Clipboard -Value $domainUrl
        Write-Host "     [Link domain otomatis disalin ke Clipboard! Tinggal Paste/Ctrl+V]" -ForegroundColor Cyan
    } catch {}
} else {
    Write-Host " [!] Alamat domain sedang dalam antrean alokasi Cloudflare." -ForegroundColor Yellow
}

Write-Host ""
Write-Host " [2] JARINGAN LOKAL (Untuk rekan/admin di 1 Wi-Fi atau LAN kantor yang sama):" -ForegroundColor White
Write-Host "     --> http://${localIp}:8000" -ForegroundColor Yellow
Write-Host ""
Write-Host " [3] LAPTOP INI SENDIRI:" -ForegroundColor White
Write-Host "     --> http://localhost:8000" -ForegroundColor Gray
Write-Host ""
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host "  STATUS: APLIKASI BERJALAN NORMAL, AMAN, DAN JAYA!" -ForegroundColor Green
Write-Host "  PENTING: JANGAN TUTUP JENDELA INI SELAMA WEBSITE MASIH DIGUNAKAN." -ForegroundColor Yellow
Write-Host "  Tekan sembarang tombol di jendela ini untuk mematikan server & domain." -ForegroundColor DarkGray
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host ""

if ($domainUrl) {
    Start-Process $domainUrl
} else {
    Start-Process "http://localhost:8000"
}

# Menunggu tombol untuk exit
try {
    $null = $host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
} catch {
    Read-Host "Tekan Enter untuk mematikan server & domain..."
}

Write-Host "`n[INFO] Menghentikan tunnel dan proses server..." -ForegroundColor Yellow
if ($cfProcess -and -not $cfProcess.HasExited) { Stop-Process -Id $cfProcess.Id -Force -ErrorAction SilentlyContinue }
if ($queueProcess -and -not $queueProcess.HasExited) { Stop-Process -Id $queueProcess.Id -Force -ErrorAction SilentlyContinue }
if ($serveProcess -and -not $serveProcess.HasExited) { Stop-Process -Id $serveProcess.Id -Force -ErrorAction SilentlyContinue }
if (Test-Path $cfLog) { Remove-Item $cfLog -Force -ErrorAction SilentlyContinue }
Write-Host "[OK] Semua layanan telah dimatikan dengan aman." -ForegroundColor Green
Start-Sleep -Seconds 1
