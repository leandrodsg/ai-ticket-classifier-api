# Production-ready Dockerfile using FrankenPHP
# FrankenPHP is a modern application server for PHP built on top of Caddy
# https://frankenphp.dev/

FROM dunglas/frankenphp:latest-php8.5

# Install system dependencies
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    opcache \
    zip \
    bcmath \
    mbstring

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Optimize Laravel for production
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Expose port (Railway will override with $PORT)
EXPOSE 8000

# Start FrankenPHP with Laravel worker mode (4 workers for better concurrency)
CMD ["frankenphp", "php-server", "--workers", "4", "--listen", ":8000"]
