-- Reminder: for a brand-new install, import database/schema.sql only.
-- This batch is for upgrading an EXISTING older database after a git pull.

@echo off
REM Apply incremental DB patches after pulling the repo (XAMPP on Windows).
REM Usage: double-click or run from repo root: database\patches\apply_patches.bat

set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DB=facilities_admin_db

if not exist "%MYSQL%" (
    echo MySQL not found at %MYSQL%. Edit MYSQL path in this script.
    exit /b 1
)

echo Applying Laravel cache tables...
"%MYSQL%" -u root %DB% --force < "%~dp0add_laravel_cache_tables.sql"

echo Applying enhancement tables (legal, contract, retention)...
"%MYSQL%" -u root %DB% --force < "%~dp0apply_missing_enhancement_tables.sql"

echo Applying reservation / visitor / document enhancements...
"%MYSQL%" -u root %DB% --force < "%~dp0add_reservation_enhancements.sql"
"%MYSQL%" -u root %DB% --force < "%~dp0add_visitor_enhancements.sql"
"%MYSQL%" -u root %DB% --force < "%~dp0add_document_enhancements.sql"
"%MYSQL%" -u root %DB% --force < "%~dp0add_retention_enhancements.sql"

echo Applying ANI facilities content refresh...
"%MYSQL%" -u root %DB% --force < "%~dp0ani_facilities_content.sql"

echo Done. Prefer a clean reimport of database\schema.sql for new machines.
pause
