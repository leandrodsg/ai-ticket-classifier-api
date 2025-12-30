# Contributing

Thank you for considering contributing to AI Ticket Classifier API!

## Getting Started

Follow the [Quick Start guide](README.md#-quick-start) to set up your development environment.

**Database Options:**
- **SQLite** (default): No additional setup required, perfect for quick development
- **PostgreSQL** (optional): For testing production-like database features, run:
  ```bash
  docker-compose --profile postgres up -d
  # Then change DB_CONNECTION=pgsql in .env and configure DB_* variables
  ```

## Running Tests
```bash
# All tests
docker-compose exec app php artisan test

# Unit tests only
docker-compose exec app php artisan test --testsuite=Unit

# Feature tests only
docker-compose exec app php artisan test --testsuite=Feature
```

## Code Style

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.

## Submitting Changes

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass
5. Commit your changes with clear messages
6. Push to your fork
7. Open a Pull Request

## Reporting Bugs

Please use the [issue tracker](https://github.com/leandrodsg/ai-ticket-classifier-api/issues) to report bugs.

Include:
- Steps to reproduce
- Expected behavior
- Actual behavior
- Your environment (OS, Docker version)
