@echo off
title Cashirak POS Server
echo ======================================
echo         CASHIRAK POS SYSTEM
echo ======================================
echo.

set PHP_PATH=%~dp0php\php.exe

if not exist "%PHP_PATH%" (
    echo [ERROR] PHP not found at: %PHP_PATH%
    echo Please download PHP from https://windows.php.net/download
    echo (Choose "Non Thread Safe" Zip) and extract it to the "php" folder.
    pause
    exit /b 1
)

set PORT=8000

echo Starting PHP server on port %PORT%...
echo.
echo Once started, open your browser and go to:
echo http://localhost:%PORT%/install.php   (first time only)
echo http://localhost:%PORT%/login.php
echo.
echo Press Ctrl+C in this window to stop the server.
echo ======================================

"%PHP_PATH%" -S localhost:%PORT% -t public

pause
