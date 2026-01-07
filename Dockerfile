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

# Configure PHP for longer execution times (AI processing)
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "default_socket_timeout = 300" >> /usr/local/etc/php/conf.d/custom.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Use php-fpm for proper web server integration
CMD ["php-fpm"]
