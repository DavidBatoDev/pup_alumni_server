# Use PHP 8.2 with FPM for Laravel
FROM php:8.2-fpm

# Set the working directory inside the container
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql zip

# Install Composer globally
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Allow Composer to run as root (optional but necessary for Docker)
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy Laravel application code to the container
COPY . .

# Install Laravel dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions for Laravel storage and cache
RUN chmod -R 775 storage bootstrap/cache

# Expose PHP-FPM's default port
EXPOSE 9000

# Start PHP-FPM when the container starts
CMD ["php-fpm"]
