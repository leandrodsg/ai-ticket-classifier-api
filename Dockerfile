FROM php:8.5-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    sqlite3 \
    libsqlite3-dev

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# WARNING: This command is for LOCAL DEVELOPMENT ONLY
# php artisan serve is NOT production-ready and should NEVER be used in production
# For production deployments:
#   - Use nginx + php-fpm (see nginx.conf)
#   - Use a process supervisor (e.g., supervisord)
#   - Configure proper security headers and rate limiting
# This Dockerfile is designed for local Docker development environments only
CMD php artisan serve --host=0.0.0.0 --port=8000
