# AI Ticket Classifier API - Cross-platform Makefile
# This Makefile provides common development tasks for all platforms
# Windows users: Install make from https://gnuwin32.sourceforge.net/packages/make.htm or use WSL

.PHONY: help setup start stop restart migrate test test-unit test-feature logs shell clean

# Default target
help: ## Show this help message
	@echo "AI Ticket Classifier API - Development Commands"
	@echo ""
	@echo "Available commands:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Detect OS and call appropriate setup script
setup: ## Run the setup script (detects OS automatically)
	@echo "Detecting operating system..."
	@if [ "$$(uname -s)" = "Windows_NT" ] || [ "$$(uname -s)" = "MINGW64_NT" ] || [ "$$(uname -s)" = "MSYS_NT" ]; then \
		echo "Windows detected - running setup.bat"; \
		./setup.bat; \
	else \
		echo "Unix-like system detected - running setup.sh"; \
		chmod +x setup.sh && ./setup.sh; \
	fi

start: ## Start all containers
	docker-compose up -d

stop: ## Stop all containers
	docker-compose down

restart: ## Restart all containers
	docker-compose restart

migrate: ## Run database migrations
	docker-compose exec app php artisan migrate

test: ## Run all tests
	docker-compose exec app php artisan test

test-unit: ## Run unit tests only
	docker-compose exec app php artisan test --testsuite=Unit

test-feature: ## Run feature tests only
	docker-compose exec app php artisan test --testsuite=Feature

logs: ## Show container logs
	docker-compose logs -f

shell: ## Open bash shell in app container
	docker-compose exec app bash

clean: ## Stop containers and remove all data
	docker-compose down -v --remove-orphans
