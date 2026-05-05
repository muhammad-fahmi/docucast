# Docker Setup Guide

Complete guide for running the DocuCast Laravel application in Docker with development environment.

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Initial Setup](#initial-setup)
3. [Running the Application](#running-the-application)
4. [Stopping the Application](#stopping-the-application)
5. [Common Commands](#common-commands)
6. [Database Management](#database-management)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before you begin, ensure you have the following installed:
- **Docker** (version 20.10+)
- **Docker Compose** (version 2.0+)
- **Git** (for cloning the repository)

### Verify Installation

```bash
docker --version
docker-compose --version
```

---

## Initial Setup

### 1. Clone Repository and Navigate to Project

```bash
git clone <repository-url> docucast
cd docucast
```

### 2. Create Environment Configuration

Copy the example environment file and configure for development:

```bash
cp .env.example .env
```

Edit `.env` if needed (most defaults are suitable for local development):
- `APP_KEY` - Set to a random base64 string (or use `php artisan key:generate` after containers start)
- `DB_PASSWORD` - Keep as `postgres` for development
- `REDIS_PASSWORD` - Keep as empty/null

### 3. Build Docker Images

Build the Docker images for the first time:

```bash
docker-compose build
```

This will:
- Build the PHP-FPM image with all Laravel dependencies
- Build the Nginx image
- Pull PostgreSQL 18 image
- Pull Redis image
- Pull Traefik image

**Build time:** 5-10 minutes depending on your internet speed.

---

## Running the Application

### Start All Services

Start all containers in the background:

```bash
docker-compose up -d
```

This starts:
- **Traefik** - Reverse proxy on port 80
- **App (PHP-FPM)** - Laravel application server
- **Nginx** - Web server
- **PostgreSQL 18** - Database
- **Redis** - Cache and session storage
- **Reverb** - WebSocket server for real-time notifications

### View Startup Progress

Watch the startup logs:

```bash
docker-compose logs -f app
```

Wait until you see messages like:
```
✅ Database is ready!
✅ Redis is ready!
🚀 Starting Supervisor...
```

### Access the Application

Once started, access the application at:
- **Web Application:** http://localhost
- **Traefik Dashboard:** http://localhost/dashboard (if enabled in config)

### Wait for Initial Setup

The first start will take 1-2 minutes because:
- Database migrations run automatically
- Asset compilation happens
- Application cache is generated

---

## Stopping the Application

### Stop All Containers

```bash
docker-compose down
```

This stops all containers but keeps the data volumes intact.

### Stop and Remove Everything

To remove containers AND volumes (⚠️ **deletes database data**):

```bash
docker-compose down -v
```

### Pause Containers (Keep Running)

```bash
docker-compose pause
docker-compose unpause
```

---

## Common Commands

### View Logs

```bash
# View all logs
docker-compose logs

# Follow logs from app container
docker-compose logs -f app

# View logs from specific service
docker-compose logs nginx
docker-compose logs reverb
docker-compose logs db

# View last 100 lines
docker-compose logs --tail=100
```

### Execute Artisan Commands

```bash
# Run a single command
docker-compose exec app php artisan tinker
docker-compose exec app php artisan migrate:fresh --seed

# Access bash shell in app container
docker-compose exec app bash

# Run composer commands
docker-compose exec app composer require package-name
```

### Rebuild Assets

```bash
# Rebuild Vite assets
docker-compose exec app npm run build

# Watch for changes (development)
docker-compose exec app npm run dev
```

### Run Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test
docker-compose exec app php artisan test --filter=TestName

# Run with compact output
docker-compose exec app php artisan test --compact
```

---

## Database Management

### Access PostgreSQL from Host

```bash
# Connect to PostgreSQL database
psql -h localhost -U postgres -d docucast

# From within the container
docker-compose exec db psql -U postgres -d docucast
```

### View Database

```bash
# List tables
\dt

# Exit psql
\q
```

### Reset Database

```bash
# Run fresh migrations (WARNING: deletes all data)
docker-compose exec app php artisan migrate:fresh

# With seeders
docker-compose exec app php artisan migrate:fresh --seed
```

### Backup Database

```bash
# Create database dump
docker-compose exec db pg_dump -U postgres docucast > backup.sql

# Restore from dump
docker-compose exec -T db psql -U postgres docucast < backup.sql
```

---

## Redis Management

### Access Redis CLI

```bash
docker-compose exec redis redis-cli

# Common Redis commands
PING           # Test connection
KEYS *         # List all keys
FLUSHDB        # Clear all data
INFO           # Server information
```

### Clear Redis Cache

```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
docker-compose exec app php artisan config:clear
```

---

## Queue and Reverb

### Check Queue Worker Status

```bash
# View queue worker logs
docker-compose logs -f app | grep "queue"

# Monitor queue jobs
docker-compose exec app php artisan queue:monitor
```

### Process Queued Jobs Manually

```bash
docker-compose exec app php artisan queue:work redis --once
```

### Test Reverb WebSocket Connection

```bash
# Access Reverb logs
docker-compose logs -f reverb

# Check if Reverb is running
curl -I http://localhost:8080
```

---

## Performance & Optimization

### View Container Resource Usage

```bash
docker stats
```

### Prune Unused Docker Resources

```bash
# Remove unused images, containers, and volumes
docker system prune -a
```

---

## Troubleshooting

### Application Won't Start

**Problem:** Container exits immediately or hangs on startup.

**Solution:**
```bash
# Check logs for errors
docker-compose logs app

# If database failed to start:
docker-compose logs db

# Restart all services
docker-compose restart
```

### Database Connection Error

**Problem:** "could not connect to database"

**Solution:**
```bash
# Check if database container is running
docker-compose ps

# View database logs
docker-compose logs db

# Restart database container
docker-compose restart db

# Re-run migrations
docker-compose exec app php artisan migrate
```

### Redis Connection Error

**Problem:** "Redis connection refused"

**Solution:**
```bash
# Check if Redis is running
docker-compose logs redis

# Clear Redis
docker-compose exec redis redis-cli FLUSHDB

# Restart Redis
docker-compose restart redis
```

### Port Already in Use

**Problem:** "Address already in use" on port 80

**Solution:**
```bash
# Find and stop process using port 80
sudo lsof -i :80
sudo kill -9 <PID>

# Or use different port (edit docker-compose.yml)
# Change: ports: - "80:80"
# To:     ports: - "8080:80"
```

### Assets Not Loading

**Problem:** CSS/JS files return 404

**Solution:**
```bash
# Rebuild assets
docker-compose exec app npm run build

# Verify assets exist
docker-compose exec app ls -la public/build

# Clear Nginx cache
docker-compose restart nginx
```

### Queue Worker Not Processing Jobs

**Problem:** Jobs remain in queue

**Solution:**
```bash
# Check queue worker status
docker-compose exec app supervisorctl status

# Restart queue worker
docker-compose exec app supervisorctl restart laravel-queue-worker

# View queue logs
docker-compose logs app | grep queue-worker

# Process jobs manually
docker-compose exec app php artisan queue:work redis --once
```

### Permission Denied Errors

**Problem:** "Permission denied" in storage directory

**Solution:**
```bash
# Fix permissions in storage directory
docker-compose exec app chmod -R ug+rwx storage bootstrap/cache

# Rebuild container
docker-compose rebuild app
docker-compose up -d
```

---

## Environment Variables

Key environment variables for development (in `.env`):

```bash
APP_ENV=local                    # Development environment
APP_DEBUG=true                   # Enable debug mode
DB_HOST=db                       # Database container name
REDIS_HOST=redis                 # Redis container name
REVERB_HOST=reverb               # Reverb container name
QUEUE_CONNECTION=redis           # Use Redis for queues
CACHE_STORE=redis                # Use Redis for cache
```

For production modifications, refer to [Laravel Configuration Documentation](https://laravel.com/docs/12/configuration).

---

## Next Steps

1. Review [DOCKER_DEBUGGING.md](DOCKER_DEBUGGING.md) for debugging tips
2. Set up your first user: `docker-compose exec app php artisan tinker`
3. Run tests: `docker-compose exec app php artisan test`
4. Configure Reverb for WebSocket notifications

---

## Need Help?

- Check logs: `docker-compose logs <service>`
- See debugging guide: [DOCKER_DEBUGGING.md](DOCKER_DEBUGGING.md)
- Verify containers are running: `docker-compose ps`
