#!/usr/bin/env bash
# Wait until Gate A/B API responds on docker/jira stack.
set -euo pipefail

BASE="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
MAX_WAIT="${CI_WAIT_SECONDS:-180}"
INTERVAL=5
elapsed=0
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE_FILE="${ROOT}/docker/jira/docker-compose.yml"

echo "Waiting for API at ${BASE} (max ${MAX_WAIT}s)..."

while [ "$elapsed" -lt "$MAX_WAIT" ]; do
  if curl -sf "${BASE}/index/index/index" >/dev/null 2>&1; then
    echo "API ready after ${elapsed}s"
    exit 0
  fi
  if [ $((elapsed % 30)) -eq 0 ] && [ "$elapsed" -gt 0 ]; then
    echo "  still waiting (${elapsed}s) — recent app logs:"
    docker compose -f "${COMPOSE_FILE}" logs app --tail 8 2>/dev/null || true
  fi
  sleep "$INTERVAL"
  elapsed=$((elapsed + INTERVAL))
done

echo "Timeout: API not ready at ${BASE}"
docker compose -f "${COMPOSE_FILE}" logs app --tail 40 2>/dev/null || true
exit 1
