#Requires -Version 5.1
<#
.SYNOPSIS
  Fix Composer SSL certificate errors on Windows/XAMPP (curl error 60).

.EXAMPLE
  .\scripts\fix-composer-ssl.ps1
  .\scripts\fix-composer-ssl.ps1 -PhpExe "C:\xampp1\php\php.exe"
#>
[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path $PSScriptRoot -Parent),
    [string]$PhpExe = "",
    [string]$CaBundlePath = ""
)

$ErrorActionPreference = "Stop"

function Write-Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Find-XamppPhp {
    $candidates = @(
        "C:\xampp1\php\php.exe",
        "C:\xampp\php\php.exe",
        "D:\xampp\php\php.exe",
        "D:\xampp1\php\php.exe"
    )

    foreach ($path in $candidates) {
        if (Test-Path $path) {
            return $path
        }
    }

    $fromPath = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($fromPath) {
        return $fromPath.Source
    }

    return $null
}

function Enable-PhpExtension([string]$IniPath, [string]$ExtensionName) {
    $iniContent = Get-Content $IniPath -Raw
    $pattern = '(?m)^;?\s*extension\s*=\s*' + [regex]::Escape($ExtensionName) + '\s*$'

    if ($iniContent -match $pattern) {
        $iniContent = [regex]::Replace($iniContent, $pattern, "extension=$ExtensionName")
    } else {
        $iniContent += "`nextension=$ExtensionName`n"
    }

    [System.IO.File]::WriteAllText($IniPath, $iniContent)
}

if (-not $PhpExe) {
    $PhpExe = Find-XamppPhp
}

if (-not $PhpExe -or -not (Test-Path $PhpExe)) {
    throw @"
PHP not found. Pass your XAMPP php.exe path explicitly, e.g.:
  .\scripts\fix-composer-ssl.ps1 -PhpExe "C:\xampp1\php\php.exe"
"@
}

$phpRoot = Split-Path (Split-Path $PhpExe -Parent) -Parent
if (-not $CaBundlePath) {
    $CaBundlePath = Join-Path $phpRoot "php\extras\ssl\cacert.pem"
}

Write-Host "Using PHP: $PhpExe" -ForegroundColor Green
Write-Host "Using CA bundle: $CaBundlePath" -ForegroundColor Green

Write-Step "Downloading latest CA certificate bundle"
$sslDir = Split-Path $CaBundlePath -Parent
if (-not (Test-Path $sslDir)) {
    New-Item -ItemType Directory -Path $sslDir -Force | Out-Null
}

try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -Uri "https://curl.se/ca/cacert.pem" -OutFile $CaBundlePath -UseBasicParsing
    Write-Host "Saved: $CaBundlePath" -ForegroundColor Green
} catch {
    throw "Could not download cacert.pem. Check internet or temporarily disable Avast HTTPS scanning.`n$($_.Exception.Message)"
}

if (-not (Test-Path $CaBundlePath)) {
    throw "CA bundle missing at $CaBundlePath"
}

Write-Step "Enabling PHP extensions required for PDF export (dom, mbstring)"
$phpIni = & $PhpExe -r "echo php_ini_loaded_file();"
if (-not $phpIni -or -not (Test-Path $phpIni)) {
    Write-Warning "Could not find php.ini automatically."
} else {
    foreach ($ext in @("dom", "mbstring")) {
        Enable-PhpExtension $phpIni $ext
        Write-Host "Enabled extension=$ext in $phpIni" -ForegroundColor Green
    }

    $caPathIni = $CaBundlePath -replace '\\', '/'
    $iniContent = Get-Content $phpIni -Raw

    $iniContent = $iniContent -replace '(?m)^;?\s*curl\.cainfo\s*=.*$', "curl.cainfo = `"$caPathIni`""
    if ($iniContent -notmatch '(?m)^curl\.cainfo\s*=') {
        $iniContent += "`ncurl.cainfo = `"$caPathIni`"`n"
    }

    $iniContent = $iniContent -replace '(?m)^;?\s*openssl\.cafile\s*=.*$', "openssl.cafile = `"$caPathIni`""
    if ($iniContent -notmatch '(?m)^openssl\.cafile\s*=') {
        $iniContent += "`nopenssl.cafile = `"$caPathIni`"`n"
    }

    [System.IO.File]::WriteAllText($phpIni, $iniContent)
    Write-Host "Updated SSL settings in: $phpIni" -ForegroundColor Green
}

Write-Step "Configuring Composer to use CA bundle"
$composerPhar = Join-Path $ProjectRoot "composer.phar"
if (Test-Path $composerPhar) {
    & $PhpExe $composerPhar config -g --unset disable-tls 2>$null
    & $PhpExe $composerPhar config -g cafile $CaBundlePath
} elseif (Get-Command composer -ErrorAction SilentlyContinue) {
    composer config -g --unset disable-tls 2>$null
    composer config -g cafile $CaBundlePath
} else {
    throw "Composer not found. Place composer.phar in the project root."
}

Write-Step "Running composer install"
Set-Location $ProjectRoot
if (Test-Path $composerPhar) {
    & $PhpExe $composerPhar install --no-interaction
} else {
    composer install --no-interaction
}

if ($LASTEXITCODE -ne 0) {
    Write-Host @"

composer install still failed. Also try:
  1. Restart Apache in XAMPP
  2. Temporarily disable Avast HTTPS scanning
  3. Re-run with your exact PHP path:
     .\scripts\fix-composer-ssl.ps1 -PhpExe "$PhpExe"
"@ -ForegroundColor Red
    exit 1
}

Write-Host @"

Setup complete.
1. Restart Apache in XAMPP
2. Open the app and try Export PDF again

"@ -ForegroundColor Green
