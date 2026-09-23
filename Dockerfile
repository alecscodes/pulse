FROM dunglas/frankenphp:php8.4
RUN install-php-extensions pcntl intl zip
RUN apt-get update && apt-get install -y --no-install-recommends nodejs npm chromium \
    && rm -rf /var/lib/apt/lists/*
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci && npm run build && npm prune --omit=dev
CMD ["frankenphp", "php-server", "-r", "public/"]
