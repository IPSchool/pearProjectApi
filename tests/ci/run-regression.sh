#!/usr/bin/env bash
# CI entry — docker/jira stack + full Gate A/B regression
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE_FILE="${ROOT}/docker/jira/docker-compose.yml"

export GATE_A_BASE_URL="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
export GATE_A_ACCOUNT="${GATE_A_ACCOUNT:-123456}"
export GATE_A_PASSWORD="${GATE_A_PASSWORD:-e10adc3949ba59abbe56e057f20f883e}"
export JIRA_BASE_URL="${JIRA_BASE_URL:-http://127.0.0.1:8090}"
export JIRA_EMAIL="${JIRA_EMAIL:-jira-test@example.com}"
export JIRA_API_TOKEN="${JIRA_API_TOKEN:-gate-b-test-token}"
export JIRA_PROJECT_KEY="${JIRA_PROJECT_KEY:-TST}"

echo "========== CI: Gate A + Gate B Regression =========="
echo "ROOT: ${ROOT}"

cd "${ROOT}"
docker compose -f "${COMPOSE_FILE}" up -d --build

bash "${ROOT}/tests/ci/wait-for-ready.sh"

echo "Initializing Jira fixture..."
docker compose -f "${COMPOSE_FILE}" exec -T app php /app/docker/jira/fixture-init.php

echo ""
bash "${ROOT}/tests/gate-a/run.sh"
