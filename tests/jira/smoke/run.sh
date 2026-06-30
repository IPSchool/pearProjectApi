#!/usr/bin/env bash
# Gate B-α 一键冒烟 — 当前预期红灯
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && cd .. && pwd)"
# shellcheck source=/dev/null
[ -f "$DIR/env.sh" ] && source "$DIR/env.sh"
export JIRA_BASE_URL="${JIRA_BASE_URL:-http://127.0.0.1:8090}"
export JIRA_EMAIL="${JIRA_EMAIL:-jira-test@example.com}"
export JIRA_API_TOKEN="${JIRA_API_TOKEN:-gate-b-test-token}"
export JIRA_PROJECT_KEY="${JIRA_PROJECT_KEY:-TST}"

echo "Gate B smoke — env from ${DIR}/env.sh or defaults"
echo ""

bash "$DIR/smoke/curl-myself.sh" || true
echo ""

python3 "$DIR/smoke/test_b_alpha.py"
alpha=$?

python3 "$DIR/smoke/test_jira_python.py" || true

exit "$alpha"
