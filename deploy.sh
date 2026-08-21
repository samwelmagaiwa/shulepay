#!/usr/bin/env bash
# =============================================================================
# ShulePay — Production Deploy Script
# Usage:
#   ./deploy.sh deploy      → full deploy (pull + migrate + restart)
#   ./deploy.sh rollback    → roll back to previous image tag
#   ./deploy.sh status      → show running containers & health
#   ./deploy.sh logs        → tail logs from all services
#   ./deploy.sh restart     → restart containers without pulling
#   ./deploy.sh migrate     → run migrations only
#   ./deploy.sh shell       → open shell inside backend container
# =============================================================================

set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
# Detect if running locally (Windows/Git Bash) or on the server
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -d "/www/shulepay" ]]; then
  APP_DIR="/www/shulepay"
else
  APP_DIR="$SCRIPT_DIR"
fi

COMPOSE_FILE="$APP_DIR/docker-compose.prod.yml"
ENV_FILE="$APP_DIR/.env"
BACKUP_TAG_FILE="$APP_DIR/.previous_tag"
LOG_FILE="$APP_DIR/deploy.log"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

# ── Helpers ───────────────────────────────────────────────────────────────────
log()     { echo -e "${CYAN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $*" | tee -a "$LOG_FILE"; }
success() { echo -e "${GREEN}✔ $*${NC}" | tee -a "$LOG_FILE"; }
warn()    { echo -e "${YELLOW}⚠ $*${NC}" | tee -a "$LOG_FILE"; }
error()   { echo -e "${RED}✖ $*${NC}" | tee -a "$LOG_FILE"; exit 1; }

rotate_log() {
  # Keep deploy.log under 2 MB — truncate oldest half when exceeded
  local max_bytes=2097152
  if [[ -f "$LOG_FILE" ]] && [[ $(wc -c < "$LOG_FILE") -gt $max_bytes ]]; then
    local lines; lines=$(wc -l < "$LOG_FILE")
    tail -n $((lines / 2)) "$LOG_FILE" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"
    echo "[log rotated]" >> "$LOG_FILE"
  fi
}

require_env() {
  [[ -f "$ENV_FILE" ]] || error ".env file not found at $ENV_FILE"
  set -a; source "$ENV_FILE"; set +a
}

require_docker() {
  command -v docker &>/dev/null        || error "Docker is not installed."
  docker compose version &>/dev/null   || error "Docker Compose plugin not installed."
  docker info &>/dev/null              || error "Docker daemon is not running."
}

health_check() {
  local container=$1
  local max_wait=${2:-60}
  local waited=0
  log "Waiting for $container to become healthy..."
  until docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null | grep -q "healthy"; do
    sleep 3; waited=$((waited + 3))
    [[ $waited -ge $max_wait ]] && return 1
  done
  return 0
}

# ── Commands ──────────────────────────────────────────────────────────────────

