#Requires -Version 5.1
<#
.SYNOPSIS
  One-shot PostgreSQL setup for FAMS on Windows (XAMPP + PostgreSQL).

.EXAMPLE
  .\setup-postgres.ps1
  .\setup-postgres.ps1 -PgPassword "secret"
  .\setup-postgres.ps1 -SkipEnv -ForceRecreateDb
#>
[CmdletBinding()]
param(
    [string]$ProjectRoot = $PSScriptRoot,
    [string]$PhpExe = "C:\xampp\php\php.exe",
    [string]$PgBin = "",
    [string]$PgHost = "127.0.0.1",
    [int]$PgPort = 5432,
    [string]$PgUser = "postgres",
    [string]$PgPassword = "",
    [string]$Database = "facilities_admin_db",
    [switch]$SkipEnv,
    [switch]$ForceRecreateDb,
    [switch]$SkipSchemaImport
)

$ErrorActionPreference = "Stop"

function Write-Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Find-PostgresBin {
    if ($PgBin -and (Test-Path (Join-Path $PgBin "psql.exe"))) {
        return $PgBin
    }

    $candidates = @(
        "C:\Program Files\PostgreSQL\17\bin",
        "C:\Program Files\PostgreSQL\16\bin",
        "C:\Program Files\PostgreSQL\15\bin",
        "C:\Program Files\PostgreSQL\14\bin"
    )

    foreach ($path in $candidates) {
        if (Test-Path (Join-Path $path "psql.exe")) {
            return $path
        }
    }

    $fromPath = Get-Command psql.exe -ErrorAction SilentlyContinue
    if ($fromPath) {
        return Split-Path $fromPath.Source -Parent
    }

    throw "PostgreSQL bin folder not found. Install PostgreSQL or pass -PgBin 'C:\Program Files\PostgreSQL\16\bin'"
}

function Invoke-Pg([string]$Bin, [string[]]$Args) {
    $env:PGPASSWORD = $PgPassword
    try {
        & (Join-Path $Bin "psql.exe") @Args
        if ($LASTEXITCODE -ne 0) {
            throw "psql failed (exit $LASTEXITCODE)"
        }
    } finally {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    }
}

function Test-DatabaseExists([string]$Bin) {
    $sql = "SELECT 1 FROM pg_database WHERE datname = '$Database';"
    $out = & (Join-Path $Bin "psql.exe") -h $PgHost -p $PgPort -U $PgUser -d postgres -tAc $sql 2>$null
    return ($out -match "^\s*1\s*$")
}

Write-Host "FAMS PostgreSQL setup" -ForegroundColor Green
Set-Location $ProjectRoot

Write-Step "Checking PHP"
if (-not (Test-Path $PhpExe)) {
    throw "PHP not found at $PhpExe. Pass -PhpExe with your XAMPP php.exe path."
}
& $PhpExe -v | Select-Object -First 1

Write-Step "Checking pdo_pgsql extension"
$modules = & $PhpExe -m
if ($modules -notcontains "pdo_pgsql") {
    Write-Warning @"
pdo_pgsql is not loaded. Enable in C:\xampp\php\php.ini:
  extension=pdo_pgsql
  extension=pgsql
Then restart Apache and re-run this script.
"@
} else {
    Write-Host "pdo_pgsql OK" -ForegroundColor Green
}

Write-Step "Locating PostgreSQL tools"
$PgBin = Find-PostgresBin
Write-Host "Using: $PgBin"

if (-not $PgPassword) {
    $secure = Read-Host "PostgreSQL password for user '$PgUser' (input hidden)" -AsSecureString
    $PgPassword = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    )
}

Write-Step "Ensuring database '$Database' exists"
$dbExists = Test-DatabaseExists $PgBin

if ($ForceRecreateDb -and $dbExists) {
    Write-Host "Dropping existing database..."
    Invoke-Pg $PgBin @("-h", $PgHost, "-p", "$PgPort", "-U", $PgUser, "-d", "postgres", "-c", "DROP DATABASE IF EXISTS `"$Database`";")
    $dbExists = $false
}

if (-not $dbExists) {
    Write-Host "Creating database..."
    Invoke-Pg $PgBin @("-h", $PgHost, "-p", "$PgPort", "-U", $PgUser, "-d", "postgres", "-c", "CREATE DATABASE `"$Database`";")
} else {
    Write-Host "Database already exists (use -ForceRecreateDb for clean reimport)."
}

if (-not $SkipSchemaImport) {
    Write-Step "Generating schema.postgres.sql"
    & $PhpExe (Join-Path $ProjectRoot "database\convert_schema_to_postgres.php")
    if ($LASTEXITCODE -ne 0) { throw "Schema conversion failed" }

    Write-Step "Importing schema"
    $schemaFile = Join-Path $ProjectRoot "database\schema.postgres.sql"
    if (-not (Test-Path $schemaFile)) {
        throw "Missing $schemaFile"
    }

    $env:PGPASSWORD = $PgPassword
    try {
        & (Join-Path $PgBin "psql.exe") -h $PgHost -p $PgPort -U $PgUser -d $Database -f $schemaFile
        if ($LASTEXITCODE -ne 0) { throw "Schema import failed (exit $LASTEXITCODE)" }
    } finally {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    }
    Write-Host "Schema imported." -ForegroundColor Green
}

if (-not $SkipEnv) {
    Write-Step "Configuring Laravel .env"
    $envFile = Join-Path $ProjectRoot ".env"
    $envExample = Join-Path $ProjectRoot ".env.example"

    if (-not (Test-Path $envFile)) {
        if (-not (Test-Path $envExample)) {
            throw ".env.example not found"
        }
        Copy-Item $envExample $envFile
        Write-Host "Created .env from .env.example"
    }

    $content = Get-Content $envFile -Raw
    $replacements = @{
        'DB_CONNECTION\s*=\s*\w+' = "DB_CONNECTION=pgsql"
        'DB_HOST\s*=\s*[^\r\n]+' = "DB_HOST=$PgHost"
        'DB_PORT\s*=\s*\d+' = "DB_PORT=$PgPort"
        'DB_DATABASE\s*=\s*[^\r\n]+' = "DB_DATABASE=$Database"
        'DB_USERNAME\s*=\s*[^\r\n]+' = "DB_USERNAME=$PgUser"
        'DB_PASSWORD\s*=\s*[^\r\n]*' = "DB_PASSWORD=$PgPassword"
        'SESSION_DRIVER\s*=\s*\w+' = "SESSION_DRIVER=database"
    }
    foreach ($pattern in $replacements.Keys) {
        $content = [regex]::Replace($content, $pattern, $replacements[$pattern])
    }
    [System.IO.File]::WriteAllText($envFile, $content.TrimEnd() + [Environment]::NewLine)

    & $PhpExe artisan key:generate --force
    & $PhpExe artisan config:clear

    Write-Host ".env updated and config cleared." -ForegroundColor Green
}

Write-Step "Testing database connection"
& $PhpExe artisan tinker --execute="echo DB::connection()->getDriverName() . PHP_EOL . DB::connection()->getDatabaseName();"

Write-Host @"

Setup complete.

App URL:  http://localhost/ani-fams/public
Login:    admin@facilitiesadmin.local
Password: Password123!

Run tests:  $PhpExe artisan test
"@ -ForegroundColor Green
