#!/bin/sh
set -e

# Wait for database to be ready
if [ -f /var/www/database/database.sqlite ]; then
    echo "SQLite database exists"
else
    echo "Creating SQLite database..."
    touch /var/www/database/database.sqlite
    chown www-data:www-data /var/www/database/database.sqlite
    chmod 664 /var/www/database/database.sqlite
fi

# Run migrations (in case scheduler starts before app)
php artisan migrate --force

# Run Laravel scheduler
echo "Starting Laravel scheduler..."
exec php artisan schedule:work --no-interaction

