#!/bin/sh
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo "${RED}[ERROR]${NC} $1"
}

# Generate app key only if missing
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "" ]; then
    log_info "Generating application key..."
    php artisan key:generate --force
fi

# Wait for database to be ready (for non-SQLite databases)
if [ "${DB_CONNECTION}" != "sqlite" ] && [ -n "${DB_CONNECTION}" ]; then
    log_info "Waiting for database connection..."
    until php artisan migrate:status > /dev/null 2>&1; do
        log_warn "Database is unavailable - sleeping..."
        sleep 2
    done
    log_info "Database is ready!"
fi

# Create and set permissions for all Laravel directories
log_info "Setting up Laravel directories and permissions..."

# Define directories that need proper permissions
STORAGE_DIR="/var/www/storage"
BOOTSTRAP_CACHE_DIR="/var/www/bootstrap/cache"
DATABASE_DIR="/var/www/database"

# Create all required storage subdirectories
mkdir -p "${STORAGE_DIR}/app/public"
mkdir -p "${STORAGE_DIR}/framework/cache/data"
mkdir -p "${STORAGE_DIR}/framework/sessions"
mkdir -p "${STORAGE_DIR}/framework/testing"
mkdir -p "${STORAGE_DIR}/framework/views"
mkdir -p "${STORAGE_DIR}/logs"

# Create bootstrap cache directory
mkdir -p "${BOOTSTRAP_CACHE_DIR}"

# Create database directory
mkdir -p "${DATABASE_DIR}"

# Set ownership efficiently (www-data is the web server user in Alpine)
log_info "Setting ownership to www-data:www-data..."
chown -R www-data:www-data "${STORAGE_DIR}" "${BOOTSTRAP_CACHE_DIR}" "${DATABASE_DIR}"

# Set permissions more efficiently
# Directories: 775 (rwxrwxr-x) - allows read/write/execute for owner/group
# Files: 664 (rw-rw-r--) - allows read/write for owner/group
log_info "Setting directory permissions (775) and file permissions (664)..."
find "${STORAGE_DIR}" "${BOOTSTRAP_CACHE_DIR}" "${DATABASE_DIR}" -type d -exec chmod 775 {} \;
find "${STORAGE_DIR}" "${BOOTSTRAP_CACHE_DIR}" "${DATABASE_DIR}" -type f -exec chmod 664 {} \;

# Create storage symlink for public storage (if it doesn't exist)
if [ ! -L /var/www/public/storage ]; then
    log_info "Creating storage symlink..."
    php artisan storage:link || log_warn "Storage symlink already exists or could not be created"
fi

# Handle SQLite database creation
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    DB_FILE="${DATABASE_DIR}/database.sqlite"
    if [ ! -f "${DB_FILE}" ]; then
        log_info "Creating SQLite database..."
        touch "${DB_FILE}"
        chown www-data:www-data "${DB_FILE}"
        chmod 664 "${DB_FILE}"
    fi
fi

# Clear previous optimizations to ensure fresh state
log_info "Clearing previous optimizations..."
php artisan optimize:clear || log_warn "Could not clear optimizations (may not exist yet)"

# Run migrations
log_info "Running database migrations..."
php artisan migrate --force

# Optimize Composer autoloader
log_info "Optimizing Composer autoloader..."
composer dump-autoload --optimize --no-interaction --quiet

# Cache Laravel optimizations (config, routes, views)
log_info "Caching Laravel optimizations..."
php artisan optimize || log_warn "Optimization caching failed"

log_info "Entrypoint setup completed successfully!"

# Start PHP-FPM
exec php-fpm
