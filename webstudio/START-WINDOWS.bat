@echo off
setlocal
title Web Studio website
rem One-click start for Windows 10/11 with XAMPP installed.
rem Copies this website into XAMPP (as htdocs\webstudio), starts Apache + MySQL and opens it.
rem Run it again any time to start the site, or to install an updated version.

echo ==================================================
echo    Starting your website
echo ==================================================
echo.

set "SRC=%~dp0"
if "%SRC:~-1%"=="\" set "SRC=%SRC:~0,-1%"
if not exist "%SRC%\index.php" (
  echo This file must stay inside the website folder, next to index.php.
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
  echo Install XAMPP from https://www.apachefriends.org and run this file again.
  goto fail
)
echo [1/4] Found XAMPP in %XAMPP%

rem ---- Copy the website into htdocs (keeps your config.local.php and uploaded pictures) ----
set "TARGET=%XAMPP%\htdocs\webstudio"
if /I "%SRC%"=="%TARGET%" (
  echo [2/4] The website is already in %TARGET%
) else (
  robocopy "%SRC%" "%TARGET%" /E /XF config.local.php /XD uploads storage /NFL /NDL /NJH /NJS /NP >nul
  if errorlevel 8 (
    echo Could not copy the website to %TARGET%
    echo Try right-clicking this file and choosing "Run as administrator".
    goto fail
  )
  if not exist "%TARGET%\uploads" robocopy "%SRC%\uploads" "%TARGET%\uploads" /E /NFL /NDL /NJH /NJS /NP >nul
  if not exist "%TARGET%\storage" robocopy "%SRC%\storage" "%TARGET%\storage" /E /NFL /NDL /NJH /NJS /NP >nul
  echo [2/4] Website installed in %TARGET%
)

rem ---- Start Apache and MySQL if they aren't running ----
tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul || start "Apache - keep this window open" /MIN "%XAMPP%\apache_start.bat"
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul || start "MySQL - keep this window open" /MIN "%XAMPP%\mysql_start.bat"
echo [3/4] Starting Apache and MySQL...

rem ---- Wait until the site answers (up to 30 seconds) ----
set /a TRIES=0
:wait
curl -s -o nul http://localhost/webstudio/ && goto ready
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
start "" "http://localhost/webstudio/"
echo [4/4] Opened http://localhost/webstudio/
echo.
echo --------------------------------------------------
echo  Your website:     http://localhost/webstudio/
echo  Admin dashboard:  http://localhost/webstudio/admin/
echo.
echo  The first time you open the dashboard, it asks you
echo  to create your owner account.
echo.
echo  To stop the website: close the minimized "Apache"
echo  and "MySQL" windows, or press Stop in XAMPP.
echo --------------------------------------------------
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1
