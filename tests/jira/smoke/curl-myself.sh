#!/usr/bin/env bash
# Layer 1 — Atlassian 文档 curl 回放（须阻塞 CI）
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && cd .. && pwd)"
# shellcheck source=/dev/null
[ -f "$SCRIPT_DIR/env.sh" ] && source "$SCRIPT_DIR/env.sh"

BASE="${JIRA_BASE_URL:-http://127.0.0.1:8090}"
EMAIL="${JIRA_EMAIL:-jira-test@example.com}"
TOKEN="${JIRA_API_TOKEN:-gate-b-test-token}"

fail=0

echo "=== Jira L1 curl replay: GET /rest/api/3/myself ==="
echo "BASE: $BASE"
echo ""

echo "--- JIRA-L1-A01: 无认证（期望 401）---"
code=$(curl -s -o /tmp/jira-myself-none.json -w "%{http_code}" \
  "$BASE/rest/api/3/myself")
echo "HTTP $code"
head -c 200 /tmp/jira-myself-none.json
echo ""
if [ "$code" != "401" ]; then
  echo "❌ JIRA-L1-A01 期望 401"
  fail=1
else
  echo "✅ JIRA-L1-A01"
fi
echo ""

echo "--- JIRA-L1-A03: Basic Auth（期望 200 + accountId）---"
code=$(curl -s -o /tmp/jira-myself-auth.json -w "%{http_code}" \
  -u "$EMAIL:$TOKEN" \
  -H "Accept: application/json" \
  "$BASE/rest/api/3/myself")
echo "HTTP $code"
head -c 400 /tmp/jira-myself-auth.json
echo ""
if [ "$code" != "200" ]; then
  echo "❌ JIRA-L1-A03 期望 200"
  fail=1
elif ! grep -q accountId /tmp/jira-myself-auth.json; then
  echo "❌ JIRA-L1-A03 缺少 accountId"
  fail=1
else
  echo "✅ JIRA-L1-A03"
fi

exit "$fail"
