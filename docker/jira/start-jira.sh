#!/usr/bin/env bash
# Gate B Jira 改造环境一键启动
set -euo pipefail

COMPOSE_DIR="$(cd "$(dirname "$0")" && pwd)"
API="$(cd "$COMPOSE_DIR/../.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[GateB]${NC} $*"; }
warn() { echo -e "${YELLOW}[GateB]${NC} $*"; }
err() { echo -e "${RED}[GateB]${NC} $*"; exit 1; }

if ! docker info >/dev/null 2>&1; then
  err "Docker 未运行。请先打开 Docker Desktop。"
fi

log "启动 MySQL + Redis + PHP (Jira API :8090)..."
cd "$COMPOSE_DIR"
docker compose up -d --build

log "等待服务就绪..."
for i in $(seq 1 30); do
  if curl -sf "http://127.0.0.1:8090/rest/api/3/myself" >/dev/null 2>&1 || \
     curl -sf -o /dev/null -w "%{http_code}" "http://127.0.0.1:8090/rest/api/3/myself" | grep -qE '401|404|200'; then
    log "HTTP 响应就绪 (${i}s)"
    break
  fi
  sleep 2
done

echo ""
log "=========================================="
log " Gate B 改造环境已启动"
log " Jira API: http://127.0.0.1:8090/rest/api/3/"
log " 测试账号: jira-test@example.com / gate-b-test-token (Fixture 待实现)"
log " MySQL:    127.0.0.1:3308 (root/root)"
log "=========================================="
echo ""
warn "兼容层尚未实现 — 运行红灯测试:"
echo "  cp tests/jira/env.sh.example tests/jira/env.sh"
echo "  tests/jira/smoke/run.sh"
echo ""
