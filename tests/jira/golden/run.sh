#!/usr/bin/env bash
# Gate B Layer 3 — schema contract tests
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"

export JIRA_BASE_URL="${JIRA_BASE_URL:-http://127.0.0.1:8090}"
export JIRA_EMAIL="${JIRA_EMAIL:-jira-test@example.com}"
export JIRA_API_TOKEN="${JIRA_API_TOKEN:-gate-b-test-token}"
export JIRA_PROJECT_KEY="${JIRA_PROJECT_KEY:-TST}"

python3 -c "import yaml" 2>/dev/null || pip install pyyaml -q

python3 "$DIR/test_l3_schema.py"
