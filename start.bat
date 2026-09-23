@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Cashirak POS Server

REM ==========================================
REM Cashirak POS - Local PHP Server
REM Developer: Elmahdi Dev
REM Website: https://almhdy24.com
REM ==========================================

set "APP_DIR=%~dp0"
set "PHP_EXE=%APP_DIR%php\php.exe"
set "PHP_INI=%APP_DIR%php\php.ini"
set "PUBLIC_DIR=%APP_DIR%public"
set "HOST=127.0.0.1"
set "PORT=8000"
set "MAX_PORT=8100"

cls

echo.
echo ==========================================
echo         Cashirak POS v1.0.0
echo ==========================================
echo.

REM ------------------------------------------
REM Resolve DATA_PATH
REM Installed: reads from data_path.ini placed by installer
REM Dev mode:  falls back to APP_DIR (next to start.bat)
REM ------------------------------------------

set "DATA_PATH=%APP_DIR%"
if exist "%APP_DIR%data_path.ini" (
    for /f "usebackq tokens=2 delims==" %%A in (`findstr /i "DATA_PATH" "%APP_DIR%data_path.ini"`) do (
        set "DATA_PATH=%%A"
    )
)

REM Remove trailing backslash (if any)
if "!DATA_PATH:~-1!"=="\" set "DATA_PATH=!DATA_PATH:~0,-1!"

REM Export for PHP process
set "CASHIRAK_DATA_PATH=!DATA_PATH!"

set "LOCK_FILE=!DATA_PATH!\storage\cashirak.port"

REM ------------------------------------------
REM Check PHP Runtime
REM ------------------------------------------

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP runtime not found.
    echo.
    echo Expected: %PHP_EXE%
    echo.
    echo Please reinstall Cashirak POS.
    echo.
    pause
    exit /b 1
)

REM ------------------------------------------
REM Check Public Directory
REM ------------------------------------------

if not exist "%PUBLIC_DIR%" (
    echo [ERROR] Application files not found.
    echo.
    echo Expected: %PUBLIC_DIR%
    echo.
    pause
    exit /b 1
)

REM ------------------------------------------
REM Create Required Data Directories
REM ------------------------------------------

if not exist "!DATA_PATH!\database"         mkdir "!DATA_PATH!\database"
if not exist "!DATA_PATH!\storage"          mkdir "!DATA_PATH!\storage"
if not exist "!DATA_PATH!\storage\logs"     mkdir "!DATA_PATH!\storage\logs"
if not exist "!DATA_PATH!\storage\sessions" mkdir "!DATA_PATH!\storage\sessions"
if not exist "!DATA_PATH!\storage\backups"  mkdir "!DATA_PATH!\storage\backups"

REM ------------------------------------------
REM Check Existing Running Instance
REM ------------------------------------------

if exist "!LOCK_FILE!" (
    set /p EXISTING_PORT=<"!LOCK_FILE!"

    if not "!EXISTING_PORT!"=="" (
        echo Checking existing Cashirak POS server...
        echo.

        powershell -NoProfile -Command "$r=try{Invoke-WebRequest -Uri 'http://127.0.0.1:!EXISTING_PORT!/' -UseBasicParsing -TimeoutSec 2}catch{$null};if($r){exit 0}else{exit 1}"

        if !ERRORLEVEL! EQU 0 (
            echo Cashirak POS is already running.
            echo.
            echo Opening: http://127.0.0.1:!EXISTING_PORT!/
            echo.
            start "" "http://127.0.0.1:!EXISTING_PORT!/"
            exit /b 0
        )

        del /q "!LOCK_FILE!" >nul 2>&1
    )
)

REM ------------------------------------------
REM Find Available Port
REM ------------------------------------------

:find_port
netstat -ano | findstr /R /C:":%PORT% .*LISTENING" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    set /a PORT+=1
    if !PORT! GTR %MAX_PORT% (
        echo.
        echo [ERROR] No available port found between 8000-8100.
        echo.
        pause
        exit /b 1
    )
    goto find_port
)

REM ------------------------------------------
REM Save Active Port
REM ------------------------------------------

echo %PORT%>"!LOCK_FILE!"

set "URL=http://%HOST%:%PORT%/"

echo Starting Cashirak POS...
echo.
echo Server: %URL%
echo Data:   !DATA_PATH!
echo.

REM ------------------------------------------
REM Start Browser (2-second delay)
REM ------------------------------------------

start "" cmd /c "timeout /t 2 /nobreak >nul & start ^"^" ^"%URL%^""

REM ------------------------------------------
REM Start PHP Server
REM ------------------------------------------

if exist "%PHP_INI%" (
    "%PHP_EXE%" -c "%PHP_INI%" -S %HOST%:%PORT% -t "%PUBLIC_DIR%"
) else (
    "%PHP_EXE%" -S %HOST%:%PORT% -t "%PUBLIC_DIR%"
)

REM ------------------------------------------
REM PHP Server Stopped — Clean Up
REM ------------------------------------------

if exist "!LOCK_FILE!" del /q "!LOCK_FILE!" >nul 2>&1

echo.
echo ==========================================
echo        CASHIRAK POS STOPPED
echo ==========================================
echo.

endlocal
