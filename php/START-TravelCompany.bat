@echo off
title TravelCompany
setlocal
rem Double-click to start XAMPP's Apache and MySQL (if needed) and open the website.
rem This folder must be inside XAMPP's htdocs, e.g. C:\xampp\htdocs\travelcompany

set "SITE=%~dp0"
for %%I in ("%SITE%..\..") do set "XAMPP=%%~fI"
if not exist "%XAMPP%\apache_start.bat" set "XAMPP=C:\xampp"
if not exist "%XAMPP%\apache_start.bat" (
  echo Could not find XAMPP.
  echo 1. Install XAMPP from https://www.apachefriends.org
  echo 2. Put this folder in C:\xampp\htdocs\travelcompany
  echo 3. Double-click this file again.
  pause
  exit /b 1
)

if not exist "%SITE%config.local.php" (
  copy "%SITE%config.local.example.php" "%SITE%config.local.php" >nul
  echo Created config.local.php - open it in Notepad to add your API keys and admin email.
)

tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul || (
  echo Starting Apache...
  start "Apache - keep this window open" /MIN "%XAMPP%\apache_start.bat"
)
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul || (
  echo Starting MySQL...
  start "MySQL - keep this window open" /MIN "%XAMPP%\mysql_start.bat"
)

echo Waiting for the servers to start...
timeout /t 6 /nobreak >nul

for %%I in ("%SITE%.") do set "FOLDER=%%~nxI"
start "" "http://localhost/%FOLDER%/"

echo.
echo TravelCompany is running at http://localhost/%FOLDER%/
echo.
echo To stop it: close the minimized "Apache" and "MySQL" windows,
echo or use Stop in the XAMPP Control Panel.
echo.
pause
