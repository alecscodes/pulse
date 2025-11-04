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

# Fix Git directory permissions if .git exists (for update functionality)
# Best practice: Set permissions at container startup, not at runtime
if [ -d "/var/www/.git" ]; then
    log_info "Setting up Git directory permissions..."
    # Set ownership first (this is critical for Docker volume mounts)
    set_ownership /var/www/.git

    # Set directory permissions (readable and executable)
    find /var/www/.git -type d -exec chmod 755 {} \; 2>/dev/null || true

    # Set file permissions (readable)
    find /var/www/.git -type f -exec chmod 644 {} \; 2>/dev/null || true

    # Make specific Git files writable (these are modified by Git operations)
    [ -f "/var/www/.git/FETCH_HEAD" ] && chmod 666 /var/www/.git/FETCH_HEAD 2>/dev/null || true
    [ -f "/var/www/.git/ORIG_HEAD" ] && chmod 666 /var/www/.git/ORIG_HEAD 2>/dev/null || true
    [ -f "/var/www/.git/HEAD" ] && chmod 666 /var/www/.git/HEAD 2>/dev/null || true
    [ -f "/var/www/.git/index" ] && chmod 666 /var/www/.git/index 2>/dev/null || true

    # Make refs and logs writable (needed for Git operations)
    [ -d "/var/www/.git/refs" ] && chmod -R 755 /var/www/.git/refs 2>/dev/null || true
    [ -d "/var/www/.git/logs" ] && chmod -R 755 /var/www/.git/logs 2>/dev/null || true

    # Configure Git to trust this directory (must be done before any git commands)
    # We use --global here because Git refuses to run --local on an "unsafe" repo
    git config --global --add safe.directory /var/www 2>/dev/null || true
fi

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
