$ErrorActionPreference = "Stop"

$exePath = Join-Path $PSScriptRoot "bin\Release\net9.0-windows\MaXSync.exe"

if (-not (Test-Path $exePath)) {
    Write-Error "Nu gasesc executabilul: $exePath. Compileaza intai cu: dotnet publish -c Release"
    exit 1
}

if (Get-Service -Name "MaXSync" -ErrorAction SilentlyContinue) {
    Write-Host "Serviciul MaXSync exista deja. Il opresc si il sterg..."
    Stop-Service -Name "MaXSync" -ErrorAction SilentlyContinue
    Remove-Service -Name "MaXSync"
    Start-Sleep -Seconds 2
}

New-Service -Name "MaXSync" `
    -DisplayName "MaXPos Saga Sync Agent" `
    -Description "Sincronizeaza date intre Saga si MaXPos" `
    -BinaryPathName $exePath `
    -StartupType Automatic | Out-Null

Start-Service -Name "MaXSync"
Write-Host "MaXSync instalat si pornit cu succes."
