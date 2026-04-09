# Docker Guide

This document replaces the previous Docker quickstart, setup, and production deployment notes with one shorter source of truth.

## Overview

DocuCast runs as a multi-service Docker Compose stack:

- `app`: Laravel web app served through Nginx and PHP-FPM behind Traefik
- `reverb`: Laravel Reverb WebSocket server behind Traefik on the same public host, routed on `/app` and `/apps`
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
cp .env.docker .env
```

Update `.env` before starting the stack:

- set `APP_KEY`
- review `APP_URL`, `TRAEFIK_HOST`, and `REVERB_HOST`
- keep service ports on their internal defaults: `DB_PORT=5432`, `REDIS_PORT=6379`
- make sure the external Traefik network in `TRAEFIK_NETWORK` already exists on the server
- choose `TRAEFIK_ENTRYPOINTS=web` or `websecure` and set `TRAEFIK_TLS` accordingly
- change database credentials if needed
- configure mail settings for your environment
- review Reverb settings so the browser uses the Traefik endpoint instead of a direct container port

If you prefer to keep a separate file such as `.env.production`, start Compose with `docker-compose --env-file .env.production ...`. The helper script and the plain `docker-compose` commands in this guide use `.env` by default.

### 2. Build and start services

```bash
docker-compose build
docker-compose up -d
```

If the Traefik network does not exist yet:

```bash
docker network create traefik
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

- Web app: `http(s)://your configured TRAEFIK_HOST`
- Reverb WebSocket: proxied by Traefik on the same public host using the `/app` and `/apps` paths
- PostgreSQL: internal-only, reachable only from containers on the Docker network
- Redis: internal-only, reachable only from containers on the Docker network

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
- keep PostgreSQL and Redis internal-only unless you explicitly need external admin access
- route the web app and Reverb through Traefik instead of publishing container ports directly
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

If you run behind Traefik, make sure `APP_URL`, `TRAEFIK_HOST`, `REVERB_HOST`, `REVERB_PORT`, and `REVERB_SCHEME` match the public endpoint seen by the browser.

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
