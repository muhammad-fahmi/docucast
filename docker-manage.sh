#!/bin/bash

# DocuCast Docker Management Script
# Quick reference for common Docker operations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

detect_environment() {
    # Explicit override via env var always wins
    if [ -n "${DOCKER_ENV:-}" ]; then
        echo "$DOCKER_ENV"
        return
    fi

    # CI / server-like contexts default to hostinger
    if [ "${CI:-}" = "true" ] || [ -n "${GITHUB_ACTIONS:-}" ] || [ "${HOSTINGER_DOCKER_MANAGER:-}" = "true" ] || [ "${HOSTINGER:-}" = "true" ]; then
        echo "hostinger"
        return
    fi

    # Developer machines default to local
    echo "local"
}

ENVIRONMENT="$(detect_environment)"

# Manual CLI override
if [ "${1:-}" = "--env" ] || [ "${1:-}" = "-e" ]; then
    if [ -z "${2:-}" ]; then
        echo "Missing environment after $1. Use: local or hostinger"
        exit 1
    fi

    ENVIRONMENT="$2"
    shift 2
fi

case "$ENVIRONMENT" in
    local)
        COMPOSE_FILE="docker-compose.local.yml"
        ENV_FILE=".env"
        IMAGE_TAG="docucast:local"
        ;;
    hostinger)
        COMPOSE_FILE="docker-compose.hostinger.yml"
        ENV_FILE=".env.production"
        IMAGE_TAG="docucast:hostinger"
        ;;
    *)
        echo "Invalid environment: $ENVIRONMENT. Valid values: local, hostinger"
        exit 1
        ;;
esac

docker_compose() {
    docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"
}

# Available commands
show_help() {
    cat << EOF
DocuCast Docker Management Script

Usage: ./docker-manage.sh [--env local|hostinger] <command> [options]

Environment selection:
    Auto-detect default:
      - hostinger on CI/server contexts
      - local on developer machines
    Manual override:
      --env hostinger
      --env local

Commands:
    build               Build the Docker image
    up                  Start all services
    down                Stop all services
    logs                View service logs

    migrate             Run database migrations
    seed                Seed the database
    shell               Open bash shell in app container
    tinker              Open Laravel Tinker REPL
    artisan             Run an artisan command

    ps                  Show running containers
    status              Check service health

    db-backup           Backup PostgreSQL database
    db-restore BACKUP   Restore PostgreSQL database from backup

    push REGISTRY TAG   Build and push image to registry
    clean               Remove all containers and volumes

    help                Show this help message

Examples:
    ./docker-manage.sh up
    ./docker-manage.sh --env local build
    ./docker-manage.sh --env local up
    ./docker-manage.sh --env hostinger up
    ./docker-manage.sh --env hostinger migrate
    ./docker-manage.sh --env local logs app

EOF
}

# Commands implementation
cmd_build() {
    log_info "Building Docker image for environment: $ENVIRONMENT"
    docker_compose build
    log_success "Docker image built successfully"
}

cmd_up() {
    log_info "Starting services for environment: $ENVIRONMENT"
    docker_compose up -d
    sleep 5
    log_success "Services started"

    if [ "$ENVIRONMENT" = "local" ]; then
        log_info "Web App: http://localhost:8090"
        log_info "Reverb (proxied): http://localhost:8090/app"
    else
        log_info "Web App: https://docucast.bionic-natura.cloud"
        log_info "Reverb (proxied): https://docucast.bionic-natura.cloud/app"
    fi
}

cmd_down() {
    log_info "Stopping services for environment: $ENVIRONMENT"
    docker_compose down
    log_success "Services stopped"
}

cmd_logs() {
    SERVICE=${2:-''}
    if [ -z "$SERVICE" ]; then
        docker_compose logs -f
    else
        docker_compose logs -f "$SERVICE"
    fi
}

cmd_migrate() {
    log_info "Running database migrations..."
    docker_compose exec -T app php artisan migrate --force
    log_success "Migrations completed"
}

