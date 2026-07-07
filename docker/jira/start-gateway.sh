#!/usr/bin/env bash
# GatewayWorker 单进程入口（Docker / 本机）
set -euo pipefail
cd /app

if [ ! -f .env ] && [ -f /docker-env/.env.docker ]; then
  cp /docker-env/.env.docker .env
fi

export GW_SERVER_ADDRESS="${GW_SERVER_ADDRESS:-0.0.0.0}"

GW_DIR="app/common/Plugins/GateWayWorker"

# 清理上次异常退出遗留的 pid（避免 already running）
rm -f "$GW_DIR"/*.pid /tmp/workerman*.pid 2>/dev/null || true

# 旧镜像未含 pcntl 时在线补装
if ! php -m 2>/dev/null | grep -q '^pcntl$'; then
  echo "[gateway] Installing php pcntl extension..."
  docker-php-ext-install pcntl
fi

echo "[gateway] Starting GatewayWorker on websocket://0.0.0.0:2345 ..."
exec php "$GW_DIR/start.php" start
