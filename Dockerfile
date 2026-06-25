# Multi-stage Dockerfile for Laravel 12 application with PHP 8.4-FPM
# Optimized for multi-environment deployments (local, staging, production)

# ==========================================
# Stage 1a: Vendor dev dependencies
# Purpose: Install development dependencies for Laravel
# In this point not copy source code, only composer.json and composer.lock
# if use this stage for local, you must run npm install locally or via a separate container for hot-reloading.
# This is a good practice for multi-environment deployments
# ==========================================
FROM composer:2 AS vendor-dev
WORKDIR /app
COPY composer.json composer.lock* ./
# Install dev dependencies for Laravel
RUN --mount=type=cache,target=/tmp/cache \
    composer install \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

# ==========================================
# Stage 1b: Vendor prod dependencies
# Purpose: Install production dependencies for Laravel
# In this point not copy source code, only composer.json and composer.lock
# if use this stage for local, you must run npm install locally or via a separate container for hot-reloading.
# This is a good practice for multi-environment deployments
# ==========================================
FROM composer:2 AS vendor-prod
WORKDIR /app
COPY composer.json composer.lock* ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install \
    # Install only production dependencies
    --no-dev \
    # Disable scripts from running, they will be run later
    --no-scripts \
    # Install dependencies in production mode
    --prefer-dist \
    # Ignore platform requirements, they will be handled by the Docker environment
    --ignore-platform-reqs

# ==========================================
# Stage 2: Frontend assets compilation
# Purpose: Compile frontend assets for Laravel
# This stage is used to compile frontend assets for Laravel
# The assets are compiled in this stage and then copied to the final image
# ==========================================
FROM node:22-alpine AS frontend
WORKDIR /app

# Copy package.json and package-lock.json to the container
COPY package.json package-lock.json* ./
# Install node dependencies for Laravel
RUN --mount=type=cache,target=/root/.npm \
    # Set alpine error configuration
    # set -eux is a shell command that does the following:
    # - e: Exit immediately if a command exits with a non-zero status.
    # - u: Treat unset variables as an error and exit immediately.
    # - x: Print commands and their arguments as they are executed.
    set -eux; \
    # Set the npm configuration for Laravel
    npm config set fund false; \
    npm config set audit false; \
    # Install node dependencies for Laravel
    if [ -f package-lock.json ]; then \
    npm ci; \
    else \
    npm install --include=dev; \
    fi;

COPY resources resources
COPY public public
COPY vite.config.js package.json ./

ARG FRONTEND_APP_NAME
ARG FRONTEND_REVERB_CLIENT
ARG FRONTEND_REVERB_HOST
ARG FRONTEND_REVERB_PORT
ARG FRONTEND_REVERB_SCHEME

RUN VITE_APP_NAME="${FRONTEND_APP_NAME}" \
    VITE_REVERB_APP_KEY="${FRONTEND_REVERB_CLIENT}" \
    VITE_REVERB_HOST="${FRONTEND_REVERB_HOST}" \
    VITE_REVERB_PORT="${FRONTEND_REVERB_PORT}" \
    VITE_REVERB_SCHEME="${FRONTEND_REVERB_SCHEME}" \
    npm run build

# ==========================================
# Stage 3: App Base (PHP-FPM runtime)
# ==========================================
FROM php:8.4-fpm-alpine AS app-base
WORKDIR /var/www/html

# Install runtime packages and PHP extensions for Laravel, PostgreSQL, Redis, and Reverb
RUN set -eux; \
    apk add --no-cache \
    bash \
    fcgi \
    git \
    $PHPIZE_DEPS \
    icu-libs \
    icu-dev \
    libpq-dev \
    libzip \
    libzip-dev \
    linux-headers \
    netcat-openbsd \
    oniguruma-dev \
    postgresql-client \
    supervisor \
    unzip \
    zip; \
    docker-php-ext-install -j$(nproc) \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    zip; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers oniguruma-dev; \
    rm -rf /var/cache/apk/* /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chown=www-data:www-data docker/scripts/start-container.sh /usr/local/bin/start-container.sh
COPY --chown=www-data:www-data docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/start-container.sh \
    && mkdir -p /opt/docucast-source \
    && mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data /var/log/supervisor

# Health check
HEALTHCHECK --interval=10s --timeout=3s --start-period=60s --retries=3 \
    CMD cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

EXPOSE 9000
CMD ["/usr/local/bin/start-container.sh"]

# ==========================================
# Stage 4: Local / Development
# ==========================================
FROM app-base AS local
# Copy dev vendor
COPY --chown=www-data:www-data --from=vendor-dev /app/vendor ./vendor
# We don't copy public/build or optimize here because we expect source code to be mounted
# and Vite/npm to be run locally or via a separate container for hot-reloading.
# But we copy the rest of the app in case it's not mounted.
COPY --chown=www-data:www-data . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R ug+rwx bootstrap/cache storage \
    && cp -a /var/www/html/. /opt/docucast-source/ \
    && chown -R www-data:www-data /opt/docucast-source

# ==========================================
# Stage 5: Staging
# ==========================================
FROM app-base AS staging
# Configure OPcache for performance
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.jit=tracing'; \
    echo 'opcache.jit_buffer_size=100M'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor-prod /app/vendor ./vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R ug+rwx bootstrap/cache storage \
    && php artisan optimize || true \
    && php artisan filament:optimize || true \
    && cp -a /var/www/html/. /opt/docucast-source/ \
    && chown -R www-data:www-data /opt/docucast-source

# ==========================================
# Stage 6: Testing
# ==========================================
FROM staging AS testing
# Identical to staging, but explicit target for clarity and potential future divergence

# ==========================================
# Stage 7: Production
# ==========================================
FROM staging AS production
# Identical to staging, but explicit target for clarity and potential future divergence

# ==========================================
# Stage 8: Nginx web server
# ==========================================
FROM nginx:1.27-alpine AS nginx-runtime

WORKDIR /var/www/html

# Copy public assets and Nginx configuration
COPY public ./public
COPY --from=frontend /app/public/build ./public/build
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

RUN mkdir -p /var/www/html/storage/app/public \
    && ln -sf /var/www/html/storage/app/public /var/www/html/public/storage \
    && chown -R nginx:nginx /var/www/html

# Expose HTTP port (internal communication with Traefik)
EXPOSE 80

# Health check
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://localhost/health || exit 1
