# PedestriRoute BDD Test Runner for Windows PowerShell (Sprint 1)
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$VenvBehave = Join-Path $ScriptDir ".venv\Scripts\behave.exe"

if (-not (Test-Path $VenvBehave)) {
    Write-Host "[INFO] Virtual environment not found. Creating..." -ForegroundColor Yellow
    python -m venv (Join-Path $ScriptDir ".venv")
    & (Join-Path $ScriptDir ".venv\Scripts\pip.exe") install -r (Join-Path $ScriptDir "requirements.txt")
}

Write-Host "===========================================================" -ForegroundColor Cyan
Write-Host "   PedestriRoute BDD Suite - Sprint 1 (Weeks 1 & 2)       " -ForegroundColor Cyan
Write-Host "   Features: F1 (Login), F2 (Register), F3 (Lapor), F5 (Admin)" -ForegroundColor Cyan
Write-Host "===========================================================" -ForegroundColor Cyan

& $VenvBehave (Join-Path $ScriptDir "features") @args
