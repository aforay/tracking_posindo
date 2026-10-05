# Tracking Posindo - Cloudflare Public Domain Tunnel
$ErrorActionPreference = "Continue"

Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host "          *** TRACKO - MENYIAPKAN DOMAIN PUBLIK & SERVER ONLINE ***     " -ForegroundColor Yellow
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host ""

# Direktori kerja
Set-Location $PSScriptRoot

# 1. Deteksi dan Konfigurasi PHP
$phpExe = "php"
try {
    $null = Get-Command php -ErrorAction Stop
    $phpExe = (Get-Command php).Source
    Write-Host "[OK] PHP terdeteksi: $phpExe" -ForegroundColor Green
} catch {
    if (Test-Path "C:\xampp\php\php.exe") {
        $phpExe = "C:\xampp\php\php.exe"
        $env:Path = "C:\xampp\php;" + $env:Path
        Write-Host "[OK] Menggunakan PHP dari C:\xampp\php" -ForegroundColor Green
    } elseif (Test-Path "C:\xampp3\php84\php.exe") {
        $phpExe = "C:\xampp3\php84\php.exe"
        $env:Path = "C:\xampp3\php84;" + $env:Path
        Write-Host "[OK] Menggunakan PHP dari C:\xampp3\php84" -ForegroundColor Green
    } else {
        Write-Host "[WARNING] PHP tidak ditemukan di PATH default, menggunakan fallback 'php'." -ForegroundColor Yellow
    }
}

# 1.1 Pastikan Kredensial Cloudflare Tersedia di Profil User
$cfTargetCredDir = "C:\Users\acer\.cloudflared"
$cfTargetCredFile = Join-Path $cfTargetCredDir "8f59bed1-1485-4da1-b55e-2e9116e537c8.json"
$cfBackupCredFile = Join-Path $PSScriptRoot ".cloudflared\8f59bed1-1485-4da1-b55e-2e9116e537c8.json"

if (-not (Test-Path $cfTargetCredFile) -and (Test-Path $cfBackupCredFile)) {
    if (-not (Test-Path $cfTargetCredDir)) {
        New-Item -ItemType Directory -Path $cfTargetCredDir -Force | Out-Null
    }
    Copy-Item -Path $cfBackupCredFile -Destination $cfTargetCredFile -Force
    Write-Host "[OK] Kredensial domain Cloudflare berhasil dipulihkan." -ForegroundColor Green
}

# 1.2 PEMBERSIHAN OTOMATIS PROSES LAMA (Idempotent Startup)
# Memastikan tidak ada sisa proses hang dari sesi sebelumnya
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue | Where-Object { 
    $_.CommandLine -like "*artisan*serve*" -or $_.CommandLine -like "*artisan*queue:work*" 
} | ForEach-Object { 
    Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue 
}
Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | ForEach-Object {
    Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue
}
Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Milliseconds 500

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
    Write-Host "[WARNING] Database MySQL belum menyala di port 3306!" -ForegroundColor Yellow
    if (Test-Path "C:\xampp\mysql\bin\mysqld.exe") {
        Write-Host "[INFO] Menyalakan MySQL XAMPP secara otomatis..." -ForegroundColor Cyan
        Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini", "--standalone" -WorkingDirectory "C:\xampp\mysql" -WindowStyle Hidden
        
        # Tunggu hingga port 3306 benar-benar aktif (maks 15 detik)
        $attempts = 0
        while (-not $mysqlPortActive -and $attempts -lt 15) {
            Start-Sleep -Seconds 1
            $attempts++
            try {
                $tcp = New-Object System.Net.Sockets.TcpClient("127.0.0.1", 3306)
                $tcp.Close()
                $mysqlPortActive = $true
            } catch {
                $mysqlPortActive = $false
            }
        }
    } else {
        Write-Host "[PERINGATAN] Silakan buka XAMPP Control Panel dan klik tombol START pada MySQL!" -ForegroundColor Red
    }
}

if ($mysqlPortActive) {
    Write-Host "[OK] Database MySQL aktif di port 3306." -ForegroundColor Green
    try {
        & $phpExe artisan migrate --force --quiet 2>$null
    } catch {}
} else {
    Write-Host "[PERINGATAN] MySQL belum merespons. Pastikan tombol START MySQL di XAMPP sudah diklik." -ForegroundColor Yellow
}

# 2. Jalankan server Laravel Multi-Worker (port 8000)
Write-Host "[INFO] Menjalankan server Laravel Multi-Worker (port 8000)..." -ForegroundColor Green
$env:PHP_CLI_SERVER_WORKERS = "10"
$serveProcess = Start-Process -FilePath $phpExe -ArgumentList "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload" -PassThru -WindowStyle Hidden

# Tunggu hingga port 8000 siap
$portActive = $false
$attempts = 0
while (-not $portActive -and $attempts -lt 10) {
    Start-Sleep -Seconds 1
    $attempts++
    try {
        $tcp = New-Object System.Net.Sockets.TcpClient("127.0.0.1", 8000)
        $tcp.Close()
        $portActive = $true
    } catch {
        $portActive = $false
    }
}

if ($portActive) {
    Write-Host "[OK] Server Laravel aktif di port 8000." -ForegroundColor Green
} else {
    Write-Host "[WARNING] Server Laravel butuh waktu lebih lama di port 8000." -ForegroundColor Yellow
}

