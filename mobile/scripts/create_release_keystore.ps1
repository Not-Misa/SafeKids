param(
  [string]$Alias = "safekids",
  [string]$OutputPath = "release/safekids-release.jks"
)

$ErrorActionPreference = "Stop"

$keytool = Join-Path $env:JAVA_HOME "bin/keytool.exe"
if (-not (Test-Path -LiteralPath $keytool)) {
  $keytool = "keytool.exe"
}

$outputFile = Join-Path (Resolve-Path (Join-Path $PSScriptRoot "..")).Path $OutputPath
$outputDirectory = Split-Path -Parent $outputFile
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null

if (Test-Path -LiteralPath $outputFile) {
  throw "Ya existe un keystore en $outputFile. No se reemplazo."
}

& $keytool `
  -genkeypair `
  -v `
  -keystore $outputFile `
  -alias $Alias `
  -keyalg RSA `
  -keysize 4096 `
  -validity 10000

Write-Output "Keystore creado en $outputFile"
Write-Output "Cree keystore.properties a partir de keystore.properties.example."
