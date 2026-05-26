 # Start PHP built-in server serving the `public` folder on port 8000
# Usage: Right-click -> Run with PowerShell or from a PowerShell prompt: .\start-server.ps1

$php = (Get-Command php -ErrorAction SilentlyContinue)?.Source
if (-not $php) {
    $fallback = 'C:\xampp\php\php.exe'
    if (Test-Path $fallback) { $php = $fallback }
}

if (-not $php) {
    Write-Error 'php executable not found. Add PHP to PATH or edit start-server.ps1 to point to your php.exe.'
    exit 1
}

$port = 8000
$host = 'localhost'
$root = 'public'
Write-Host "Starting PHP dev server: http://$host`:$port (document root: $root) using $php"
# Use the router script so /public routes correctly (e.g. /login.php)
Start-Process -FilePath $php -ArgumentList "-S","$host`:$port","-t","$root","$root\router.php" -WorkingDirectory (Resolve-Path .) -NoNewWindow:$false
Write-Host 'Server started.'

