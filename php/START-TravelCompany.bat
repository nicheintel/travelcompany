@echo off
title TravelCompany
setlocal
rem Double-click to start XAMPP's Apache and MySQL (if needed) and open the website.
rem This folder must be somewhere inside XAMPP's htdocs, e.g. C:\xampp\htdocs\travelcompany

set "SITE=%~dp0"

rem Find the part of this folder's path after "\htdocs\" -> that's the web address.
set "REL=%SITE:*\htdocs\=%"
if /I "%REL%"=="%SITE%" (
  echo This folder is not inside XAMPP's htdocs folder:
  echo   %SITE%
  echo.
  echo Move the "travelcompany" folder into C:\xampp\htdocs and double-click this file again.
  pause
  exit /b 1
)
call set "XAMPP=%%SITE:\htdocs\%REL%=%%"
if not exist "%XAMPP%\apache_start.bat" set "XAMPP=C:\xampp"
if not exist "%XAMPP%\apache_start.bat" (
  echo Could not find XAMPP. Install it from https://www.apachefriends.org
  pause
  exit /b 1
)
set "URLPATH=%REL:\=/%"

if not exist "%SITE%index.php" (
  echo index.php is missing in %SITE% - please extract the zip again.
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

start "" "http://localhost/%URLPATH%"

echo.
echo TravelCompany is running at http://localhost/%URLPATH%
echo.
echo To stop it: close the minimized "Apache" and "MySQL" windows,
echo or use Stop in the XAMPP Control Panel.
echo.
pause
