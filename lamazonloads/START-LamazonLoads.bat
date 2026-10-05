@echo off
setlocal
title LamazonLoads
rem One-click local test for Windows 10/11 with XAMPP installed.
rem Copies the website into XAMPP, starts Apache + MySQL and opens it in your browser.
rem Run it again any time to start the site (and to install an updated version).

echo ==================================================
echo    LamazonLoads - Why wait? Let's freight.
echo    Starting your website...
echo ==================================================
echo.

set "SRC=%~dp0site"
if not exist "%SRC%\index.php" (
  echo The "site" folder must be next to this file.
  echo Right-click the zip file, choose "Extract All", then
  echo double-click this file inside the extracted "lamazonloads" folder.
  goto fail
)

rem ---- Find XAMPP ----
set "XAMPP="
for %%D in ("C:\xampp" "D:\xampp" "E:\xampp" "%ProgramFiles%\xampp" "%USERPROFILE%\xampp") do (
  if not defined XAMPP if exist "%%~D\apache_start.bat" set "XAMPP=%%~D"
)
if not defined XAMPP (
  echo Could not find XAMPP automatically.
  set /p "XAMPP=Type the folder where XAMPP is installed, for example C:\xampp, then press Enter: "
)
if not exist "%XAMPP%\apache_start.bat" (
  echo XAMPP was not found in "%XAMPP%".
  echo Install XAMPP ^(PHP 8.1 or newer^) from https://www.apachefriends.org and run this file again.
  goto fail
)
echo [1/4] Found XAMPP in %XAMPP%

rem ---- Copy the website into htdocs (keeps your config.local.php, uploads and settings) ----
set "TARGET=%XAMPP%\htdocs\lamazonloads"
robocopy "%SRC%" "%TARGET%" /E /XF config.local.php /NFL /NDL /NJH /NJS /NP >nul
if errorlevel 8 (
  echo Could not copy the website to %TARGET%
  echo Try right-clicking this file and choosing "Run as administrator".
  goto fail
)
if not exist "%TARGET%\config.local.php" copy "%TARGET%\config.local.example.php" "%TARGET%\config.local.php" >nul
echo [2/4] Website installed in %TARGET%

rem ---- Start Apache and MySQL if they aren't running ----
tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul || start "Apache - keep this window open" /MIN "%XAMPP%\apache_start.bat"
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul || start "MySQL - keep this window open" /MIN "%XAMPP%\mysql_start.bat"
echo [3/4] Starting Apache and MySQL...

rem ---- Wait until the site answers (up to 30 seconds) ----
set /a TRIES=0
:wait
curl -s -o nul http://localhost/lamazonloads/ && goto ready
set /a TRIES+=1
if %TRIES% geq 30 goto slow
timeout /t 1 /nobreak >nul
goto wait

:slow
echo.
echo The website is taking long to start. Open the XAMPP Control Panel and check
echo that Apache and MySQL are green. If Apache won't start, another program
echo such as Skype or IIS may be using port 80 - close it and run this file again.
echo.

:ready
start "" "http://localhost/lamazonloads/"
echo [4/4] Opened http://localhost/lamazonloads/
echo.
echo --------------------------------------------------
echo  Your website:  http://localhost/lamazonloads/
echo.
echo  The FIRST account you create becomes the admin.
echo  Admin dashboard: http://localhost/lamazonloads/admin/
echo.
echo  Contact email, phone and admin emails - open in Notepad:
echo    %TARGET%\config.local.php
echo.
echo  To stop the website: double-click STOP-LamazonLoads.bat,
echo  or press Stop in the XAMPP Control Panel.
echo --------------------------------------------------
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1
