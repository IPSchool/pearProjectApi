#!/usr/bin/env bash
# Layer 1 — Atlassian 文档 curl 回放（仅改 base URL）
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && cd .. && pwd)"
# shellcheck source=/dev/null
[ -f "$SCRIPT_DIR/env.sh" ] && source "$SCRIPT_DIR/env.sh"

BASE="${JIRA_BASE_URL:-http://127.0.0.1:8090}"
EMAIL="${JIRA_EMAIL:-jira-test@example.com}"
TOKEN="${JIRA_API_TOKEN:-gate-b-test-token}"

echo "=== Jira L1 curl replay: GET /rest/api/3/myself ==="
echo "BASE: $BASE"
echo ""

echo "--- JIRA-L1-A01: 无认证（期望 401）---"
code=$(curl -s -o /tmp/jira-myself-none.json -w "%{http_code}" \
  "$BASE/rest/api/3/myself" || true)
echo "HTTP $code"
cat /tmp/jira-myself-none.json | head -c 200
echo ""
echo ""

echo "--- JIRA-L1-A03: Basic Auth（期望 200 + accountId）---"
code=$(curl -s -o /tmp/jira-myself-auth.json -w "%{http_code}" \
  -u "$EMAIL:$TOKEN" \
  -H "Accept: application/json" \
  "$BASE/rest/api/3/myself" || true)
echo "HTTP $code"
cat /tmp/jira-myself-auth.json | head -c 400
echo ""
