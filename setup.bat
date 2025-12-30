@echo off
REM AI Ticket Classifier API - Windows Setup Script
REM This script automates the initial setup for local development on Windows
REM It handles environment configuration, database creation, and key generation

setlocal enabledelayedexpansion

REM Color codes for CMD (using ANSI escape sequences)
set "GREEN=[92m"
set "YELLOW=[93m"
set "RED=[91m"
set "BLUE=[94m"
set "NC=[0m"

REM Function to print colored output
:print_status
echo %GREEN%âœ“%NC% %~1
goto :eof

:print_warning
echo %YELLOW%âš %NC% %~1
goto :eof

:print_error
echo %RED%âœ—%NC% %~1
goto :eof

:print_info
echo %BLUE%â„¹%NC% %~1
goto :eof

REM Check if Docker Desktop is running
docker info >nul 2>&1
if errorlevel 1 (
    call :print_error "Docker Desktop is not running. Please start Docker Desktop and try again."
    exit /b 1
)

call :print_info "Starting AI Ticket Classifier API local setup..."

REM Check if .env already exists
if exist ".env" (
    call :print_warning ".env file already exists!"
    call :print_warning "This script is designed for first-time setup only."
    call :print_warning "If you want to reset your environment, please backup and remove the existing .env file first."
    exit /b 1
)

REM Copy .env.example to .env
call :print_status "Creating .env file from .env.example"
copy .env.example .env >nul

REM Create SQLite database file if it doesn't exist
if not exist "database\database.sqlite" (
    call :print_status "Creating SQLite database file"
    type nul > database\database.sqlite
) else (
    call :print_status "SQLite database file already exists"
)

REM Build and start containers
call :print_status "Building and starting Docker containers..."
docker-compose up -d --build
if errorlevel 1 (
    call :print_error "Failed to start containers. Check Docker Desktop and try again."
    exit /b 1
)

REM Generate APP_KEY using Laravel's artisan command
call :print_status "Generating Laravel APP_KEY"
docker-compose exec app php artisan key:generate
if errorlevel 1 (
    call :print_error "Failed to generate APP_KEY"
    exit /b 1
)

REM Generate CSV_SIGNING_KEY using PHP
call :print_status "Generating CSV_SIGNING_KEY"
for /f "delims=" %%i in ('docker-compose run --rm app php -r "echo base64_encode(random_bytes(32));"') do set CSV_KEY=%%i
REM Remove any trailing newlines
set CSV_KEY=%CSV_KEY%

REM Update .env file with CSV_SIGNING_KEY
powershell -Command "(Get-Content .env) -replace '^CSV_SIGNING_KEY=.*', 'CSV_SIGNING_KEY=%CSV_KEY%' | Set-Content .env"

call :print_status "Setup completed successfully!"
echo.
call :print_info "IMPORTANT: You need to add your OpenRouter API key manually:"
echo.
call :print_info "1. Go to https://openrouter.ai/ and sign up for an account"
call :print_info "2. Get your API key from the dashboard"
call :print_info "3. Edit your .env file and add:"
echo    OPENROUTER_API_KEY=sk-or-v1-your-key-here
echo.
call :print_info "Next steps:"
call :print_info "1. Add your OPENROUTER_API_KEY to the .env file"
call :print_info "2. Run: docker-compose up -d"
call :print_info "3. Run: docker-compose exec app php artisan migrate"
call :print_info "4. Test the API at http://localhost:8000"
echo.
call :print_info "Happy coding! ðŸš€"

goto :eof
