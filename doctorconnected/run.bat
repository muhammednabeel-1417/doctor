@echo off
title DOCTOR CONNECT - Local Server Runner
echo ========================================================
echo   DOCTOR CONNECT - Token-Based Consultation Platform
echo ========================================================
echo.

:: Detect PHP
set PHP_BIN=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    ) else if exist "D:\xampp\php\php.exe" (
        set PHP_BIN=D:\xampp\php\php.exe
    ) else (
        echo [ERROR] PHP executable was not found in PATH or C:\xampp\php\php.exe.
        echo Please ensure XAMPP is installed at C:\xampp or add PHP to your PATH.
        echo.
        pause
        exit /b 1
    )
)

echo [OK] Using PHP at: %PHP_BIN%
echo [!] Make sure MySQL is running in XAMPP Control Panel!
echo.
echo --------------------------------------------------------
echo  Starting local server at http://localhost:8000
echo  Press Ctrl+C in this window anytime to stop the server.
echo --------------------------------------------------------
echo.

:: Open browser
start http://localhost:8000/setup.php

:: Start PHP server
"%PHP_BIN%" -S localhost:8000
pause

