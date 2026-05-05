# Multi-stage Dockerfile for Laravel 12 application with PHP 8.4-FPM
# Optimized for development with Traefik, PostgreSQL, Redis, and Reverb

# ==========================================
# Stage 1: Vendor dependencies installation
# ==========================================
FROM php:8.4-cli-alpine AS vendor

WORKDIR /app

RUN apk add --no-cache \
    icu-libs \
    icu-dev \
    libzip \
    libzip-dev \
    linux-headers \
    unzip \
    zip \
    && docker-php-ext-install intl pcntl zip \
    && apk del icu-dev libzip-dev linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

# ==========================================
# Stage 2: Frontend assets compilation
# ==========================================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY public public
COPY vite.config.js package.json ./

ARG VITE_APP_NAME
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME

ENV VITE_APP_NAME=${VITE_APP_NAME}
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST}
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT}
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN npm run build

# ==========================================
# Stage 3: PHP-FPM runtime with queue worker
# ==========================================
FROM php:8.4-fpm-alpine AS app-runtime

WORKDIR /var/www/html

# Install runtime packages and PHP extensions for Laravel, PostgreSQL, Redis, and Reverb
RUN apk add --no-cache \
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
    zip \
    && docker-php-ext-install \
    intl \
    pcntl \
    pdo_pgsql \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers oniguruma-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/scripts/start-container.sh /usr/local/bin/start-container
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/start-container \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/log/supervisor \
    && chmod -R ug+rwx storage bootstrap/cache

# Health check
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

# Expose PHP-FPM port (internal communication)
EXPOSE 9000

# Start Supervisor to manage PHP-FPM and queue worker processes
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ==========================================
# Stage 4: Nginx web server
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
