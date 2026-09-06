[CmdletBinding()]
param(
  [string]$Serial,
  [string]$ApkPath
)

$ErrorActionPreference = "Stop"
$adb = Join-Path $env:LOCALAPPDATA "Android\Sdk\platform-tools\adb.exe"
if (-not $ApkPath) {
  $ApkPath = Join-Path $PSScriptRoot "..\app\build\outputs\apk\debug\app-debug.apk"
}
$resolvedApk = [System.IO.Path]::GetFullPath($ApkPath)

if (-not (Test-Path -LiteralPath $adb)) {
  throw "No se encontro ADB en $adb."
}

if (-not (Test-Path -LiteralPath $resolvedApk)) {
  throw "No se encontro el APK. Ejecuta primero: .\gradlew.bat assembleDebug"
}

& $adb start-server | Out-Null
$deviceLines = & $adb devices -l

if ($Serial) {
  $selectedDevice = $deviceLines |
    Where-Object { $_ -match "^$([regex]::Escape($Serial))\s+device\b" } |
    Select-Object -First 1
} else {
  $physicalDevices = $deviceLines |
    Where-Object { $_ -match "^(\S+)\s+device\b" -and $_ -notmatch "^emulator-" }

  if (@($physicalDevices).Count -gt 1) {
    throw "Hay varios telefonos conectados. Ejecuta el script con -Serial <numero>."
  }

  $selectedDevice = $physicalDevices | Select-Object -First 1
  if ($selectedDevice -match "^(\S+)") {
    $Serial = $Matches[1]
  }
}

if (-not $selectedDevice) {
  $unauthorized = $deviceLines | Where-Object { $_ -match "\s+unauthorized\b" }
  if ($unauthorized) {
    throw "El telefono esta conectado, pero falta aceptar 'Permitir depuracion USB' en su pantalla."
  }

  throw "No se detecto un telefono fisico. Activa la depuracion USB, conectalo y vuelve a ejecutar el script."
}

Write-Host "Instalando SafeKids en $Serial..."
& $adb -s $Serial install -r $resolvedApk
if ($LASTEXITCODE -ne 0) {
  throw "ADB no pudo instalar el APK."
}

& $adb -s $Serial shell am start -n com.safekids.mobile/.MainActivity | Out-Null
Write-Host "SafeKids se instalo y se abrio correctamente."
