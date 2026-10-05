@echo off
setlocal
title LamazonLoads - stop
rem Stops XAMPP's Apache and MySQL. Your website files and data are kept.

set "XAMPP="
for %%D in ("C:\xampp" "D:\xampp" "E:\xampp" "%ProgramFiles%\xampp" "%USERPROFILE%\xampp") do (
  if not defined XAMPP if exist "%%~D\apache_stop.bat" set "XAMPP=%%~D"
)
if not defined XAMPP (
  echo Could not find XAMPP. Use the Stop buttons in the XAMPP Control Panel instead.
  pause
  exit /b 1
)
echo Stopping Apache and MySQL...
call "%XAMPP%\apache_stop.bat" >nul 2>&1
call "%XAMPP%\mysql_stop.bat" >nul 2>&1
echo Done. Run START-LamazonLoads.bat to start the website again.
pause
