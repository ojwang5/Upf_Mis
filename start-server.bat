@echo off
REM Start PHP built-in server serving the public folder on port 8000
REM Usage: double-click start-server.bat or run from cmd prompt: start-server.bat

where php >nul 2>&1
if %errorlevel%==0 (
    start "PHP Server" php -S localhost:8000 -t public
    goto :eof
)

if exist "C:\xampp\php\php.exe" (
    start "PHP Server" "C:\xampp\php\php.exe" -S localhost:8000 -t public
    goto :eof
)

echo php.exe not found in PATH and default XAMPP path not present.
echo Edit start-server.bat to point to your php.exe or add php to PATH.
pause
