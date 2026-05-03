$ErrorActionPreference = "SilentlyContinue"

Stop-Service -Name "MaXSync"
Remove-Service -Name "MaXSync"
Write-Host "MaXSync dezinstalat."
