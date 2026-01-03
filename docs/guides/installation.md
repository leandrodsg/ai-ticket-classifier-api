# Installation Guide

## Prerequisites

- Docker and Docker Compose
- Git
- OpenRouter API key (for AI classification)
- **Optional:** PHP 8.5+ and Composer (for local development and IDE support)

**Note:** PHP dependencies are automatically installed during Docker build. Local PHP and Composer are only needed if you want to run commands outside Docker or get IDE autocomplete support.

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
- Docker build failed: Rebuild with `docker-compose up -d --build --force-recreate`
- Database file permissions: Recreate `database/database.sqlite`
- Environment variables: Check `.env` file is properly configured

If dependencies are missing (rare), they should be installed during build. Check Docker build logs for errors.

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
