#!/usr/bin/env bash
# HistoryV 本地验收环境一键启动
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
FRONTEND="$ROOT/../pearProject"
API="$ROOT"
COMPOSE_DIR="$API/docker/historyv"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[HistoryV]${NC} $*"; }
warn() { echo -e "${YELLOW}[HistoryV]${NC} $*"; }
err() { echo -e "${RED}[HistoryV]${NC} $*"; exit 1; }

# 1. Docker 检查
if ! docker info >/dev/null 2>&1; then
  err "Docker 未运行。请先打开 Docker Desktop，等待状态变为 Running 后重试。"
fi

# 2. 前端构建（本地 Node 优先，避免拉 node 镜像）
need_frontend_build=false
if [ ! -f "$API/static/dist/index.html" ]; then
  need_frontend_build=true
elif ! grep -q '/static/dist/js/' "$API/static/dist/index.html" 2>/dev/null; then
  warn "static/dist/index.html 资源路径过期，需重新构建（VUE_APP_BUILD_PATH=/static/dist/）"
  need_frontend_build=true
fi

if [ "$need_frontend_build" = true ]; then
  warn "未找到 static/dist/index.html，开始本地构建前端..."
  if ! command -v npm >/dev/null 2>&1; then
    warn "本地无 npm，尝试 Docker 构建前端..."
    (cd "$COMPOSE_DIR" && docker compose --profile build run --rm frontend-build)
  else
    (cd "$FRONTEND" && \
      npm install --registry=https://registry.npmmirror.com && \
      VUE_APP_CROSS_DOMAIN=false \
      VUE_APP_API_URL=http://127.0.0.1:8080/index.php \
      VUE_APP_WS_URI= \
      VUE_APP_HOME_PAGE=/home \
      VUE_APP_BUILD_PATH=/static/dist/
      npm run build && \
      mkdir -p "$API/static/dist" && \
      rm -rf "$API/static/dist/"* && \
      cp -r dist/* "$API/static/dist/")
    log "前端已构建至 pearProjectApi/static/dist/"
  fi
else
  log "前端 static/dist 已就绪，跳过构建"
fi

# 3. 启动后端栈
log "启动 MySQL + Redis + PHP..."
cd "$COMPOSE_DIR"
docker compose up -d --build

# 4. 等待 API
log "等待 API 就绪..."
for i in $(seq 1 60); do
  if curl -sf "http://127.0.0.1:8080/index.php/index/index/index" >/dev/null 2>&1; then
    log "API 就绪 (${i}s)"
    break
  fi
  sleep 2
done

echo ""
log "=========================================="
log " HistoryV 验收环境已启动"
log " 前端+API: http://127.0.0.1:8080"
log " 演示账号: 123456 / 123456"
log " MySQL:    127.0.0.1:3307 (root/root)"
log "=========================================="
echo ""
log "快速 API 测试:"
curl -s "http://127.0.0.1:8080/index.php/index/index/index" | head -c 200 || warn "API 尚未响应，请 docker compose logs -f app"
echo ""
