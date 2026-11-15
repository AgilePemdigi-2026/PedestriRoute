# PedestriRoute Startup Script
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "             PedestriRoute - Setup & Launcher             " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Check if Docker is installed and in PATH (or add standard install paths if present)
$dockerPaths = @(
    "$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin",
    "$env:ProgramFiles\Docker\Docker\resources\bin"
)
foreach ($p in $dockerPaths) {
    if ((Test-Path $p) -and ($env:Path -notlike "*$p*")) {
        $env:Path = "$p;$env:Path"
    }
}

try {
    $dockerVersion = docker --version 2>$null
    if (-not $dockerVersion) {
        throw "Docker command not found."
    }
    Write-Host "[OK] Docker CLI detected: $dockerVersion" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Docker is not installed yet." -ForegroundColor Red
    Write-Host "Please run the downloaded installer to complete installation:" -ForegroundColor Yellow
    Write-Host "  $env:USERPROFILE\Downloads\DockerDesktopInstaller.exe" -ForegroundColor Yellow
    Exit 1
}

# Check Docker daemon
Write-Host "[INFO] Checking Docker daemon status..." -ForegroundColor Gray
$prevEAP = $ErrorActionPreference
$ErrorActionPreference = "SilentlyContinue"

cmd.exe /c "docker info >nul 2>&1"
$isDockerRunning = ($LASTEXITCODE -eq 0)

if (-not $isDockerRunning) {
    Write-Host "[INFO] Docker Desktop is not currently running. Starting it now..." -ForegroundColor Yellow
    $dockerDesktopExes = @(
        "$env:LOCALAPPDATA\Programs\DockerDesktop\Docker Desktop.exe",
        "$env:ProgramFiles\Docker\Docker\Docker Desktop.exe"
    )
    foreach ($exe in $dockerDesktopExes) {
        if (Test-Path $exe) {
            Start-Process -FilePath $exe
            break
        }
    }
    
    Write-Host "[INFO] Waiting for Docker engine to become ready (this may take 30-60 seconds on first launch)..." -ForegroundColor Yellow
    $retries = 40
    while ($retries -gt 0) {
        Start-Sleep -Seconds 3
        cmd.exe /c "docker info >nul 2>&1"
        if ($LASTEXITCODE -eq 0) {
            $isDockerRunning = $true
            break
        }
        Write-Host "." -NoNewline
        $retries--
    }
    Write-Host ""
}

$ErrorActionPreference = $prevEAP

if (-not $isDockerRunning) {
    Write-Host "[ERROR] Could not connect to Docker Engine." -ForegroundColor Red
    Write-Host "Please make sure Docker Desktop is open on your screen, accept any initial prompts if shown, and wait until the bottom-left status turns green ('Engine running')." -ForegroundColor Yellow
    Exit 1
}
Write-Host "[OK] Docker daemon is running." -ForegroundColor Green

# 2. Build & Launch Containers
Write-Host "`n[STEP 1/4] Building and launching containers via Docker Compose..." -ForegroundColor Cyan
docker compose -f infra/docker-compose.yml up -d --build

# 3. Wait for database container to be healthy
Write-Host "`n[STEP 2/4] Waiting for PostGIS database to be ready..." -ForegroundColor Cyan
$retries = 30
while ($retries -gt 0) {
    $health = (& docker inspect --format='{{json .State.Health.Status}}' pedestriroute_db 2>&1 | Out-String)
    if ($health -match "healthy") {
        Write-Host "`n[OK] Database is healthy and ready!" -ForegroundColor Green
        break
    }
    Start-Sleep -Seconds 2
    $retries--
    Write-Host "." -NoNewline
}

# 4. Check / install Composer vendor in app container
Write-Host "`n[STEP 3/4] Ensuring Composer dependencies and permissions..." -ForegroundColor Cyan
docker exec pedestriroute_app composer install --no-interaction --prefer-dist --optimize-autoloader
docker exec pedestriroute_app chmod -R 777 /var/www/backend/storage /var/www/backend/bootstrap/cache

# 5. Database migration & initial seed
Write-Host "`n[STEP 4/4] Running database migrations and seeders..." -ForegroundColor Cyan
docker exec pedestriroute_app php artisan migrate --force
docker exec pedestriroute_app php artisan db:seed --force

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host " PedestriRoute is up and running successfully!           " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "Web Application: http://localhost:8000" -ForegroundColor White
Write-Host "CV Microservice: http://localhost:8001/docs" -ForegroundColor White
Write-Host "Admin Validasi:  http://localhost:8000/admin/validasi" -ForegroundColor White
Write-Host "Beranda Warga:   http://localhost:8000/beranda-warga" -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Green
