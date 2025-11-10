#!/bin/sh
set -e

set_ownership() {
    if [ "${USER_ID}" != "82" ] || [ "${GROUP_ID}" != "82" ]; then
        chown ${USER_ID}:${GROUP_ID} "$1"
    else
        chown www-data:www-data "$1"
    fi
}

DB_FILE="/var/www/database/database.sqlite"
[ ! -f "${DB_FILE}" ] && touch "${DB_FILE}" && set_ownership "${DB_FILE}" && chmod 664 "${DB_FILE}"

php artisan migrate --force

# Ensure Playwright browsers are installed (needed if queue processes monitor checks)
php artisan playwright:install --quiet || true

echo "Starting Laravel queue worker..."
exec php artisan queue:work --tries=3 --timeout=90 --no-interaction

