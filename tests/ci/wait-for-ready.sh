#!/usr/bin/env bash
# Wait until Gate A/B API responds on docker/jira stack.
set -euo pipefail

BASE="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
MAX_WAIT="${CI_WAIT_SECONDS:-180}"
INTERVAL=3
elapsed=0

echo "Waiting for API at ${BASE} (max ${MAX_WAIT}s)..."

while [ "$elapsed" -lt "$MAX_WAIT" ]; do
  if curl -sf "${BASE}/index/index/index" >/dev/null 2>&1; then
    echo "API ready after ${elapsed}s"
    exit 0
  fi
  sleep "$INTERVAL"
  elapsed=$((elapsed + INTERVAL))
done

echo "Timeout: API not ready at ${BASE}"
exit 1
