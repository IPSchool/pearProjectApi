#!/usr/bin/env bash
# 重启 Gate A/B API（8090）— 修改 PHP 路由或后端代码后执行
set -euo pipefail

COMPOSE_DIR="$(cd "$(dirname "$0")" && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[API]${NC} $*"; }
warn() { echo -e "${YELLOW}[API]${NC} $*"; }
err() { echo -e "${RED}[API]${NC} $*"; exit 1; }

if ! docker info >/dev/null 2>&1; then
  err "Docker 未运行。请先打开 Docker Desktop。"
fi

cd "$COMPOSE_DIR"

if ! docker compose ps --status running -q app 2>/dev/null | grep -q .; then
  warn "app 容器未运行，改为完整启动…"
  exec "$COMPOSE_DIR/start-jira.sh"
fi

log "重启 app 容器 (php-fpm + nginx :8090)…"
docker compose restart app

log "清理运行时缓存…"
docker compose exec -T app sh -c 'rm -rf /app/runtime/cache/* 2>/dev/null || true'

log "Applying DB migrations…"
docker compose exec -T app php /app/docker/jira/run-migrations.php 2>&1 || warn "migrate skipped (container starting?)"

log "等待 HTTP 就绪…"
ready=0
for i in $(seq 1 45); do
  code=$(curl -sf -o /dev/null -w "%{http_code}" "http://127.0.0.1:8090/swagger-spec" 2>/dev/null || echo "000")
  if [ "$code" = "200" ]; then
    ready=1
    log "HTTP 响应就绪 (${i}s)"
    break
  fi
  sleep 1
done

if [ "$ready" -ne 1 ]; then
  warn "swagger-spec 未在 45s 内返回 200，容器可能仍在启动。"
  warn "请检查: cd docker/jira && docker compose logs app --tail 50"
fi

echo ""
log "=========================================="
log " API 已重启"
log " Legacy:  http://127.0.0.1:8090/project/"
log " Swagger: http://127.0.0.1:8090/swagger-ui"
log "=========================================="
