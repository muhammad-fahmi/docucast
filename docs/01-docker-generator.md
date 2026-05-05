# Intent
Create a Dockerfile and docker-compose.yml to run the Laravel application in a containerized environment with Nginx, PHP-FPM, PostgreSQL, Redis, Reverb WebSocket server, and Traefik reverse proxy. This will enable consistent development and deployment across environments.

# Context
The application is a Laravel 12 web application built with:
- **Backend:** PHP 8.4 with Laravel 12
- **Frontend:** Vite for asset bundling
- **Database:** PostgreSQL 18 with persistent volume storage
- **Cache & Session:** Redis (latest)
- **Real-time:** Reverb WebSocket server for notifications
- **Web Server:** Nginx with Traefik reverse proxy
- **Task Scheduling:** Laravel Queue with background processing
- **Architecture:** Separate PHP-FPM and Nginx containers for scalability

# Technical Specifications

## Services Architecture
- **app**: PHP 8.4-FPM container with Laravel application, Composer/NPM dependencies, and queue worker as background process
- **nginx**: Nginx web server container (separate from PHP-FPM for scalability)
- **db**: PostgreSQL 18 with persistent volume at `/var/lib/postgresql/data`
- **redis**: Latest Redis version with port 6380 exposed for WebSocket access
- **reverb**: Separate Laravel Reverb service for WebSocket notifications (best practice)
- **traefik**: Reverse proxy with Docker provider for routing traffic

## Build Configuration
- Composer dependencies installed during build stage
- NPM dependencies installed and Vite assets built during build stage
- Database migrations run automatically on container startup
- Queue worker runs as background process using Supervisor in app container
- Development setup with localhost as domain

## Constraints
- PHP-FPM and Nginx in separate containers, communicating via Docker network
- Traefik acts as reverse proxy; application ports not exposed directly
- PostgreSQL volume ensures data persistence across container restarts
- Redis port 6380 exposed for external WebSocket access
- Queue worker managed by Supervisor for reliability
- Reverb runs as dedicated service for WebSocket notifications
- Volume mount strategy for code, storage, and database persistence

# Acceptance Criteria
- Dockerfile creates PHP 8.4-FPM image with all Laravel extensions, Composer, Node.js, and Supervisor
- Nginx container serves as reverse proxy to PHP-FPM
- docker-compose.yml defines all services with correct networking and volume configuration
- PostgreSQL 18 container with persistent volume for data storage
- Redis container with port 6380 exposed
- Reverb container running WebSocket server
- Traefik container routing traffic to Nginx
- Application starts with migrations automatically applied
- Queue worker runs as background process in app container
- All services communicate via Docker network
- Development environment uses localhost domain with Traefik labels
- Supervisor manages PHP-FPM and queue worker processes in app container

# Format
- **Dockerfile**: Main PHP-FPM application image with all dependencies (at root)
- **docker-compose.yml**: Orchestration file defining all services (at root)
- **docker/nginx/nginx.conf**: Nginx configuration for PHP-FPM upstream (in docker/nginx/)
- **docker/nginx/default.conf**: Nginx server block configuration (in docker/nginx/)
- **docker/supervisor/supervisord.conf**: Supervisor configuration for queue worker (in docker/supervisor/)
- All files include detailed comments explaining configuration
- Environment variables documented for database, cache, and application setup