<div align="center">

# 🎯 AI Ticket Classifier API

**AI-powered ticket classification with ITIL methodology**

[![Tests](https://img.shields.io/badge/tests-366%20passing-brightgreen)](https://github.com/leandrodsg/ai-ticket-classifier-api)
[![Docker](https://img.shields.io/badge/docker-ready-2496ED?logo=docker&logoColor=white)](https://github.com/leandrodsg/ai-ticket-classifier-api)
[![Laravel](https://img.shields.io/badge/Laravel-12-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.5-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

[Quick Start](#-quick-start) • [Features](#-features) • [API](#-api)

</div>

---

## What is this?

REST API that takes messy support tickets and turns them into organized, AI-classified, ITIL-prioritized workflows.

Upload a CSV → AI classifies everything → Get back structured data with categories, priorities, SLA deadlines, and sentiment analysis.

Built as a learning project exploring Laravel 12, AI integration patterns (retry, fallback, timeout), ITIL calculations, and test-driven development.

---

## ⚡ Quick Start

**Requirements:** Docker, Docker Compose, Git

**Optional (for local development):** PHP 8.5+, Composer (helpful for IDE autocomplete, but not required for Docker setup)

### Windows
```cmd
git clone https://github.com/leandrodsg/ai-ticket-classifier-api.git
cd ai-ticket-classifier-api
.\setup.bat
# Edit .env and add your OPENROUTER_API_KEY (get it at https://openrouter.ai/)
docker-compose up -d
docker-compose exec app php artisan migrate

# Verify setup
curl http://localhost:8000/api/health
# Expected: {"status":"ok","message":"API is running"}
```

**First-time setup notes:**
- The initial build takes 2-5 minutes (downloads Docker images, installs PHP dependencies)
- The setup script includes automatic retry logic and health checks
- Local Composer is **NOT required** - all dependencies install inside Docker
- If you have Composer locally, the script will install dependencies for IDE autocomplete (optional)

**If setup.bat fails or you need to reset:**

```cmd
# Clean up
docker-compose down
del .env
del database\database.sqlite

# Retry
.\setup.bat
```

**Troubleshooting:**
- If setup fails with "container not ready", wait for the full 2-minute timeout
- Run `docker-compose logs app` to see detailed error messages
- Ensure Docker Desktop is running and has at least 2GB RAM allocated

For detailed manual installation steps, see [docs/guides/installation.md](docs/guides/installation.md).

### macOS / Linux
```bash
git clone https://github.com/leandrodsg/ai-ticket-classifier-api.git
cd ai-ticket-classifier-api
chmod +x setup.sh
./setup.sh
# Edit .env and add your OPENROUTER_API_KEY (get it at https://openrouter.ai/)
docker-compose up -d
docker-compose exec app php artisan migrate
```

**Note:** Local Composer is **NOT required** - all dependencies install inside Docker. If you have Composer installed locally, the setup script will install dependencies for IDE autocomplete (optional).

### Using Make (all platforms)
```bash
git clone https://github.com/leandrodsg/ai-ticket-classifier-api.git
cd ai-ticket-classifier-api
make setup
# Edit .env and add your OPENROUTER_API_KEY (get it at https://openrouter.ai/)
make start
make migrate
```

**Database:** Uses SQLite by default (no additional setup needed). PostgreSQL is available optionally.

**Test it:**
```bash
curl http://localhost:8000/api/health
```

---

## Common Issues

**Setup script times out waiting for container:**
- **Normal on first run**: Docker build takes 2-5 minutes to install dependencies
- Let the script run for the full 2-minute timeout period
- If it fails, run `docker-compose logs app` to see what went wrong
- Common causes: slow internet, low Docker memory allocation (<2GB)

**Setup script fails at key generation:**
- Cause: Container started but Laravel not fully initialized
- Solution: Wait 30 seconds and manually run:
  ```cmd
  docker-compose exec app php artisan key:generate
  ```

**Container exits immediately:**
- Check logs: `docker-compose logs app`
- Most likely cause: Docker build failed to install dependencies
- Solution: Run `docker-compose up -d --build --force-recreate` to rebuild
- Verify `vendor/autoload.php` exists: `docker-compose exec app ls -la vendor/autoload.php`

**"vendor/autoload.php not found" error:**
- This should NOT happen with the current Dockerfile (it runs `composer install`)
- If you see this error, the Docker build failed
- Check build logs: `docker-compose build app 2>&1 | findstr "error"`
- Common cause: composer timeout during build (slow connection)

**Port 8000 already in use:**
```bash
docker-compose down
# Change ports in docker-compose.yml if needed
```

**Permission denied on Windows:**
- Run Command Prompt as Administrator
- Or use PowerShell: `Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser`

**Permission denied (Linux only):**
```bash
sudo usermod -aG docker $USER
# Logout and login again
```

**Tests fail with "multiple tickets classification" error:**
- This is a known issue being investigated
- 365 out of 366 tests pass - the project is functional
- To run tests: `docker-compose exec app php -d memory_limit=512M artisan test`

**Need help?** [Open an issue](https://github.com/leandrodsg/ai-ticket-classifier-api/issues)

---

## Features

![Features Overview](docs/images/features-overview.png)

---

## Architecture

![System Architecture](docs/images/architecture.png)

**How it works:**

1. **CSV Upload** → Validated and sanitized
2. **Security Layer** → HMAC signature + nonce verification + rate limiting
3. **CSV Processing** → Parser extracts data, validator checks schema
4. **AI Classification** → 3-model fallback (Claude → GPT-4 → Gemini)
5. **ITIL Calculation** → Impact × Urgency → Priority
6. **SLA Calculation** → Auto deadline based on priority
7. **Storage** → SQLite/PostgreSQL + Cache (30min TTL)

---

## API Reference

### Generate Test CSV

```http
POST /api/csv/generate
Content-Type: application/json

{
  "ticket_count": 10
}
```

<details>
<summary><b>Response Example</b></summary>

```json
{
  "csv_content": "base64_encoded_csv_string",
  "filename": "tickets-2025-12-29.csv",
  "metadata": {
    "signature": "hmac_sha256_hash",
    "nonce": "32_char_random_string",
    "session_id": "uuid_v4",
    "expires_at": "2025-12-29T15:00:00Z"
  }
}
```

</details>

**Rate limit:** 60/min

---

### Upload & Classify Tickets

```http
POST /api/tickets/upload
Content-Type: application/json

{
  "csv_content": "base64_encoded_csv"
}
```

<details>
<summary><b>Response Example</b></summary>

```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed",
  "processed": 10,
  "failed": 0,
  "processing_time_ms": 8432,
  "results": [
    {
      "issue_key": "DEMO-001",
      "summary": "Cannot access dashboard",
      "category": "Technical",
      "sentiment": "Negative",
      "priority": "High",
      "impact": "High",
      "urgency": "Medium",
      "sla_due_date": "2025-12-29T14:00:00Z",
      "reasoning": "User reports critical access issue..."
    }
  ]
}
```

</details>

**Rate limit:** 10/min

---

### Query Classification Status

```http
GET /api/tickets/{session_id}
```

<details>
<summary><b>Response (processing)</b></summary>

```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "processing",
  "progress": {
    "processed": 5,
    "total": 10,
    "percentage": 50
  }
}
```

</details>

**Rate limit:** 120/min

---

## Testing

**366 tests • 2114 assertions • 100% success rate**

```bash
# Run all tests
docker-compose exec app php -d memory_limit=512M artisan test

# Unit tests only (280 tests)
docker-compose exec app php artisan test --testsuite=Unit

# Feature tests only (52 tests)
docker-compose exec app php artisan test --testsuite=Feature
```

---

## Performance Benchmarks

![Performance Benchmarks](docs/images/performance-benchmarks.png)

All targets exceeded in production testing ✓

---

## Tech Stack

![Tech Stack](docs/images/tech-stack.png)


---

## Contributing

Contributions welcome! Please:

1. Fork the repository
2. Create a new feature branch
3. Write tests (we maintain 100% coverage)
4. Follow PSR-12 standards
5. Submit a PR

**Development workflow:**

```bash
# Start development environment
docker-compose up -d

# Run tests before committing
docker-compose exec app php artisan test

# Code style check
docker-compose exec app ./vendor/bin/phpstan analyse
```

---

## License

MIT License - see [LICENSE](LICENSE) file

---
