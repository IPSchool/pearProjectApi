#!/usr/bin/env bash
# Reset writable dirs after root-owned exec steps (composer, fixture-init) on CI bind mounts.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE_FILE="${ROOT}/docker/jira/docker-compose.yml"

docker compose -f "${COMPOSE_FILE}" exec -T app bash -lc \
  'mkdir -p runtime/cache runtime/log runtime/temp static/upload data \
   && chmod -R 777 runtime static/upload data \
   && chown -R www-data:www-data runtime static/upload data 2>/dev/null || true'

echo "Runtime directories are writable for php-fpm"
