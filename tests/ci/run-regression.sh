#!/usr/bin/env bash
# CI entry — docker/jira stack + full Gate A/B regression
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE_FILE="${ROOT}/docker/jira/docker-compose.yml"

export GATE_A_BASE_URL="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
export GATE_A_ACCOUNT="${GATE_A_ACCOUNT:-Lincoln}"
export GATE_A_PASSWORD="${GATE_A_PASSWORD:-e10adc3949ba59abbe56e057f20f883e}"
export JIRA_BASE_URL="${JIRA_BASE_URL:-http://127.0.0.1:8090}"
export JIRA_EMAIL="${JIRA_EMAIL:-jira-test@example.com}"
export JIRA_API_TOKEN="${JIRA_API_TOKEN:-gate-b-test-token}"
export JIRA_PROJECT_KEY="${JIRA_PROJECT_KEY:-TST}"

echo "========== CI: Gate A + Gate B Regression =========="
echo "ROOT: ${ROOT}"

cd "${ROOT}"
if ! docker compose -f "${COMPOSE_FILE}" up -d --build; then
  echo "docker compose up failed"
  docker compose -f "${COMPOSE_FILE}" logs mysql --tail 80 2>/dev/null || true
  exit 1
fi

# Fail fast if MySQL container crashed (e.g. seed SQL import error)
if ! docker compose -f "${COMPOSE_FILE}" ps --status running mysql 2>/dev/null | grep -q .; then
  echo "MySQL container is not running after compose up"
  docker compose -f "${COMPOSE_FILE}" logs mysql --tail 80 2>/dev/null || true
  exit 1
fi

bash "${ROOT}/tests/ci/wait-for-db-seed.sh"
bash "${ROOT}/tests/ci/wait-for-ready.sh"

echo "Ensuring composer dependencies..."
docker compose -f "${COMPOSE_FILE}" exec -T app bash -lc \
  'git config --global --add safe.directory "*" \
   && export COMPOSER_ROOT_VERSION=dev-main \
   && composer config --no-plugins allow-plugins.topthink/think-installer true \
   && composer install --no-interaction --prefer-dist --no-dev \
   && composer dump-autoload -o \
   && php -r "require \"vendor/autoload.php\"; if (!class_exists(\"Firebase\\\\JWT\\\\JWT\")) { fwrite(STDERR, \"missing firebase/php-jwt\n\"); exit(1); }"'

echo "Initializing Jira fixture..."
docker compose -f "${COMPOSE_FILE}" exec -T app php /app/docker/jira/fixture-init.php

bash "${ROOT}/tests/ci/fix-runtime-perms.sh"
bash "${ROOT}/tests/ci/preflight-login.sh"

echo ""
bash "${ROOT}/tests/gate-a/run.sh"
