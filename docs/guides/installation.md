# Installation Guide

## Prerequisites

- Docker and Docker Compose (with at least 2GB RAM allocated)
- Git
- OpenRouter API key (for AI classification) - Get yours at https://openrouter.ai/
- **Optional:** PHP 8.5+ and Composer (for local development and IDE support)

**Important:** PHP and Composer are **NOT required** to run the application. All dependencies are automatically installed inside Docker during the build process. Local PHP/Composer are only useful if you want to:
- Run commands outside Docker
- Get IDE autocomplete support
- Develop without Docker

## Expected Setup Time

- **First-time setup:** 2-5 minutes (depends on internet speed and machine)
- **Subsequent builds:** 30-60 seconds (Docker caching)
- **Container startup:** 10-20 seconds after build completes

## Automated Setup (Recommended)

### Windows

```cmd
git clone https://github.com/leandrodsg/ai-ticket-classifier-api.git
cd ai-ticket-classifier-api
.\setup.bat
```

The script will:
1. Check Docker is running
2. Create `.env` from `.env.example`
3. Create SQLite database file
4. Optionally install dependencies locally (if Composer exists)
5. Build and start Docker containers (2-5 minutes on first run)
6. Wait up to 2 minutes for containers to be healthy (with retry logic)
7. Generate `APP_KEY` (with 3 retry attempts)
8. Generate `CSV_SIGNING_KEY` (with 3 retry attempts)

**After the script completes:**

```cmd
# Add your OpenRouter API key to .env
notepad .env
# Find OPENROUTER_API_KEY= and add your key

# Start containers if not running
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Verify
curl http://localhost:8000/api/health
```

### macOS / Linux

```bash
git clone https://github.com/leandrodsg/ai-ticket-classifier-api.git
cd ai-ticket-classifier-api
chmod +x setup.sh
./setup.sh

# Add your API key to .env
vim .env  # or nano .env

# Start and migrate
docker-compose up -d
docker-compose exec app php artisan migrate
```

## Manual Installation

If the automated setup fails, follow these steps:

### 1. Clone Repository

```bash
git clone https://github.com/leandrodsg/ai-ticket-classifier-api.git
cd ai-ticket-classifier-api
```

### 2. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` and configure required variables:

```env
APP_KEY=base64:your-key-here
APP_ENV=local
APP_DEBUG=true

# Database: SQLite (default for local development)
DB_CONNECTION=sqlite

# AI Configuration
OPENROUTER_API_KEY=your-api-key
```

### 3. Start Docker Services

```bash
docker-compose up -d --build
```

This starts the containers and installs PHP dependencies automatically:
- PHP application server (with SQLite database)
- PostgreSQL database (optional, for testing production-like setup)
- All PHP dependencies are installed during the Docker build process

### 4. Generate Application Key

```bash
docker-compose exec app php artisan key:generate
```

### 5. Run Migrations

```bash
docker-compose exec app php artisan migrate
```

### 6. Verify Installation

```bash
curl http://localhost:8000/api/health
```

Expected response:

```json
{
  "status": "ok",
  "message": "API is running"
}
```

## Running Tests

Execute the test suite:

```bash
docker-compose exec app php artisan test
```

For specific test groups:

```bash
# Unit tests only
docker-compose exec app php artisan test tests/Unit

# Feature tests only
docker-compose exec app php artisan test tests/Feature

# Specific test file
docker-compose exec app php artisan test tests/Feature/TicketUploadControllerTest.php
```

## Common Issues

### Setup Script Times Out Waiting for Container

**Symptoms:**
- Script shows "Container not ready yet, retrying..."
- Reaches maximum retries (24 attempts = 2 minutes)

**Causes:**
- Docker build is still running (first-time builds take 2-5 minutes)
- Slow internet connection during dependency download
- Low Docker memory allocation (<2GB)

**Solutions:**
```bash
# Check if container is actually running
docker-compose ps

# Check build logs
docker-compose logs app

# If container is running but not healthy, wait longer
# The script has 2-minute timeout, which should be enough

# If truly stuck, force rebuild
docker-compose down
docker-compose up -d --build --force-recreate
```

### Setup Script Fails at Key Generation

**Symptoms:**
- "ERROR: Failed to generate APP_KEY after 3 attempts"

