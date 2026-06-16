#!/bin/bash
# Start Container Script
# Handles application initialization and startup for PHP-FPM container
# This script runs on container start and handles migrations, seeders, and Supervisor

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  DocuCast Laravel Application - Container Startup Script      ║"
echo "╚════════════════════════════════════════════════════════════════╝"

# ==========================================
# Application Directory
# ==========================================
cd /var/www/html

# ==========================================
# Generate Application Key (if not exists)
# ==========================================
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ]; then
        echo "📝 No .env file found, creating a new one to store APP_KEY..."
        echo "APP_KEY=" > .env
    fi
    echo "🔑 Generating application key..."
    php artisan key:generate --no-interaction || true
fi

# ==========================================
# Wait for Database to be Ready
# ==========================================
echo "⏳ Waiting for database to be ready..."
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-5432}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_DATABASE=${DB_DATABASE:-docucast}

# Retry connection for up to 60 seconds
RETRY=0
MAX_RETRY=60

while [ $RETRY -lt $MAX_RETRY ]; do
    if pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" 2>/dev/null; then
        echo "✅ Database is ready!"
        break
    fi
    RETRY=$((RETRY + 1))
    echo "   Attempt $RETRY/$MAX_RETRY - Database not ready yet, retrying in 1 second..."
    sleep 1
done

if [ $RETRY -eq $MAX_RETRY ]; then
    echo "❌ Database failed to start within 60 seconds"
    exit 1
fi

# ==========================================
# Wait for Redis to be Ready
# ==========================================
echo "⏳ Waiting for Redis to be ready..."
REDIS_HOST=${REDIS_HOST:-localhost}
REDIS_PORT=${REDIS_PORT:-6379}
RETRY=0

while [ $RETRY -lt $MAX_RETRY ]; do
    if nc -z "$REDIS_HOST" "$REDIS_PORT" 2>/dev/null; then
        echo "✅ Redis is ready!"
        break
    fi
    RETRY=$((RETRY + 1))
    echo "   Attempt $RETRY/$MAX_RETRY - Redis not ready yet, retrying in 1 second..."
    sleep 1
done

if [ $RETRY -eq $MAX_RETRY ]; then
    echo "⚠️  Warning: Redis is not available, continuing anyway..."
fi

# ==========================================
# Directory Setup
# ==========================================
echo "📁 Creating required directories..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# ==========================================
# File Permissions
# ==========================================
echo "🔐 Setting file permissions..."
owner_group=""
if id -u www-data >/dev/null 2>&1; then
    owner_group="www-data:www-data"
elif id -u nginx >/dev/null 2>&1; then
    owner_group="nginx:nginx"
fi

if [ -n "$owner_group" ]; then
    # Target only framework, logs, and bootstrap/cache to avoid slow recursion on user uploads
    chown -R "$owner_group" storage/framework storage/logs bootstrap/cache || true
fi
chmod -R ug+rwx storage/framework storage/logs bootstrap/cache || true

# ==========================================
# Storage Symlink
# ==========================================
if [ ! -L public/storage ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link --force 2>/dev/null || true
fi

# ==========================================
# Clear Caches & Package Manifests
# ==========================================
echo "🧹 Clearing application caches and stale manifests..."
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes.php
php artisan optimize:clear --no-interaction || true

# ==========================================
# Run Database Migrations
# ==========================================
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction

# ==========================================
# Run Database Seeders (Optional)
# ==========================================
if [ "${SEED_DATABASE}" == "true" ]; then
    echo "🌱 Running database seeders..."
    php artisan db:seed --force --no-interaction
else
    echo "⏭️  Skipping database seeders (set SEED_DATABASE=true to enable)"
fi

# ==========================================
# Optimize / Clear Cache based on environment
# ==========================================
if [ "${APP_ENV}" = "local" ] || [ "${APP_ENV}" = "testing" ]; then
    echo "🧹 Clearing application caches for local development..."
    php artisan config:clear --no-interaction || true
    php artisan route:clear --no-interaction || true
    php artisan view:clear --no-interaction || true
    php artisan cache:clear --no-interaction || true
else
    echo "⚡ Optimizing application for production..."
    php artisan optimize --no-interaction || true
fi

# ==========================================
# Start Supervisor (manages PHP-FPM and Queue Worker)
# ==========================================
echo "🚀 Starting Supervisor to manage PHP-FPM and queue worker..."
echo "════════════════════════════════════════════════════════════════"

# Start Supervisor in foreground to keep container running
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
