@echo off
REM Import a clean PostgreSQL schema for FAMS (Windows).
REM Edit PSQL, DB, and PGUSER below if your install differs.

set PSQL=C:\Program Files\PostgreSQL\16\bin\psql.exe
set DB=facilities_admin_db
set PGUSER=postgres
set PGHOST=127.0.0.1
set PGPORT=5432

if not exist "%PSQL%" (
    echo psql not found at %PSQL%.
    echo Install PostgreSQL or edit PSQL path in this script.
    exit /b 1
)

cd /d "%~dp0.."

echo Regenerating database\schema.postgres.sql ...
"C:\xampp\php\php.exe" database\convert_schema_to_postgres.php
if errorlevel 1 exit /b 1

echo.
echo Importing into database "%DB%" ...
set PGPASSWORD=
"%PSQL%" -h %PGHOST% -p %PGPORT% -U %PGUSER% -d %DB% -f database\schema.postgres.sql
if errorlevel 1 (
    echo Import failed. Create the database first, e.g.:
    echo   createdb -U %PGUSER% %DB%
    exit /b 1
)

echo Done.
pause
