#!/usr/bin/env bash
# Gate A + Gate B 合并门禁（core + extended）
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export GATE_A_BASE_URL="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
export GATE_A_ACCOUNT="${GATE_A_ACCOUNT:-123456}"
export GATE_A_PASSWORD="${GATE_A_PASSWORD:-e10adc3949ba59abbe56e057f20f883e}"

fail=0

echo "========== Gate A Core (HV-A01~A17) =========="
python3 "$ROOT/tests/gate-a/test_gate_a.py" || fail=1

echo ""
echo "========== Gate A Extended (HV-A18~A95) =========="
python3 "$ROOT/tests/gate-a/test_gate_a_extended.py" || fail=1

echo ""
echo "========== Gate B (Jira) =========="
bash "$ROOT/tests/jira/smoke/run.sh" || fail=1

if [ "$fail" -ne 0 ]; then
  echo ""
  echo "🔴 合并门禁未通过"
  exit 1
fi

echo ""
echo "🟢 Gate A + Gate B 全部通过"
exit 0
