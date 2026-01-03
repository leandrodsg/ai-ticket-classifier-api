# Installation Guide

## Prerequisites

- Docker and Docker Compose
- Git
- OpenRouter API key (for AI classification)

## Local Development Setup

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
docker-compose up -d
```

This starts the containers:
- PHP application server (with SQLite database)
- PostgreSQL database (optional, for testing production-like setup)

### 4. Install Dependencies

```bash
docker-compose exec app composer install
```

### 5. Generate Application Key

```bash
docker-compose exec app php artisan key:generate
```

### 6. Run Migrations

```bash
docker-compose exec app php artisan migrate
```

### 7. Verify Installation

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

If `setup.bat` fails with "broken pipe" errors:

1. Ensure Docker Desktop is fully started
2. Wait for containers to be completely ready (may take 10-15 seconds)
3. If it still fails, use manual installation steps above

For partial setup failures:

```cmd
# Clean up and retry
docker-compose down
del .env
del database\database.sqlite
.\setup.bat
```

### Container Exits Immediately

Check container logs for issues:

```bash
docker-compose logs app
```

Common causes:
- Missing vendor directory: Run `docker-compose exec app composer install`
- Database file permissions: Recreate `database/database.sqlite`
- Environment variables: Check `.env` file is properly configured

### Permission Issues on Windows

If you get permission errors:

1. Run Command Prompt as Administrator
2. Or configure PowerShell execution policy:
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

### PHP Dependencies Not Available

PHP dependencies are installed during Docker build. If you encounter class not found errors:

1. Check Docker build completed successfully
2. Verify vendor directory exists: `docker-compose exec app ls -la vendor/`
3. Reinstall if needed: `docker-compose exec app composer install`

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