**Causes:**
- Container started but Laravel not fully initialized
- Database file locked or missing

**Solutions:**
```cmd
# Wait 30 seconds for Laravel to fully initialize
timeout /t 30

# Manually generate keys
docker-compose exec app php artisan key:generate --force

# For CSV_SIGNING_KEY, add to .env manually:
# CSV_SIGNING_KEY=base64:YOUR_KEY_HERE
# Generate key with: docker-compose exec app php -r "echo base64_encode(random_bytes(32));"
```

### "vendor/autoload.php not found" Error

**Symptoms:**
- Container logs show: "Fatal error: Failed opening required '/var/www/html/vendor/autoload.php'"

**Cause:**
- Docker build did not complete successfully
- The Dockerfile should run `composer install` during build, but it failed

**Solution:**
```bash
# Check if composer install ran during build
docker-compose logs app | findstr "composer"

# Force rebuild and check for errors
docker-compose build app 2>&1 | findstr "error"

# If composer timeout occurred, rebuild:
docker-compose down
docker-compose up -d --build --force-recreate

# Verify vendor directory exists
docker-compose exec app ls -la vendor/autoload.php
```

### Database Connection Failed

Ensure the SQLite database file exists and has correct permissions:

```bash
ls -la database/database.sqlite
```

Recreate database file if needed:

```bash
rm database/database.sqlite
touch database/database.sqlite
docker-compose exec app php artisan migrate
```

### Memory Limit Exceeded in Tests

Some tests require more memory. Run with increased limit:

```bash
docker-compose exec app php -d memory_limit=512M artisan test
```

### Port Already in Use

If port 8000 is occupied, change it in `docker-compose.yml`:

```yaml
ports:
  - "8001:8000"
```

### Setup Script Issues (Windows)

**Old behavior (before improvements):**
- Script waited only 10 seconds for containers
- No retry logic for key generation
- Failed silently if commands didn't work

**New behavior (current version):**
- Waits up to 2 minutes with health checks every 5 seconds
- 3 retry attempts for APP_KEY generation
- 3 retry attempts for CSV_SIGNING_KEY generation
- Clear error messages and logs

**If setup.bat still fails:**

```cmd
# Clean slate
docker-compose down -v
del .env
del database\database.sqlite

# Manual setup
copy .env.example .env
type nul > database\database.sqlite
docker-compose up -d --build

# Wait for container to be ready (check logs)
docker-compose logs -f app
# Press Ctrl+C when you see "Server running on [http://0.0.0.0:8000]"

# Generate keys manually
docker-compose exec app php artisan key:generate --force

# Generate CSV key
docker-compose exec app php -r "echo base64_encode(random_bytes(32));"
# Copy output and add to .env as CSV_SIGNING_KEY=<output>

# Add your OPENROUTER_API_KEY to .env
notepad .env

# Run migrations
docker-compose exec app php artisan migrate
```

### Container Exits Immediately

Check container logs for issues:

```bash
docker-compose logs app
```

Common causes:
- **Docker build failed:** Look for "composer install" errors in build logs
  ```bash
  docker-compose build app 2>&1 | findstr "error"
  ```
  Solution: Rebuild with `docker-compose up -d --build --force-recreate`

- **vendor/ directory missing:** Should not happen (Dockerfile installs dependencies)
  ```bash
  docker-compose exec app ls -la vendor/autoload.php
  ```
  If missing, the Docker build failed - check build logs

- **Database file permissions:** Recreate `database/database.sqlite`
  ```bash
  rm database/database.sqlite
  touch database/database.sqlite
  ```

- **Environment variables:** Check `.env` file is properly configured
  ```bash
  cat .env | grep APP_KEY
  # Should show: APP_KEY=base64:...
  ```

### Permission Issues on Windows

If you get permission errors:

1. Run Command Prompt as Administrator
2. Or configure PowerShell execution policy:
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

## Development Commands

Start services:
```bash
docker-compose up -d
```

Stop services:
```bash
docker-compose down
```

View logs:
```bash
docker-compose logs -f app
```

Access application shell:
```bash
docker-compose exec app bash
```

Clear cache:
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
```

## Production Deployment

See deployment configuration in the Railway section of the main README.
