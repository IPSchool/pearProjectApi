#!/usr/bin/env bash
# Gate A + Gate B 合并前验收
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export GATE_A_BASE_URL="${GATE_A_BASE_URL:-http://127.0.0.1:8090}"
export GATE_A_ACCOUNT="${GATE_A_ACCOUNT:-123456}"
export GATE_A_PASSWORD="${GATE_A_PASSWORD:-e10adc3949ba59abbe56e057f20f883e}"

echo "========== Gate A (Legacy) =========="
python3 "$ROOT/tests/gate-a/test_gate_a.py"
gate_a=$?

echo ""
echo "========== Gate B (Jira) =========="
bash "$ROOT/tests/jira/smoke/run.sh"
gate_b=$?

if [ "$gate_a" -ne 0 ] || [ "$gate_b" -ne 0 ]; then
  echo ""
  echo "🔴 合并门禁未通过 (Gate A=$gate_a Gate B=$gate_b)"
  exit 1
fi

echo ""
echo "🟢 Gate A + Gate B 全部通过 — 可以合并 master"
exit 0