cmd_deploy() {
  rotate_log

  log "══════════════════════════════════════════"
  log "  ShulePay — Starting Deployment"
  log "══════════════════════════════════════════"

  require_env
  require_docker

  # 1. Save current image tag for rollback (use IMAGE_TAG from .env written by CI)
  CURRENT_TAG="${IMAGE_TAG:-none}"
  echo "$CURRENT_TAG" > "$BACKUP_TAG_FILE"
  log "Previous tag saved for rollback: $CURRENT_TAG"

  # 2. Pull latest images (images are public — no Docker Hub login needed on server)
  log "Pulling latest images from Docker Hub..."
  docker compose -f "$COMPOSE_FILE" pull \
    || error "Failed to pull images from Docker Hub"
  success "Images pulled successfully"

  # 3. Recreate only app containers (backend + frontend); leave DB untouched unless its image changed
  log "Starting containers..."
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans \
    || error "Failed to start containers"
  success "Containers started"

  # 4. Wait for DB health (skip if using external DB — no shulepay_db container)
  if docker ps --filter "name=shulepay_db" --format '{{.Names}}' | grep -q shulepay_db; then
    log "Waiting for database..."
    health_check shulepay_db 90 \
      || error "Database did not become healthy in time. Run: ./deploy.sh logs"
  else
    log "Using external database — skipping container health check"
  fi

  # 5. Brief wait to ensure backend PHP-FPM is fully up before running artisan
  sleep 3

  # 6. Run migrations only if there are pending ones
  log "Checking for pending migrations..."
  PENDING=$(docker exec shulepay_backend php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
  if [[ "$PENDING" -gt 0 ]]; then
    log "Found $PENDING pending migration(s) — running..."
    docker exec shulepay_backend php artisan migrate --force \
      || error "Migrations failed. Run: ./deploy.sh rollback"
    success "Migrations complete ($PENDING applied)"
  else
    success "No pending migrations — skipped"
  fi

  # 7. Artisan optimisation — single optimize call (config + route + view + event cache)
  log "Optimising Laravel..."
  docker exec shulepay_backend php artisan optimize \
    || warn "artisan optimize had warnings — continuing"
  success "Laravel cache warmed"

  # 8. Storage link (idempotent — safe to always run)
  docker exec shulepay_backend php artisan storage:link 2>/dev/null || true

  # 9. Queue restart (picks up new code)
  docker exec shulepay_backend php artisan queue:restart 2>/dev/null || true

  # 10. Final health check
  log "Verifying containers are running..."
  cmd_status

  # 11. Remove dangling images to free disk
  log "Cleaning up dangling images..."
  docker image prune -f
  success "Cleanup done"

  log "══════════════════════════════════════════"
  success "Deployment complete! 🚀"
  log "══════════════════════════════════════════"
}

cmd_rollback() {
  require_env
  require_docker

  [[ -f "$BACKUP_TAG_FILE" ]] || error "No previous tag found. Cannot rollback."
  PREV_TAG=$(cat "$BACKUP_TAG_FILE")
  [[ "$PREV_TAG" == "none" ]] && error "Previous tag is 'none'. Cannot rollback."

  # Resolve actual image names from env (set by CI) or fall back to defaults
  BACKEND_IMG="${BACKEND_IMAGE:-magaiwa/magreth-backend}"
  FRONTEND_IMG="${FRONTEND_IMAGE:-magaiwa/magreth-frontend}"

  warn "Rolling back to tag: $PREV_TAG"

  # Pull the previous images
  docker pull "${BACKEND_IMG}:${PREV_TAG}"  || error "Could not pull previous backend image"
  docker pull "${FRONTEND_IMG}:${PREV_TAG}" || error "Could not pull previous frontend image"

  # Re-tag as latest so compose picks them up
  docker tag "${BACKEND_IMG}:${PREV_TAG}"  "${BACKEND_IMG}:latest"
  docker tag "${FRONTEND_IMG}:${PREV_TAG}" "${FRONTEND_IMG}:latest"

  # Restart app containers only (leave DB running)
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

  success "Rolled back to $PREV_TAG"
  warn "Note: DB schema is NOT rolled back. Run 'php artisan migrate:rollback' manually if needed."
}

cmd_status() {
  echo ""
  echo -e "${CYAN}Container status:${NC}"
  docker compose -f "$COMPOSE_FILE" ps
  echo ""
  echo -e "${CYAN}Resource usage:${NC}"
  docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}" \
    shulepay_backend shulepay_frontend shulepay_db 2>/dev/null || true
  echo ""
}

cmd_logs() {
  local service=${2:-}
  if [[ -n "$service" ]]; then
    docker compose -f "$COMPOSE_FILE" logs -f --tail=100 "$service"
  else
    docker compose -f "$COMPOSE_FILE" logs -f --tail=50
  fi
}

cmd_restart() {
  require_env
  require_docker
  log "Restarting containers..."
  docker compose -f "$COMPOSE_FILE" restart
  success "Containers restarted"
}

