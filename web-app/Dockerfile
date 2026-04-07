# TACI Petroleum Web App - Dockerfile
# PHP 8.1 with Symfony and PostgreSQL support

FROM php:8.1-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    wget \
    libpng-dev \
    libjpeg-turbo-dev \
    libfreetype6-dev \
    zlib-dev \
    libpq-dev \
    postgresql-client \
    unzip \
    zip

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    gd \
    zip \
    opcache \
    intl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader

# Set proper permissions
RUN chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    mkdir -p /app/var/log /app/var/cache && \
    chmod -R 777 /app/var

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
