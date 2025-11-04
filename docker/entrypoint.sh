#!/bin/sh
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo "${RED}[ERROR]${NC} $1"; }

set_ownership() {
    if [ "${USER_ID}" != "82" ] || [ "${GROUP_ID}" != "82" ]; then
        chown -R ${USER_ID}:${GROUP_ID} "$@"
    else
        chown -R www-data:www-data "$@"
    fi
}

# Generate app key if missing
[ -z "${APP_KEY}" ] && log_info "Generating application key..." && php artisan key:generate --force

# Wait for database (non-SQLite)
if [ "${DB_CONNECTION}" != "sqlite" ] && [ -n "${DB_CONNECTION}" ]; then
    log_info "Waiting for database connection..."
    until php artisan migrate:status > /dev/null 2>&1; do
        log_warn "Database unavailable - sleeping..."
        sleep 2
    done
    log_info "Database ready!"
fi

# Setup directories and permissions
log_info "Setting up Laravel directories and permissions..."

mkdir -p /var/www/storage/{app/public,framework/{cache/data,sessions,testing,views},logs} \
         /var/www/bootstrap/cache \
         /var/www/database

set_ownership /var/www/storage /var/www/bootstrap/cache /var/www/database
find /var/www/storage /var/www/bootstrap/cache /var/www/database -type d -exec chmod 775 {} \;
find /var/www/storage /var/www/bootstrap/cache /var/www/database -type f -exec chmod 664 {} \;

# Create storage symlink
[ ! -L /var/www/public/storage ] && log_info "Creating storage symlink..." && php artisan storage:link || true

# Create SQLite database if needed
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    DB_FILE="/var/www/database/database.sqlite"
    [ ! -f "${DB_FILE}" ] && touch "${DB_FILE}" && set_ownership "${DB_FILE}" && chmod 664 "${DB_FILE}"
fi

# Laravel optimizations
log_info "Clearing optimizations..."
php artisan optimize:clear || true

log_info "Running migrations..."
php artisan migrate --force

log_info "Optimizing..."
composer dump-autoload --optimize --no-interaction --quiet
php artisan optimize || true

log_info "Entrypoint setup completed successfully!"
exec php-fpm
