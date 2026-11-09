# ========================================================
# DOCTOR CONNECT - PowerShell Application Runner
# Run in PowerShell: .\run.ps1
# ========================================================

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  DOCTOR CONNECT - Token-Based Consultation Platform   " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

# 1. Locate PHP binary
$phpBin = "php"
if (-not (Get-Command "php" -ErrorAction SilentlyContinue)) {
    if (Test-Path "C:\xampp\php\php.exe") {
        $phpBin = "C:\xampp\php\php.exe"
    } elseif (Test-Path "D:\xampp\php\php.exe") {
        $phpBin = "D:\xampp\php\php.exe"
    } elseif (Test-Path "$env:LOCALAPPDATA\Programs\php\php.exe") {
        $phpBin = "$env:LOCALAPPDATA\Programs\php\php.exe"
    } else {
        Write-Host "`n[ERROR] PHP was not found." -ForegroundColor Red
        Write-Host "Please ensure XAMPP is installed (usually at C:\xampp\php\php.exe)" -ForegroundColor Yellow
        Write-Host "or install PHP and add it to your system PATH." -ForegroundColor Yellow
        Read-Host "`nPress Enter to exit"
        exit 1
    }
}

Write-Host "`n[+] Found PHP at: $phpBin" -ForegroundColor Green

# 2. Check if user wants to run database setup via CLI first
Write-Host "[+] Preparing to start application..." -ForegroundColor Cyan
Write-Host "[!] Note: Make sure MySQL is running in your XAMPP Control Panel!`n" -ForegroundColor Yellow

$port = 8000
$url = "http://localhost:$port"

Write-Host "--------------------------------------------------------" -ForegroundColor DarkGray
Write-Host " Web Server: $url" -ForegroundColor Green
Write-Host " Setup Tool: $url/setup.php" -ForegroundColor Cyan
Write-Host " Login Page: $url/auth/login.php" -ForegroundColor Cyan
Write-Host " Press [Ctrl + C] in this window anytime to stop the server." -ForegroundColor DarkGray
Write-Host "--------------------------------------------------------`n" -ForegroundColor DarkGray

# Open default browser after 1 second
Start-Process "$url/setup.php"

# Start PHP built-in web server
& $phpBin -S "localhost:$port"

