# PedestriRoute BDD Test Runner for Windows PowerShell (Sprint 2)
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$VenvBehave = Join-Path $ScriptDir ".venv\Scripts\behave.exe"

if (-not (Test-Path $VenvBehave)) {
    Write-Host "[INFO] Virtual environment not found. Creating..." -ForegroundColor Yellow
    python -m venv (Join-Path $ScriptDir ".venv")
    & (Join-Path $ScriptDir ".venv\Scripts\pip.exe") install -r (Join-Path $ScriptDir "requirements.txt")
}

Write-Host "===========================================================" -ForegroundColor Cyan
Write-Host "   PedestriRoute BDD Suite - Sprint 2                      " -ForegroundColor Cyan
Write-Host "   Features: F4 (Computer Vision), F6 (Heatmap), F9 (Perbaikan)" -ForegroundColor Cyan
Write-Host "===========================================================" -ForegroundColor Cyan

& $VenvBehave `
    (Join-Path $ScriptDir "features\f4_computer_vision.feature") `
    (Join-Path $ScriptDir "features\f6_heatmap.feature") `
    (Join-Path $ScriptDir "features\f9_status_perbaikan.feature") `
    @args
