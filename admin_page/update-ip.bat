@echo off
echo ======================================
echo    XAMPP IP Configuration Tool
echo ======================================
echo.

echo Current IP configuration in .env:
findstr "VITE_" .env 2>nul || echo No .env file found!
echo.

echo Your current IP addresses:
ipconfig | findstr "IPv4"
echo.

set /p NEW_IP="Enter your new XAMPP IP address (e.g., 192.168.1.100): "

if "%NEW_IP%"=="" (
    echo No IP entered. Exiting...
    pause
    exit /b
)

echo.
echo Updating .env file with new IP: %NEW_IP%

(
echo # API Configuration
echo VITE_API_BASE_URL=http://%NEW_IP%/project/admin_page
echo.
echo # หมายเหตุ: ทุก API endpoints จะถูกสร้างจาก VITE_API_BASE_URL อัตโนมัติ
) > .env

echo.
echo ✅ Updated successfully!
echo New configuration:
echo VITE_API_BASE_URL=http://%NEW_IP%/project/admin_page
echo.
echo ⚠️  All API endpoints will be automatically generated from this base URL!
echo.
echo ⚠️  Please restart your development server for changes to take effect!
echo    Run: npm run dev
echo.
pause
