# Docker Guide

This document replaces the previous Docker quickstart, setup, and production deployment notes with one shorter source of truth.

## Overview

DocuCast runs as a multi-service Docker Compose stack:

- `app`: Laravel web app served through Nginx and PHP-FPM on port `80`
- `reverb`: Laravel Reverb WebSocket server on port `8080`
- `queue`: Horizon worker, or `queue:work` when Horizon is unavailable
- `postgres`: PostgreSQL 16 database
- `redis`: Redis cache, session, and queue backend

The image is built from the local `Dockerfile` and reused by the `app`, `reverb`, and `queue` services.

## Prerequisites

- Docker 20.10+
- Docker Compose 2+
- At least 4 GB RAM available to Docker

## Local Setup

### 1. Prepare environment

```bash
cp .env.docker .env.production
```

Update `.env.production` before starting the stack:

- set `APP_KEY`
- review `APP_URL`
- change database credentials if needed
- configure mail settings for your environment
- review Reverb settings if you will access it outside localhost

### 2. Build and start services

```bash
docker-compose build
docker-compose up -d
```

### 3. Initialize the database

```bash
docker-compose exec -T app php artisan migrate --force
```

Optional seed:

```bash
docker-compose exec -T app php artisan db:seed
```

### 4. Access services

- Web app: `http://localhost`
- Reverb: `http://localhost:8080`
- PostgreSQL: `localhost:5432`
- Redis: `localhost:6379`

## Useful Commands

### Docker Compose

```bash
docker-compose ps
docker-compose logs -f
docker-compose logs -f app
docker-compose logs -f reverb
docker-compose logs -f queue
docker-compose exec app bash
docker-compose exec app php artisan tinker
docker-compose down
```

### Helper script

The repo includes `docker-manage.sh` for common tasks:

```bash
./docker-manage.sh build
./docker-manage.sh up
./docker-manage.sh migrate
./docker-manage.sh logs app
./docker-manage.sh db-backup
./docker-manage.sh help
```

## Runtime Behavior

The container entrypoint supports these commands:

- `web`: waits for PostgreSQL, runs migrations, caches config and routes, then starts Nginx and PHP-FPM
- `reverb`: waits for PostgreSQL, caches config, then starts `php artisan reverb:start`
- `queue`: waits for PostgreSQL, caches config, then starts Horizon when available, otherwise `php artisan queue:work`
- `artisan`: runs arbitrary Artisan commands

## Production Notes

For production, keep these points in mind:

- use a real `APP_KEY`
- set `APP_ENV=production` and `APP_DEBUG=false`
- use strong PostgreSQL and Redis credentials
- terminate TLS at a reverse proxy such as Nginx or Traefik
- expose only the ports you actually need
- persist database and application volumes
- back up PostgreSQL regularly
- pull and redeploy the image when publishing a new version

Typical production flow:

```bash
docker build -t your-registry/docucast:1.0.0 .
docker push your-registry/docucast:1.0.0
docker-compose up -d
docker-compose exec -T app php artisan migrate --force
```

If you run behind a reverse proxy, make sure `APP_URL`, `REVERB_HOST`, `REVERB_PORT`, and `REVERB_SCHEME` match the public endpoint.

## Troubleshooting

### Services fail to start

```bash
docker-compose ps
docker-compose logs --tail=100 app
docker-compose logs --tail=100 postgres
docker-compose logs --tail=100 redis
```

### Database connection issues

```bash
docker-compose exec -T postgres pg_isready -U ${DB_USERNAME:-postgres}
docker-compose exec -T app php artisan migrate --force
```

### Clear Laravel caches

```bash
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan route:clear
docker-compose exec -T app php artisan view:clear
```

### Rebuild after dependency or asset changes

```bash
docker-compose build --no-cache
docker-compose up -d
```

## Source Of Truth

When Docker behavior and documentation differ, trust these files first:

- `docker-compose.yml`
- `Dockerfile`
- `docker/entrypoint.sh`
- `docker-manage.sh`
