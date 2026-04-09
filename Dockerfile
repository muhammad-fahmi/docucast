# Stage 1: Builder
FROM php:8.4-fpm-alpine as builder

WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
    build-base \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    icu-dev \
    git \
    curl \
    unzip \
    nodejs \
    npm \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    docker-php-ext-install -j$(nproc) pdo pdo_pgsql gd zip bcmath intl pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files and install dependencies (no scripts until source is available)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-dev --optimize-autoloader

# Copy package files for npm layer caching
COPY package.json package-lock.json ./
RUN npm ci

# Copy full application source
COPY . .

# Ensure required directories exist and create a minimal .env for build-time artisan calls
RUN mkdir -p bootstrap/cache storage/logs storage/framework/cache storage/framework/sessions storage/framework/views && \
    echo "APP_KEY=base64:dummykeydummykeydummykeydummykeydummies=" > .env && \
    echo "APP_ENV=production" >> .env

# Run post-install scripts now that full source is available
RUN composer run-script post-autoload-dump && rm .env

# Build-time arguments for Vite frontend bundle (baked into JS at build time)
ARG VITE_REVERB_APP_KEY=docucast-app-key
ARG VITE_REVERB_HOST=docucast.bionic-natura.cloud
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

# Create .env with Vite variables so they are baked into the JS bundle
RUN printf 'VITE_REVERB_APP_KEY=%s\nVITE_REVERB_HOST=%s\nVITE_REVERB_PORT=%s\nVITE_REVERB_SCHEME=%s\n' \
    "${VITE_REVERB_APP_KEY}" "${VITE_REVERB_HOST}" "${VITE_REVERB_PORT}" "${VITE_REVERB_SCHEME}" > .env

# Build frontend assets
RUN npm run build && rm .env

# Stage 2: Runtime
FROM php:8.4-fpm-alpine

WORKDIR /app

# Install runtime dependencies only
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    postgresql-libs \
    icu-libs \
    nginx \
    supervisor \
    curl \
    bash

# Copy compiled PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy PHP-FPM config
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini

# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisor config
COPY docker/supervisord.conf /etc/supervisord.conf

# Copy app from builder
COPY --from=builder /app /app

# Create necessary directories
RUN mkdir -p /app/storage/logs \
    /app/bootstrap/cache \
    /var/log/supervisor && \
    chown -R www-data:www-data /app

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose ports
EXPOSE 80 8080

# Default entrypoint and command
ENTRYPOINT ["/entrypoint.sh"]
CMD ["web"]
