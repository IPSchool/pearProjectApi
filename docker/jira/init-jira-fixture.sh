#!/usr/bin/env bash
# Gate B Fixture 初始化 — 创建 jira-test 用户、API Token、TST 项目（待 Jira 层实现后完善）
set -euo pipefail

echo "[GateB] init-jira-fixture: placeholder"
echo "  Target user:  jira-test@example.com"
echo "  Target token: gate-b-test-token (see tests/jira/env.sh.example)"
echo "  Target project key: TST"
echo "[GateB] Full fixture will run after /rest/api/3 auth + project modules land."
