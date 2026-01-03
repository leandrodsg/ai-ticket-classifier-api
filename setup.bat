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
echo Building and starting Docker containers (this may take 2-5 minutes on first run)...
docker-compose up -d --build
if errorlevel 1 (
    echo ERROR: Failed to start containers. Check Docker Desktop and try again.
    exit /b 1
)

REM Wait for containers to be healthy with retry logic
echo Waiting for container to be ready (checking every 5 seconds)...
set MAX_RETRIES=24
set RETRY_COUNT=0

:wait_for_container
timeout /t 5 /nobreak >nul
set /a RETRY_COUNT+=1

REM Check if container is healthy using Docker's health check
docker inspect ticket-classifier-api --format="{{.State.Health.Status}}" >nul 2>&1
if errorlevel 1 (
    REM No healthcheck defined, fallback to checking if container is running
    docker-compose ps app | findstr "Up" >nul
    if errorlevel 1 (
        if %RETRY_COUNT% LSS %MAX_RETRIES% (
            echo Container not ready yet, retrying... ^(%RETRY_COUNT%/%MAX_RETRIES%^)
            goto wait_for_container
        ) else (
            echo ERROR: Container failed to start after 2 minutes. Checking logs...
            docker-compose logs app
            exit /b 1
        )
    )
) else (
    REM Container has healthcheck, wait for it to be healthy
    for /f "delims=" %%i in ('docker inspect ticket-classifier-api --format^="{{.State.Health.Status}}"') do set HEALTH_STATUS=%%i
    if not "%HEALTH_STATUS%"=="healthy" (
        if %RETRY_COUNT% LSS %MAX_RETRIES% (
            echo Container health status: %HEALTH_STATUS%, waiting... ^(%RETRY_COUNT%/%MAX_RETRIES%^)
            goto wait_for_container
        ) else (
            echo ERROR: Container failed to become healthy after 2 minutes. Checking logs...
            docker-compose logs app
            exit /b 1
        )
    )
)

echo Container is ready!

REM Install PHP dependencies (installed during Docker build)
echo PHP dependencies were installed during Docker build

REM Generate APP_KEY using Laravel's artisan command with retry logic
echo Generating Laravel APP_KEY...
set KEY_RETRY_COUNT=0
set KEY_MAX_RETRIES=3

:generate_app_key
docker-compose exec -T app php artisan key:generate --force 2>nul
if errorlevel 1 (
    set /a KEY_RETRY_COUNT+=1
    if %KEY_RETRY_COUNT% LSS %KEY_MAX_RETRIES% (
        echo Failed to generate APP_KEY, retrying... ^(%KEY_RETRY_COUNT%/%KEY_MAX_RETRIES%^)
        timeout /t 3 /nobreak >nul
        goto generate_app_key
    ) else (
        echo ERROR: Failed to generate APP_KEY after %KEY_MAX_RETRIES% attempts
        echo Checking container logs for more details...
        docker-compose logs app
        exit /b 1
    )
)

echo APP_KEY generated successfully

REM Generate CSV_SIGNING_KEY using PHP
echo Generating CSV_SIGNING_KEY...
set CSV_RETRY_COUNT=0
set CSV_MAX_RETRIES=3

:generate_csv_key
for /f "usebackq delims=" %%i in (`docker-compose exec -T app php -r "echo base64_encode(random_bytes(32));" 2^>nul`) do set CSV_KEY=%%i
if "%CSV_KEY%"=="" (
    set /a CSV_RETRY_COUNT+=1
    if %CSV_RETRY_COUNT% LSS %CSV_MAX_RETRIES% (
        echo Failed to generate CSV_SIGNING_KEY, retrying... ^(%CSV_RETRY_COUNT%/%CSV_MAX_RETRIES%^)
        timeout /t 3 /nobreak >nul
        goto generate_csv_key
    ) else (
        echo ERROR: Failed to generate CSV_SIGNING_KEY after %CSV_MAX_RETRIES% attempts
        exit /b 1
    )
)

REM Update .env file with CSV_SIGNING_KEY
powershell -Command "try { (Get-Content .env) -replace '^CSV_SIGNING_KEY=.*', 'CSV_SIGNING_KEY=%CSV_KEY%' | Set-Content .env; exit 0 } catch { exit 1 }"
if errorlevel 1 (
    echo ERROR: Failed to update .env with CSV_SIGNING_KEY
    exit /b 1
)

echo CSV_SIGNING_KEY generated and saved to .env

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
