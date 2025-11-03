# Stage 1: Build assets (PHP + Node.js for Wayfinder)
FROM php:8.4-cli-alpine AS build-assets

# Install Node.js and npm
RUN apk add --no-cache nodejs npm

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    sqlite-dev \
    && docker-php-ext-install \
    mbstring \
    zip \
    pdo \
    pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy all application files needed for build
COPY . .

# Install Composer dependencies (needed for Wayfinder)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-req=ext-sockets

# Copy package files (already copied with COPY . ., but explicit for clarity)
# Package files are needed for npm install

# Install Node.js dependencies and build assets
RUN npm ci && npm run build

# Stage 2: PHP-FPM application
FROM php:8.4-fpm-alpine AS app

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    unzip \
    sqlite \
    sqlite-dev \
    curl \
    && docker-php-ext-install \
    mbstring \
    zip \
    pdo \
    pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application code
COPY . .

# Copy built assets from build stage
COPY --from=build-assets /app/public/build ./public/build

# Install Composer dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-req=ext-sockets

# Set permissions for storage and cache directories
RUN chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

# Copy entrypoint scripts
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/scheduler-entrypoint.sh /scheduler-entrypoint.sh
RUN chmod +x /entrypoint.sh /scheduler-entrypoint.sh

# Default entrypoint
ENTRYPOINT ["/entrypoint.sh"]

# Stage 3: Nginx web server
FROM nginx:alpine AS web

# Copy built public directory from assets build
COPY --from=build-assets /app/public /usr/share/nginx/html/public

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf
