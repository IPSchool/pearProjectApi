#!/usr/bin/env bash
# Fail fast if legacy login is broken before running the full Gate A suite.
set -euo pipefail

BASE="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
ACCOUNT="${GATE_A_ACCOUNT:-Lincoln}"
PASSWORD="${GATE_A_PASSWORD:-e10adc3949ba59abbe56e057f20f883e}"

echo "Preflight: POST ${BASE}/project/login/index"
body="$(curl -sS -w '\n%{http_code}' -X POST "${BASE}/project/login/index" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "account=${ACCOUNT}" \
  --data-urlencode "password=${PASSWORD}")"
http_code="${body##*$'\n'}"
payload="${body%$'\n'*}"
token="$(python3 -c "
import json, sys
try:
    data = json.loads(sys.argv[1])
    print((data.get('data') or {}).get('tokenList', {}).get('accessToken', ''))
except Exception:
    print('')
" "${payload}")"

if [ "${http_code}" = "200" ] && [ -n "${token}" ]; then
  echo "Preflight login OK"
  exit 0
fi

echo "Preflight login FAILED: http=${http_code} body=${payload}"
exit 1
