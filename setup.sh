#!/bin/bash

# AI Ticket Classifier API - Local Setup Script
# This script automates the initial setup for local development
# It handles environment configuration, database creation, and key generation

set -e  # Exit on any error

# Color codes for better UX
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check if Docker and Docker Compose are available
if ! command_exists docker; then
    print_error "Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command_exists docker-compose; then
    print_error "Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

print_info "Starting AI Ticket Classifier API local setup..."

# Check if .env already exists
if [ -f ".env" ]; then
    print_warning ".env file already exists!"
    print_warning "This script is designed for first-time setup only."
    print_warning "If you want to reset your environment, please backup and remove the existing .env file first."
    exit 1
fi

# Copy .env.example to .env
print_status "Creating .env file from .env.example"
cp .env.example .env

# Create SQLite database file if it doesn't exist
if [ ! -f "database/database.sqlite" ]; then
    print_status "Creating SQLite database file"
    touch database/database.sqlite
else
    print_status "SQLite database file already exists"
fi

# Generate APP_KEY using Laravel's artisan command
print_status "Generating Laravel APP_KEY"
docker-compose run --rm app php artisan key:generate

# Generate CSV_SIGNING_KEY using PHP
print_status "Generating CSV_SIGNING_KEY"
CSV_KEY=$(docker-compose run --rm app php -r "echo base64_encode(random_bytes(32));")
# Remove any trailing newlines and update the .env file
CSV_KEY=$(echo "$CSV_KEY" | tr -d '\n' | tr -d '\r')

# Cross-platform sed command (works on Linux and macOS)
if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' "s/^CSV_SIGNING_KEY=.*/CSV_SIGNING_KEY=$CSV_KEY/" .env
else
    sed -i "s/^CSV_SIGNING_KEY=.*/CSV_SIGNING_KEY=$CSV_KEY/" .env
fi

print_status "Setup completed successfully!"
echo
print_info "IMPORTANT: You need to add your OpenRouter API key manually:"
echo
print_info "1. Go to https://openrouter.ai/ and sign up for an account"
print_info "2. Get your API key from the dashboard"
print_info "3. Add it to your .env file:"
echo "   OPENROUTER_API_KEY=sk-or-v1-your-key-here"
echo
print_info "Next steps:"
print_info "1. Add your OPENROUTER_API_KEY to the .env file"
print_info "2. Start the application: docker-compose up -d"
print_info "3. Run database migrations: docker-compose exec app php artisan migrate"
print_info "4. Test the API at http://localhost:8000"
echo
print_info "Happy coding! 🚀"
