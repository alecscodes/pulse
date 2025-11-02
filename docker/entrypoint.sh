#!/bin/sh

DB_PATH=${DB_DATABASE:-/var/www/html/database/database.sqlite}
if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH"
fi

# Run composer post-install scripts
composer dump-autoload --optimize --classmap-authoritative || true

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate --force || true

# Clear cache to avoid MAC errors
php artisan optimize:clear || true

# Execute command
exec "$@"