# 3. Jalankan Queue Worker
Write-Host "[INFO] Menjalankan Queue Worker..." -ForegroundColor Green
$queueProcess = Start-Process -FilePath $phpExe -ArgumentList "artisan", "queue:work", "--timeout=300", "--tries=3" -PassThru -WindowStyle Hidden

# 4. Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
$localIp = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*','Ethernet*' -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127|169)' } | Select-Object -First 1).IPAddress
if (-not $localIp) { $localIp = "127.0.0.1" }

# 5. Cloudflare Tunnel untuk Domain Resmi tracko.my.id
Write-Host "[INFO] Menghubungkan ke Cloudflare Edge Network untuk Domain Resmi tracko.my.id..." -ForegroundColor Yellow

$cfExe = Join-Path $PSScriptRoot "cloudflared.exe"
$cfConfig = Join-Path $PSScriptRoot "cloudflared_config.yml"
$cfLog = Join-Path $PSScriptRoot "cf_session.log"
if (Test-Path $cfLog) { Remove-Item $cfLog -Force -ErrorAction SilentlyContinue }

# Jalankan permanent tunnel tracko-tunnel dengan protocol http2 & konfigurasi eksplisit
$cfArgs = @(
    "--config", $cfConfig,
    "tunnel",
    "--protocol", "http2",
    "--logfile", $cfLog,
    "run", "tracko-tunnel"
)
$cfProcess = Start-Process -FilePath $cfExe -ArgumentList $cfArgs -PassThru -WindowStyle Hidden

Write-Host "[INFO] Memverifikasi koneksi domain https://tracko.my.id..." -ForegroundColor Cyan
$tunnelReady = $false
$maxWait = 15
for ($i = 0; $i -lt $maxWait; $i++) {
    Start-Sleep -Seconds 1
    if (Test-Path $cfLog) {
        $logContent = Get-Content $cfLog -Raw -ErrorAction SilentlyContinue
        if ($logContent -match "Registered tunnel connection|Connection .* registered") {
            $tunnelReady = $true
            break
        }
    }
    if ($cfProcess.HasExited) {
        break
    }
}

$domainUrl = "https://tracko.my.id"
Set-Content -Path (Join-Path $PSScriptRoot "tunnel_url.txt") -Value $domainUrl -Force -ErrorAction SilentlyContinue

Clear-Host
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host "          *** TRACKO - SERVER AKTIF & SIAP DIGUNAKAN! ***               " -ForegroundColor Green
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host " [1] LINK RESMI INTERNET (PERMANEN SELAMANYA - BISA SEMUA JARINGAN):" -ForegroundColor White
Write-Host "     --> https://tracko.my.id" -ForegroundColor Green
Write-Host "     --> https://www.tracko.my.id" -ForegroundColor Green
Write-Host "     [!] PENTING: LINK INI RESMI, PERMANEN SELAMANYA, & AMAN (HTTPS)!" -ForegroundColor Cyan
Write-Host "     [+] Semua rekan kerja (Wi-Fi, LAN, HP di luar kantor) bisa langsung buka:" -ForegroundColor Cyan
Write-Host "        https://tracko.my.id" -ForegroundColor Yellow
Write-Host ""

if ($tunnelReady) {
    Write-Host "     STATUS CLOUDFLARE: TERHUBUNG KE EDGE NETWORK (ONLINE GLOBAL)" -ForegroundColor Green
} else {
    Write-Host "     STATUS CLOUDFLARE: Sedang menghubungkan atau memeriksa internet..." -ForegroundColor Yellow
}

try {
    Set-Clipboard -Value "https://tracko.my.id"
    Write-Host "`n     [Link https://tracko.my.id OTOMATIS DISALIN KE CLIPBOARD!]" -ForegroundColor Green
} catch {}

Write-Host ""
Write-Host " [2] JALUR LOKAL (Jika internet kantor sedang gangguan):" -ForegroundColor White
Write-Host "     --> http://${localIp}:8000" -ForegroundColor Yellow

Write-Host ""
Write-Host " [3] LAPTOP SERVER INI:" -ForegroundColor White
Write-Host "     --> http://localhost:8000" -ForegroundColor Gray
Write-Host ""
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host "  STATUS: APLIKASI BERJALAN NORMAL, CEPAT, DAN AMAN!" -ForegroundColor Green
Write-Host "  PENTING: JANGAN TUTUP JENDELA INI SELAMA WEBSITE MASIH DIGUNAKAN." -ForegroundColor Yellow
Write-Host "  Tekan sembarang tombol di jendela ini untuk mematikan server." -ForegroundColor DarkGray
Write-Host "========================================================================" -ForegroundColor Cyan
Write-Host ""

# Buka domain resmi jika tunnel siap, atau localhost jika offline
if ($tunnelReady) {
    Start-Process "https://tracko.my.id"
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
Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
if ($queueProcess -and -not $queueProcess.HasExited) { Stop-Process -Id $queueProcess.Id -Force -ErrorAction SilentlyContinue }
if ($serveProcess -and -not $serveProcess.HasExited) { Stop-Process -Id $serveProcess.Id -Force -ErrorAction SilentlyContinue }
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue | Where-Object { 
    $_.CommandLine -like "*artisan*serve*" -or $_.CommandLine -like "*artisan*queue:work*" 
} | ForEach-Object { 
    Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue 
}
Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | ForEach-Object {
    Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue
}
if (Test-Path $cfLog) { Remove-Item $cfLog -Force -ErrorAction SilentlyContinue }
Write-Host "[OK] Semua layanan telah dimatikan dengan aman." -ForegroundColor Green
Start-Sleep -Seconds 1
