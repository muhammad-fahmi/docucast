# Docker Debugging Guide

Comprehensive guide for debugging and troubleshooting the DocuCast Docker environment.

## Table of Contents
1. [Viewing Logs](#viewing-logs)
2. [Executing Commands](#executing-commands)
3. [Database Debugging](#database-debugging)
4. [Redis Debugging](#redis-debugging)
5. [Queue Worker Debugging](#queue-worker-debugging)
6. [Reverb WebSocket Debugging](#reverb-websocket-debugging)
7. [Nginx Debugging](#nginx-debugging)
8. [Container Inspection](#container-inspection)
9. [Performance Profiling](#performance-profiling)
10. [Common Issues & Solutions](#common-issues--solutions)

---

## Viewing Logs

### Docker Compose Logs

```bash
# View all service logs
docker-compose logs

# Follow all logs in real-time
docker-compose logs -f

# View logs from specific service
docker-compose logs app
docker-compose logs nginx
docker-compose logs db
docker-compose logs redis
docker-compose logs reverb

# View last 50 lines
docker-compose logs --tail=50

# View logs with timestamps
docker-compose logs --timestamps

# Follow logs from multiple services
docker-compose logs -f app nginx reverb
```

### Application Logs

```bash
# View Laravel logs in real-time
docker-compose exec app tail -f storage/logs/laravel.log

# View supervisor logs (PHP-FPM and queue worker)
docker-compose exec app tail -f /var/log/supervisor/supervisord.log

# View queue worker logs specifically
docker-compose exec app tail -f /var/log/supervisor/queue-worker.log

# View PHP-FPM logs
docker-compose exec app tail -f /var/log/supervisor/php-fpm.log

# View schedule logs
docker-compose exec app tail -f /var/log/supervisor/schedule.log
```

### Nginx Logs

```bash
# View Nginx access logs
docker-compose exec nginx tail -f /var/log/nginx/access.log

# View Nginx error logs
docker-compose exec nginx tail -f /var/log/nginx/error.log

# View specific request errors
docker-compose exec nginx grep "error" /var/log/nginx/error.log
```

---

## Executing Commands

### Interactive Shell Access

```bash
# Access bash shell in app container
docker-compose exec app bash

# Access shell in nginx container
docker-compose exec nginx sh

# Access shell in database container
docker-compose exec db bash
```

### Running Artisan Commands

```bash
# Interactive Tinker console
docker-compose exec app php artisan tinker

# Run migrations
docker-compose exec app php artisan migrate

# Fresh migration with seed
docker-compose exec app php artisan migrate:fresh --seed

# Create new migration
docker-compose exec app php artisan make:migration create_table_name

# Create new model with migration
docker-compose exec app php artisan make:model Model -m

# Create new controller
docker-compose exec app php artisan make:controller ControllerName

# Publish package assets
docker-compose exec app php artisan vendor:publish --provider="Package\Provider"

# Run tests
docker-compose exec app php artisan test
docker-compose exec app php artisan test --filter=TestName --compact
```

### Running Composer Commands

```bash
# Install dependencies
docker-compose exec app composer install

# Update dependencies
docker-compose exec app composer update

# Require a new package
docker-compose exec app composer require vendor/package

# Remove a package
docker-compose exec app composer remove vendor/package

# Show installed packages
docker-compose exec app composer show
```

### Running npm Commands

```bash
# Install npm dependencies
docker-compose exec app npm install

# Build assets for production
docker-compose exec app npm run build

# Watch for changes during development
docker-compose exec app npm run dev

# Update npm packages
docker-compose exec app npm update
```

### Running PHP Directly

```bash
# Execute PHP code
docker-compose exec app php -r "echo phpinfo();"

# Check PHP version and extensions
docker-compose exec app php -v
docker-compose exec app php -m

# Run custom PHP script
docker-compose exec app php script.php
```

---

## Database Debugging

### Access PostgreSQL

```bash
# Connect to PostgreSQL from host machine
psql -h localhost -U postgres -d docucast

# Connect from app container
docker-compose exec app psql -h db -U postgres -d docucast
```

### PostgreSQL Commands

```sql
-- List all databases
\l

-- Connect to database
\c docucast

-- List tables
\dt

-- List all tables (including system tables)
\da

-- Describe table structure
\d table_name

-- Show table columns
\d+ table_name

-- List indexes
\di

-- List sequences
\ds

-- Show table privileges
\dp

-- Show all SQL statements for last query
\s

-- Export query results to file
\o filename.txt

-- Execute file
\i filename.sql

-- Exit psql
\q

-- Get help
\h
```

### Database Queries

```bash
# Get total database size
docker-compose exec db psql -U postgres -d docucast -c "SELECT pg_size_pretty(pg_database_size('docucast'));"

# Get table sizes
docker-compose exec db psql -U postgres -d docucast -c "SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) FROM pg_tables ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;"

# Count rows in all tables
docker-compose exec db psql -U postgres -d docucast -c "SELECT tablename, count(*) FROM pg_tables t1 CROSS JOIN LATERAL (SELECT count(*) FROM information_schema.tables WHERE table_name = tablename) t2 WHERE schemaname='public' GROUP BY tablename;"

# List active connections
docker-compose exec db psql -U postgres -c "SELECT pid, usename, state FROM pg_stat_activity;"

# Kill specific connection
docker-compose exec db psql -U postgres -c "SELECT pg_terminate_backend(<pid>);"
```

### Database Backup & Restore

```bash
# Create full database backup
docker-compose exec db pg_dump -U postgres docucast > backup_$(date +%Y%m%d_%H%M%S).sql

# Create compressed backup
docker-compose exec db pg_dump -U postgres docucast | gzip > backup.sql.gz

# Restore from backup
docker-compose exec -T db psql -U postgres docucast < backup.sql

# Restore from compressed backup
gunzip < backup.sql.gz | docker-compose exec -T db psql -U postgres docucast

# Clear database and restore
docker-compose exec db dropdb -U postgres docucast
docker-compose exec db createdb -U postgres docucast
docker-compose exec -T db psql -U postgres docucast < backup.sql
```

---

## Redis Debugging

### Access Redis CLI

```bash
# Connect to Redis
docker-compose exec redis redis-cli

# Connect with specific database
docker-compose exec redis redis-cli -n 0

# Execute command from host
docker-compose exec redis redis-cli PING
docker-compose exec redis redis-cli INFO
```

### Redis Commands

```bash
# Test connection
PING

# Get all keys
KEYS *

# Get keys matching pattern
KEYS cache:*

# Get value for key
GET key_name

# Delete specific key
DEL key_name

# Clear all keys in current database
FLUSHDB

# Clear all keys in all databases
FLUSHALL

# Get database info
INFO

# Get memory usage
INFO memory

# Get stats
INFO stats

# Monitor all Redis commands in real-time
MONITOR

# Check connection
CLIENT LIST

# Get key expiration time
TTL key_name

# Set key expiration
EXPIRE key_name 3600
```

### Clear Application Cache

```bash
# Clear Laravel cache
docker-compose exec app php artisan cache:clear

# Clear view cache
docker-compose exec app php artisan view:clear

# Clear route cache
docker-compose exec app php artisan route:clear

# Clear config cache
docker-compose exec app php artisan config:clear

# Clear all caches
docker-compose exec app php artisan cache:clear && \
docker-compose exec app php artisan view:clear && \
docker-compose exec app php artisan route:clear && \
docker-compose exec app php artisan config:clear

# Completely flush Redis
docker-compose exec redis redis-cli FLUSHDB
```

---

## Queue Worker Debugging

### Check Queue Status

```bash
# View supervisor status
docker-compose exec app supervisorctl status

# Get detailed status
docker-compose exec app supervisorctl status all

# Restart queue worker
docker-compose exec app supervisorctl restart laravel-queue-worker

# Stop queue worker
docker-compose exec app supervisorctl stop laravel-queue-worker

# Start queue worker
docker-compose exec app supervisorctl start laravel-queue-worker
```

### Queue Logs

```bash
# View queue worker logs
docker-compose logs -f app | grep queue

# View supervisor logs
docker-compose exec app tail -f /var/log/supervisor/queue-worker.log

# View supervisor error logs
docker-compose exec app tail -f /var/log/supervisor/queue-worker-error.log
```

### Process Queue Jobs

```bash
# Process single job and exit
docker-compose exec app php artisan queue:work redis --once

# Process jobs with specific parameters
docker-compose exec app php artisan queue:work redis --sleep=3 --tries=3 --timeout=90

# Monitor queue in real-time
docker-compose exec app php artisan queue:monitor

# Fail all jobs in queue
docker-compose exec app php artisan queue:flush

# Retry failed jobs
docker-compose exec app php artisan queue:retry all

# Forget failed jobs
docker-compose exec app php artisan queue:forget

# View failed jobs table
docker-compose exec app php artisan tinker
>>> DB::table('failed_jobs')->get();
```

---

## Reverb WebSocket Debugging

### Check Reverb Status

```bash
# View Reverb logs
docker-compose logs -f reverb

# Check if Reverb is listening
docker-compose exec app curl -I http://reverb:8080

# Check port 8080
docker-compose exec app netstat -tlnp | grep 8080
```

### Test WebSocket Connection

```bash
# From app container
docker-compose exec app curl -v http://reverb:8080

# Check Reverb configuration
docker-compose exec app php artisan tinker
>>> config('broadcasting.connections.reverb')

# Check broadcast credentials
docker-compose exec app php artisan tinker
>>> config('broadcasting.connections.reverb.app_id')
>>> config('broadcasting.connections.reverb.app_key')
```

### Test Broadcasting

```bash
# Create test event in Tinker
docker-compose exec app php artisan tinker
>>> event(new App\Events\TestEvent());
```

---

## Nginx Debugging

### Check Nginx Configuration

```bash
# Test nginx configuration
docker-compose exec nginx nginx -t

# Show active configuration
docker-compose exec nginx nginx -T

# List loaded modules
docker-compose exec nginx nginx -V

# Reload nginx config
docker-compose exec nginx nginx -s reload

# Test specific config
docker-compose exec nginx cat /etc/nginx/conf.d/default.conf
```

### Nginx Performance

```bash
# View active connections
docker-compose exec nginx ss -tulnp

# Check listening ports
docker-compose exec nginx netstat -tlnp

# View nginx process tree
docker-compose exec nginx ps aux | grep nginx
```

---

## Container Inspection

### Container Status

```bash
# List all containers
docker-compose ps

# Show container details
docker-compose ps -a

# Get container IP address
docker-compose exec app hostname -I

# View resource usage
docker stats

# View specific container stats
docker stats docucast-app
```

### Container Processes

```bash
# View running processes in app container
docker-compose exec app ps aux

# View network connections
docker-compose exec app netstat -tlnp

# View open files
docker-compose exec app lsof | head -50

# View CPU and memory usage
docker-compose top app
```

### Container Volumes

```bash
# List volumes
docker volume ls

# Inspect specific volume
docker volume inspect docucast_app-storage

# View volume disk usage
docker system df
```

---

## Performance Profiling

### Application Performance

```bash
# Check total response time
docker-compose exec app time php artisan tinker

# Profile memory usage
docker-compose exec app php -d memory_limit=512M artisan command

# Check slow queries in database
docker-compose exec db psql -U postgres -d docucast -c "\
SELECT mean_time, query FROM pg_stat_statements ORDER BY mean_time DESC LIMIT 10;"
```

### Docker Resource Usage

```bash
# View real-time resource usage
watch docker stats

# Export stats to JSON
docker stats --no-stream --format json

# Check container size
docker-compose exec app du -sh /var/www/html
```

---

## Common Issues & Solutions

### High Memory Usage

**Problem:** Container consuming excessive memory

**Solution:**
```bash
# Check PHP memory limit
docker-compose exec app php -i | grep "memory_limit"

# View memory-hungry processes
docker-compose exec app ps aux --sort=-%mem

# Restart container to free memory
docker-compose restart app

# Clear cache to free memory
docker-compose exec app php artisan cache:clear
```

### Slow Database Queries

**Problem:** Application running slowly

**Solution:**
```bash
# Enable PostgreSQL logging
docker-compose exec db psql -U postgres -d docucast -c "\
ALTER SYSTEM SET log_min_duration_statement = 1000;"

docker-compose restart db

# View slow query logs
docker-compose exec db tail -f /var/log/postgresql/postgresql.log
```

### Queue Jobs Stuck

**Problem:** Jobs not processing

**Solution:**
```bash
# Check queue status
docker-compose exec app supervisorctl status laravel-queue-worker

# Restart worker
docker-compose exec app supervisorctl restart laravel-queue-worker

# View failed jobs
docker-compose exec app php artisan tinker
>>> DB::table('failed_jobs')->get();

# Retry failed jobs
docker-compose exec app php artisan queue:retry all
```

### Network Issues

**Problem:** Containers can't communicate

**Solution:**
```bash
# Inspect network
docker network ls
docker network inspect docucast_internal

# Test container connectivity
docker-compose exec app ping db
docker-compose exec app ping redis
docker-compose exec app ping reverb
```

---

## Advanced Debugging

### Docker Debug Container

```bash
# Start debug container
docker run -it --rm \
  --network docucast_internal \
  nicolaka/netshoot bash

# Test DNS resolution
nslookup db
ping db
telnet db 5432
```

### Performance Profiling with XDebug

```bash
# Install XDebug extension
docker-compose exec app pecl install xdebug

# Configure XDebug
docker-compose exec app php -r "echo ini_get('xdebug.mode');"
```

### Memory Leak Detection

```bash
# Check process memory growth
watch -n 5 'docker-compose exec app ps aux | grep php'

# Use memory_get_peak_usage in code
docker-compose exec app php artisan tinker
>>> echo memory_get_peak_usage(true);
```

---

## Need More Help?

- Check [DOCKER_SETUP.md](DOCKER_SETUP.md) for setup instructions
- Review Laravel documentation: https://laravel.com/docs
- PostgreSQL docs: https://www.postgresql.org/docs/
- Redis commands: https://redis.io/commands/
