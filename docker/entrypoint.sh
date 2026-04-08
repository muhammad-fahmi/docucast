#!/bin/bash

set -e

# Wait for database to be ready
wait_for_db() {
    echo "Waiting for database to be ready..."
    max_attempts=30
    attempt=1

    while [ $attempt -le $max_attempts ]; do
        if php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:5432).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; then
            echo "Database is ready!"
            return 0
        fi
        echo "Attempt $attempt/$max_attempts: Database not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "Database failed to become ready after $max_attempts attempts"
    return 1
}

# Run migrations
run_migrations() {
    echo "Running migrations..."
    php artisan migrate --force
}

# Cache config
cache_config() {
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
}

case "${1:-web}" in
    web)
        echo "Starting web server..."
        wait_for_db
        run_migrations
        cache_config

        # Start supervisor (manages nginx + php-fpm)
        exec /usr/bin/supervisord -c /etc/supervisord.conf
        ;;

    reverb)
        echo "Starting Reverb server..."
        wait_for_db
        cache_config

        # Start Reverb WebSocket server
        if php -m | grep -qi "^pcntl$"; then
            exec php artisan reverb:start --host=0.0.0.0 --port=8080
        fi

        echo "pcntl extension is not available; starting Reverb without signal trapping"
        exec php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
        ;;

    queue)
        echo "Starting Queue Worker (Horizon)..."
        wait_for_db
        cache_config

        # Start Horizon when installed, otherwise fall back to queue worker.
        if php artisan list --raw 2>/dev/null | grep -q "^horizon$"; then
            exec php artisan horizon
        fi

        echo "Horizon is not installed; starting queue worker instead"
        exec php artisan queue:work --sleep=3 --tries=3 --timeout=90
        ;;

    artisan)
        # Allow running arbitrary artisan commands
        exec php artisan "${@:2}"
        ;;

    *)
        # Allow running any command
        exec "$@"
        ;;
esac
