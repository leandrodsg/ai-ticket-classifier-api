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

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=ticket_classifier
DB_USERNAME=postgres
DB_PASSWORD=postgres

CACHE_DRIVER=redis
REDIS_HOST=redis

OPENROUTER_API_KEY=your-api-key
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
```

### 3. Start Docker Services

```bash
docker-compose up -d
```

This starts three containers:
- PHP-FPM application server
- PostgreSQL database
- Redis cache

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
curl http://localhost:8000/api/info
```

Expected response:

```json
{
  "service": "AI Ticket Classifier API",
  "version": "1.0.0",
  "environment": "local"
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

Check if PostgreSQL container is running:

```bash
docker-compose ps
```

Restart database if needed:

```bash
docker-compose restart db
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