cmd_migrate() {
  log "Checking for pending migrations..."
  PENDING=$(docker exec shulepay_backend php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
  if [[ "$PENDING" -gt 0 ]]; then
    log "Found $PENDING pending migration(s) — running..."
    docker exec shulepay_backend php artisan migrate --force
    success "Migrations done ($PENDING applied)"
  else
    success "No pending migrations — nothing to do"
  fi
}

cmd_release() {
  # Must be run from the project root (where .git lives)
  [[ -d ".git" ]] || error "Not a git repository. Run this from the project root."
  command -v git &>/dev/null || error "git is not installed."

  log "══════════════════════════════════════════"
  log "  ShulePay — Release"
  log "══════════════════════════════════════════"

  # 1. Show what will be committed
  log "Checking working tree..."
  git status --short
  echo ""

  # 2. Stage all changes
  git add -A
  STAGED=$(git diff --cached --name-only)
  if [[ -z "$STAGED" ]]; then
    warn "Nothing to commit — working tree is clean."
    exit 0
  fi

  log "Files staged for commit:"
  echo "$STAGED" | sed 's/^/  → /'
  echo ""

  # 3. Ask for commit message (or accept from arg)
  COMMIT_MSG="${2:-}"
  if [[ -z "$COMMIT_MSG" ]]; then
    echo -e "${CYAN}Enter commit message (or press Enter for auto-generated):${NC}"
    read -r COMMIT_MSG
  fi

  # Auto-generate message from changed file count if empty
  if [[ -z "$COMMIT_MSG" ]]; then
    FILE_COUNT=$(echo "$STAGED" | wc -l | tr -d ' ')
    BRANCH=$(git rev-parse --abbrev-ref HEAD)
    COMMIT_MSG="chore: update ${FILE_COUNT} file(s) on ${BRANCH} [$(date '+%Y-%m-%d %H:%M')]"
  fi

  # 4. Commit
  log "Committing: \"$COMMIT_MSG\""
  git commit -m "$COMMIT_MSG"
  success "Committed"

  # 5. Push
  BRANCH=$(git rev-parse --abbrev-ref HEAD)
  log "Pushing to origin/$BRANCH..."
  git push origin "$BRANCH"
  success "Pushed to origin/$BRANCH"

  # 6. Show latest commit
  echo ""
  echo -e "${CYAN}Latest commit:${NC}"
  git log --oneline -1
  echo ""
  success "Release done! CI/CD pipeline will now trigger on GitHub. 🚀"
  log "Watch it at: https://github.com/samwelmagaiwa/shulepay/actions"
}

cmd_shell() {
  local service=${2:-backend}
  case "$service" in
    backend)  docker exec -it shulepay_backend bash ;;
    frontend) docker exec -it shulepay_frontend sh ;;
    db)       docker exec -it shulepay_db mysql -u root -p ;;
    *)        error "Unknown service: $service. Use: backend, frontend, db" ;;
  esac
}

# ── Router ────────────────────────────────────────────────────────────────────
COMMAND=${1:-help}

case "$COMMAND" in
  release)  cmd_release "$@" ;;
  deploy)   cmd_deploy ;;
  rollback) cmd_rollback ;;
  status)   require_env; cmd_status ;;
  logs)     require_env; cmd_logs "$@" ;;
  restart)  cmd_restart ;;
  migrate)  cmd_migrate ;;
  shell)    cmd_shell "$@" ;;
  help|*)
    echo ""
    echo -e "${CYAN}ShulePay Deploy Script${NC}"
    echo "───────────────────────────────────"
    echo -e "${YELLOW}LOCAL COMMANDS${NC}"
    echo "  ./deploy.sh release              Stage, commit & push to GitHub"
    echo "  ./deploy.sh release \"my message\" Use a custom commit message"
    echo ""
    echo -e "${YELLOW}SERVER COMMANDS${NC}"
    echo "  ./deploy.sh deploy      Full deploy: pull → migrate → restart"
    echo "  ./deploy.sh rollback    Roll back to previous image"
    echo "  ./deploy.sh status      Show container status & resource usage"
    echo "  ./deploy.sh logs        Tail all container logs"
    echo "  ./deploy.sh logs db     Tail logs for a specific service"
    echo "  ./deploy.sh restart     Restart containers (no pull)"
    echo "  ./deploy.sh migrate     Run artisan migrate only"
    echo "  ./deploy.sh shell       Open bash in backend container"
    echo "  ./deploy.sh shell db    Open MySQL shell"
    echo ""
    ;;
esac
