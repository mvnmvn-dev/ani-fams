@echo off
REM Bypass PowerShell execution policy and fix Composer SSL (curl error 60),
REM then run composer install so Dompdf can generate PDFs.
REM Double-click this file, or run from cmd:
REM   tools\fix-composer-ssl.bat

setlocal
cd /d "%~dp0.."

set PHP_EXE=
if exist "C:\xampp1\php\php.exe" set PHP_EXE=C:\xampp1\php\php.exe
if exist "C:\xampp\php\php.exe" if "%PHP_EXE%"=="" set PHP_EXE=C:\xampp\php\php.exe

echo.
echo Project: %CD%
if not "%PHP_EXE%"=="" echo PHP:     %PHP_EXE%
echo.

if "%PHP_EXE%"=="" (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-composer-ssl.ps1"
) else (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-composer-ssl.ps1" -PhpExe "%PHP_EXE%"
)

echo.
if errorlevel 1 (
  echo FAILED. If Avast is installed: Settings - General - Core Shields - turn off HTTPS Scanning temporarily, then run this again.
) else (
  echo SUCCESS. Restart Apache in XAMPP, then try Export PDF again.
)
pause
