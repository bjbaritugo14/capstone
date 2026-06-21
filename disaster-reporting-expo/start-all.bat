@echo off
title Disaster Reporting - Full Stack Launcher
echo ============================================
echo   Disaster Reporting - Full Stack Launcher
echo ============================================
echo.

:: ---- Resolve project paths relative to this script ----
set "SCRIPT_DIR=%~dp0"
:: Expo project is where this script lives
set "EXPO_DIR=%SCRIPT_DIR%"
:: Laravel project is a sibling folder
set "LARAVEL_DIR=%SCRIPT_DIR%..\matanao-disaster-ai"

:: Verify Laravel directory exists
if not exist "%LARAVEL_DIR%\.env" (
    echo [ERROR] Laravel .env not found at: %LARAVEL_DIR%\.env
    echo Make sure matanao-disaster-ai is a sibling folder of disaster-reporting-expo.
    pause
    exit /b 1
)

:: Detect the local IP address (first non-loopback IPv4)
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /R /C:"IPv4 Address"') do (
    for /f "tokens=1" %%b in ("%%a") do (
        if not defined LOCAL_IP set "LOCAL_IP=%%b"
    )
)

if not defined LOCAL_IP (
    echo [ERROR] Could not detect local IP address.
    echo Falling back to 127.0.0.1
    set "LOCAL_IP=127.0.0.1"
)

echo [INFO] Detected IP: %LOCAL_IP%
echo.

:: ---- Update Expo .env ----
set "EXPO_ENV=%EXPO_DIR%.env"
echo [INFO] Updating Expo .env...
(
    echo EXPO_PUBLIC_USE_MOCK_API=false
    echo EXPO_PUBLIC_API_BASE_URL=http://%LOCAL_IP%:8000/api
) > "%EXPO_ENV%"
echo [OK] Expo .env updated with http://%LOCAL_IP%:8000/api

:: ---- Update Laravel .env (only APP_URL line) ----
set "LARAVEL_ENV=%LARAVEL_DIR%\.env"
echo [INFO] Updating Laravel .env APP_URL...

:: Use PowerShell to do a reliable find-and-replace in the Laravel .env
powershell -Command "(Get-Content '%LARAVEL_ENV%') -replace '^APP_URL=.*', 'APP_URL=http://%LOCAL_IP%:8000' | Set-Content '%LARAVEL_ENV%'"
echo [OK] Laravel APP_URL updated to http://%LOCAL_IP%:8000

echo.
echo ============================================
echo   Starting Laravel Backend...
echo ============================================
echo.

:: Start Laravel in a new window
start "Laravel Server" cmd /k "cd /d "%LARAVEL_DIR%" && php artisan serve --host=0.0.0.0 --port=8000"

:: Wait a moment for the server to boot
timeout /t 3 /nobreak > nul

echo.
echo ============================================
echo   Starting Expo (React Native)...
echo ============================================
echo.

:: Start Expo in a new window
start "Expo Dev Server" cmd /k "cd /d "%EXPO_DIR%" && npx expo start --tunnel"

echo.
echo ============================================
echo   All services started!
echo ============================================
echo.
echo   Laravel API: http://%LOCAL_IP%:8000
echo   Expo:        Check the Expo window for QR code
echo.
echo   Press any key to close this launcher window...
pause > nul
