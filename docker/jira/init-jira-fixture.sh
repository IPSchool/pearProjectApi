#!/usr/bin/env bash
# Gate B Fixture 初始化 — 创建 jira-test 用户、TST 项目
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR/../.."

echo "[GateB] init-jira-fixture"
echo "  Target user:  jira-test@example.com"
echo "  Target token: gate-b-test-token (see tests/jira/env.sh.example)"
echo "  Target project key: TST"

php "$DIR/fixture-init.php"
