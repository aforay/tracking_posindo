Start-Sleep -Seconds 2
Write-Host "--- Test 1: Local HTTP 8000 ---"
try {
    $r1 = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/login' -UseBasicParsing -TimeoutSec 10
    Write-Host "Local /login Status: $($r1.StatusCode)"
} catch {
    Write-Host "Local /login Failed: $($_.Exception.Message)"
}

Write-Host "--- Test 2: Cloudflare Domain https://tracko.my.id/login ---"
try {
    $r2 = Invoke-WebRequest -Uri 'https://tracko.my.id/login' -UseBasicParsing -TimeoutSec 15
    Write-Host "Domain tracko.my.id Status: $($r2.StatusCode)"
} catch {
    Write-Host "Domain tracko.my.id Failed: $($_.Exception.Message)"
}

Write-Host "--- Test 3: Authenticated Inertia Routes in PHP ---"
& php scratch/test_routes_live.php
