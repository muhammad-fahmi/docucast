FROM php:8.4-fpm-alpine as builder

WORKDIR /app

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

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    docker-php-ext-install -j$(nproc) pdo pdo_pgsql gd zip bcmath intl pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-dev --optimize-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN mkdir -p bootstrap/cache storage/logs storage/framework/cache storage/framework/sessions storage/framework/views && \
    echo "APP_KEY=base64:dummykeydummykeydummykeydummykeydummies=" > .env && \
    echo "APP_ENV=production" >> .env

RUN composer run-script post-autoload-dump && rm .env

ARG VITE_REVERB_APP_KEY=docucast-app-key
ARG VITE_REVERB_HOST=docucast.bionic-natura.cloud
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

RUN printf 'VITE_REVERB_APP_KEY=%s\nVITE_REVERB_HOST=%s\nVITE_REVERB_PORT=%s\nVITE_REVERB_SCHEME=%s\n' \
    "${VITE_REVERB_APP_KEY}" "${VITE_REVERB_HOST}" "${VITE_REVERB_PORT}" "${VITE_REVERB_SCHEME}" > .env

RUN npm run build && rm .env

FROM php:8.4-fpm-alpine

WORKDIR /app

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

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/nginx/hostinger.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY --from=builder /app /app

RUN mkdir -p /app/storage/logs \
    /app/bootstrap/cache \
    /var/log/supervisor && \
    chown -R www-data:www-data /app

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["web"]
