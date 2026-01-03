@echo off
REM AI Ticket Classifier API - Windows Setup Script
REM This script automates the initial setup for local development on Windows
REM It handles environment configuration, database creation, and key generation

echo Starting AI Ticket Classifier API local setup...

REM Check if Docker Desktop is running
docker info >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker Desktop is not running. Please start Docker Desktop and try again.
    exit /b 1
)

REM Check if .env already exists
if exist ".env" (
    echo WARNING: .env file already exists!
    echo This script is designed for first-time setup only.
    echo If you want to reset your environment, please backup and remove the existing .env file first.
    exit /b 1
)

REM Copy .env.example to .env
echo Creating .env file from .env.example
copy .env.example .env >nul
if errorlevel 1 (
    echo ERROR: Failed to create .env file
    exit /b 1
)

REM Create SQLite database file if it doesn't exist
if not exist "database\database.sqlite" (
    echo Creating SQLite database file
    type nul > database\database.sqlite
) else (
    echo SQLite database file already exists
)

REM Check if Composer is installed locally
where composer >nul 2>&1
if errorlevel 1 (
    echo WARNING: Composer not found locally.
    echo Skipping local dependency installation - dependencies will be installed in Docker.
    echo If you need to run PHP commands locally, install Composer from: https://getcomposer.org/
) else (
    REM Install PHP dependencies locally (helps with IDE autocomplete and local development)
    echo Installing PHP dependencies locally...
    composer install --no-interaction
    if errorlevel 1 (
        echo WARNING: Failed to install dependencies locally, but continuing...
        echo Dependencies will be installed in Docker during build.
    ) else (
        echo PHP dependencies installed successfully
    )
)

REM Build and start containers
echo Building and starting Docker containers...
docker-compose up -d --build
if errorlevel 1 (
    echo ERROR: Failed to start containers. Check Docker Desktop and try again.
    exit /b 1
)

REM Wait for container to be ready
echo Waiting for container to be ready...
timeout /t 10 /nobreak >nul

REM Check if container is running
docker-compose ps app | findstr "Up" >nul
if errorlevel 1 (
    echo ERROR: Container is not running. Checking container logs...
    docker-compose logs app
    exit /b 1
)

REM Install PHP dependencies (installed during Docker build)
echo PHP dependencies installed during Docker build

REM Generate APP_KEY using Laravel's artisan command
echo Generating Laravel APP_KEY
docker-compose exec app php artisan key:generate
if errorlevel 1 (
    echo ERROR: Failed to generate APP_KEY
    echo Checking container logs for more details...
    docker-compose logs app
    exit /b 1
)

REM Generate CSV_SIGNING_KEY using PHP
echo Generating CSV_SIGNING_KEY
for /f "delims=" %%i in ('docker-compose run --rm app php -r "echo base64_encode(random_bytes(32));"') do set CSV_KEY=%%i
REM Remove any trailing newlines
set CSV_KEY=%CSV_KEY%

REM Update .env file with CSV_SIGNING_KEY
powershell -Command "(Get-Content .env) -replace '^CSV_SIGNING_KEY=.*', 'CSV_SIGNING_KEY=%CSV_KEY%' | Set-Content .env"
if errorlevel 1 (
    echo ERROR: Failed to update .env with CSV_SIGNING_KEY
    exit /b 1
)

echo Setup completed successfully!
echo.
echo IMPORTANT: You need to add your OpenRouter API key manually:
echo.
echo 1. Go to https://openrouter.ai/ and sign up for an account
echo 2. Get your API key from the dashboard
echo 3. Edit your .env file and add:
echo    OPENROUTER_API_KEY=sk-or-v1-your-key-here
echo.
echo Next steps:
echo 1. Add your OPENROUTER_API_KEY to the .env file
echo 2. Run: docker-compose up -d
echo 3. Run: docker-compose exec app php artisan migrate
echo 4. Test the API at http://localhost:8000
echo.
echo Happy coding!
