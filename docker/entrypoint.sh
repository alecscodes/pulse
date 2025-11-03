#!/bin/sh
set -e

# Generate app key only if missing
if [ -z "${APP_KEY}" ]; then
    echo "Generating app key..."
    php artisan key:generate --force
fi

# Wait for database to be ready
if [ -f /var/www/database/database.sqlite ]; then
    echo "SQLite database exists"
else
    echo "Creating SQLite database..."
    touch /var/www/database/database.sqlite
    chown www-data:www-data /var/www/database/database.sqlite
    chmod 664 /var/www/database/database.sqlite
fi

# Run migrations
php artisan migrate --force

# Optimize Composer autoloader (ensure optimization)
composer dump-autoload --optimize --no-interaction

# Clear optimizations
php artisan optimize:clear

# Cache Laravel optimizations
php artisan optimize

# Start PHP-FPM
exec php-fpm
