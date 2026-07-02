#!/usr/bin/env bash
# Wait until MySQL init SQL has seeded Gate A login account.
set -euo pipefail

MAX_WAIT="${CI_WAIT_SECONDS:-600}"
INTERVAL=5
elapsed=0
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE_FILE="${ROOT}/docker/jira/docker-compose.yml"
ACCOUNT="${GATE_A_ACCOUNT:-123456}"

echo "Waiting for DB seed (pear_member.account=${ACCOUNT}, max ${MAX_WAIT}s)..."

while [ "$elapsed" -lt "$MAX_WAIT" ]; do
  count="$(docker compose -f "${COMPOSE_FILE}" exec -T mysql \
    mysql -uroot -proot -N -e \
    "SELECT COUNT(*) FROM pearproject.pear_member WHERE account='${ACCOUNT}' LIMIT 1" 2>/dev/null || echo 0)"
  count="${count//$'\r'/}"
  if [ "${count}" = "1" ]; then
    echo "DB seed ready after ${elapsed}s"
    exit 0
  fi
  if [ $((elapsed % 30)) -eq 0 ] && [ "$elapsed" -gt 0 ]; then
    echo "  still waiting for SQL import (${elapsed}s, count=${count})"
  fi
  sleep "$INTERVAL"
  elapsed=$((elapsed + INTERVAL))
done

echo "Timeout: Gate A account ${ACCOUNT} not found in pear_member"
docker compose -f "${COMPOSE_FILE}" logs mysql --tail 30 2>/dev/null || true
exit 1
