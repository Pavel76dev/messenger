# Копирует faster-whisper-small из HF-кэша (общий с C:\OpenServer\video) в models/
$ErrorActionPreference = 'Stop'
# PSScriptRoot = .../services/asr/scripts → asr root:
$AsrRoot = Split-Path $PSScriptRoot -Parent
$Hub = Join-Path $env:USERPROFILE '.cache\huggingface\hub\models--Systran--faster-whisper-small\snapshots'
$Dst = Join-Path $AsrRoot 'models\faster-whisper-small'

if (-not (Test-Path $Hub)) {
    Write-Error "Кэш Whisper small не найден: $Hub`nСначала один раз скачайте модель в video или с машины без SSL-блокировки."
}

$snap = Get-ChildItem $Hub -Directory | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $snap) {
    Write-Error "Нет snapshot в $Hub"
}

$bin = Join-Path $snap.FullName 'model.bin'
if (-not (Test-Path $bin)) {
    Write-Error "Нет model.bin в $($snap.FullName)"
}

New-Item -ItemType Directory -Force -Path $Dst | Out-Null
Write-Host "Копирую $($snap.FullName) -> $Dst"
robocopy $snap.FullName $Dst /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) {
    Write-Error "robocopy failed: $LASTEXITCODE"
}

Get-ChildItem $Dst | Format-Table Name, Length
Write-Host "Готово. В .env: WHISPER_MODEL=models/faster-whisper-small"