cmd_seed() {
    log_info "Seeding database..."
    docker_compose exec -T app php artisan db:seed
    log_success "Database seeded"
}

cmd_shell() {
    log_info "Opening bash shell in app container..."
    docker_compose exec app bash
}

cmd_tinker() {
    log_info "Opening Laravel Tinker..."
    docker_compose exec app php artisan tinker
}

cmd_artisan() {
    COMMAND=${@:2}
    if [ -z "$COMMAND" ]; then
        log_error "Please provide an artisan command"
        exit 1
    fi
    docker_compose exec app php artisan $COMMAND
}

cmd_ps() {
    docker_compose ps
}

cmd_status() {
    log_info "Checking service health..."
    docker_compose ps --format "table {{.Service}}\t{{.Status}}"
}

cmd_db_backup() {
    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="database_backup_${TIMESTAMP}.sql"
    DB_USER="${DB_USERNAME:-postgres}"
    DB_NAME="${DB_DATABASE:-docucast}"

    log_info "Backing up PostgreSQL database to $BACKUP_FILE..."
    docker_compose exec -T postgres pg_dump -U "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"

    if [ -f "$BACKUP_FILE" ]; then
        log_success "Database backed up to $BACKUP_FILE"
        log_info "File size: $(ls -lh $BACKUP_FILE | awk '{print $5}')"
    else
        log_error "Backup failed"
        exit 1
    fi
}

cmd_db_restore() {
    BACKUP_FILE=$2
    DB_USER="${DB_USERNAME:-postgres}"
    DB_NAME="${DB_DATABASE:-docucast}"

    if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
        log_error "Please provide a valid backup file path"
        exit 1
    fi

    log_warning "This will restore the database from $BACKUP_FILE"
    read -p "Are you sure? (yes/no): " CONFIRM

    if [ "$CONFIRM" = "yes" ]; then
        log_info "Restoring database..."
        docker_compose exec -T postgres psql -U "$DB_USER" "$DB_NAME" < "$BACKUP_FILE"
        log_success "Database restored"
    else
        log_info "Restore cancelled"
    fi
}

cmd_push() {
    IMAGE=$2
    if [ -z "$IMAGE" ]; then
        log_error "Please provide image name and tag (e.g., myregistry/docucast:1.0.0)"
        exit 1
    fi

    log_info "Building Docker image as $IMAGE..."
    docker build -t "$IMAGE" .

    log_info "Pushing to registry..."
    docker push "$IMAGE"

    log_success "Image pushed successfully: $IMAGE"
}

cmd_clean() {
    log_warning "This will remove all containers, volumes, and images"
    read -p "Are you sure? (yes/no): " CONFIRM

    if [ "$CONFIRM" = "yes" ]; then
        log_info "Removing containers..."
        docker_compose down -v

        log_info "Removing images..."
        docker rmi "$IMAGE_TAG"

        log_success "Cleanup completed"
    else
        log_info "Cleanup cancelled"
    fi
}

log_info "Using environment profile: $ENVIRONMENT"

# Main command router
COMMAND=${1:-'help'}

case "$COMMAND" in
    build)      cmd_build ;;
    up)         cmd_up ;;
    down)       cmd_down ;;
    logs)       cmd_logs "$@" ;;
    migrate)    cmd_migrate ;;
    seed)       cmd_seed ;;
    shell)      cmd_shell ;;
    tinker)     cmd_tinker ;;
    artisan)    cmd_artisan "$@" ;;
    ps)         cmd_ps ;;
    status)     cmd_status ;;
    db-backup)  cmd_db_backup ;;
    db-restore) cmd_db_restore "$@" ;;
    push)       cmd_push "$@" ;;
    clean)      cmd_clean ;;
    help)       show_help ;;
    *)          log_error "Unknown command: $COMMAND"; echo "Run './docker-manage.sh help' for usage"; exit 1 ;;
esac
