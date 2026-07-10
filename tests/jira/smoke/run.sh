#!/usr/bin/env bash
# Gate B smoke — B-α / B-β / B-γ + jira-python Layer 4
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

fail=0

bash "$DIR/smoke/curl-myself.sh" || fail=1
echo ""

python3 "$DIR/smoke/test_b_alpha.py" || fail=1
python3 "$DIR/smoke/test_b_beta.py" || fail=1
python3 "$DIR/smoke/test_b_gamma.py" || fail=1
python3 "$DIR/smoke/test_b_delta.py" || fail=1

python3 "$DIR/smoke/test_jira_python.py" || fail=1
python3 "$DIR/smoke/test_jira_python_extended.py" || fail=1
python3 "$DIR/smoke/test_b_epsilon.py" || fail=1
python3 "$DIR/smoke/test_b_resolution.py" || fail=1
python3 "$DIR/smoke/test_b_eta.py" || fail=1

bash "$DIR/contract/run.sh" || fail=1
bash "$DIR/golden/run.sh" || fail=1

if [ "$fail" -ne 0 ]; then
  exit 1
fi
exit 0
