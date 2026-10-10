$ErrorActionPreference = 'Stop'

function Require-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command '$Name' was not found. Install the PostgreSQL command-line tools (psql and pg_dump), reopen PowerShell, and run this script again."
    }
}

try {
    Require-Command 'psql'
    Require-Command 'pg_dump'

    $migrationPath = Join-Path $PSScriptRoot 'add_revision_2026_10_updates.postgres.sql'
    if (-not (Test-Path -LiteralPath $migrationPath -PathType Leaf)) {
        throw "Migration file not found: $migrationPath"
    }

    Write-Host 'Connect this runner to the Render database using its EXTERNAL connection details.' -ForegroundColor Cyan
    Write-Host 'Find them from the database page in Render: Connect > External connection.'
    Write-Host 'Do not use the internal URL from your local PC. Your password will be prompted by PostgreSQL and is not saved by this script.'
    Write-Host ''

    $dbHost = (Read-Host 'Render external host').Trim()
    $portInput = (Read-Host 'Port [5432]').Trim()
    $dbPort = 5432
    if ($portInput -and -not [int]::TryParse($portInput, [ref]$dbPort)) {
        throw 'Port must be a number.'
    }
    $dbName = (Read-Host 'Database name').Trim()
    $dbUser = (Read-Host 'Database username').Trim()

    if (-not $dbHost -or -not $dbName -or -not $dbUser) {
        throw 'Host, database name, and username are required.'
    }
    if ($dbPort -lt 1 -or $dbPort -gt 65535) {
        throw 'Port must be between 1 and 65535.'
    }

    # libpq settings for both pg_dump and psql. External Render connections require TLS.
    $env:PGHOST = $dbHost
    $env:PGPORT = [string]$dbPort
    $env:PGDATABASE = $dbName
    $env:PGUSER = $dbUser
    $env:PGSSLMODE = 'require'

    $desktop = [Environment]::GetFolderPath('Desktop')
    if ([string]::IsNullOrWhiteSpace($desktop)) {
        $desktop = $HOME
    }
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $defaultBackupPath = Join-Path $desktop "ani-fams-before-revision-$timestamp.sql"
    $backupInput = (Read-Host "Backup file path [$defaultBackupPath]").Trim()
    $backupPath = if ($backupInput) { $backupInput } else { $defaultBackupPath }

    Write-Host ''
    Write-Host 'Target database:' -ForegroundColor Yellow
    Write-Host "  Host:     $dbHost"
    Write-Host "  Port:     $dbPort"
    Write-Host "  Database: $dbName"
    Write-Host "  Username: $dbUser"
    Write-Host "Backup:     $backupPath"
    Write-Host "Migration:  $migrationPath"
    Write-Host ''
    $confirmation = (Read-Host 'Type APPLY to create the backup and apply the migration').Trim()
    if ($confirmation -cne 'APPLY') {
        Write-Host 'Cancelled. No database changes were made.' -ForegroundColor Yellow
        exit 0
    }

    Write-Host ''
    Write-Host 'Creating a plain SQL backup. Enter the Render database password when prompted.' -ForegroundColor Cyan
    & pg_dump -W --no-owner --schema=public --file=$backupPath
    if ($LASTEXITCODE -ne 0) {
        throw "pg_dump failed with exit code $LASTEXITCODE. Migration was not started."
    }
    if (-not (Test-Path -LiteralPath $backupPath -PathType Leaf) -or (Get-Item -LiteralPath $backupPath).Length -eq 0) {
        throw 'The backup file is missing or empty. Migration was not started.'
    }
    Write-Host "Backup saved: $backupPath" -ForegroundColor Green

    Write-Host ''
    Write-Host 'Applying the additive migration. Enter the Render database password when prompted.' -ForegroundColor Cyan
    & psql -W --single-transaction -v ON_ERROR_STOP=1 -f $migrationPath
    if ($LASTEXITCODE -ne 0) {
        throw "Migration failed with exit code $LASTEXITCODE. PostgreSQL rolled back the migration transaction; keep the backup for recovery."
    }

    Write-Host ''
    Write-Host 'Migration completed successfully.' -ForegroundColor Green
    Write-Host "Keep the backup file until you have confirmed the deployed app works: $backupPath"
}
catch {
    Write-Error $_
    exit 1
}
finally {
    Remove-Item Env:PGHOST, Env:PGPORT, Env:PGDATABASE, Env:PGUSER, Env:PGSSLMODE -ErrorAction SilentlyContinue
}
