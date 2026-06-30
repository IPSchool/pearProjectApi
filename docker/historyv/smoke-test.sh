#!/usr/bin/env bash
# Gate A 冒烟测试 — HistoryV Docker 环境就绪后执行
set -euo pipefail

BASE="${BASE_URL:-http://127.0.0.1:8080/index.php}"
DEMO_ACCOUNT="${DEMO_ACCOUNT:-123456}"
DEMO_PASSWORD="${DEMO_PASSWORD:-123456}"
# 与前端 login.vue 一致：提交 MD5(明文)，库内存的也是 MD5
DEMO_PASSWORD_MD5=$(python3 -c "import hashlib; print(hashlib.md5('${DEMO_PASSWORD}'.encode()).hexdigest())")
PASS=0
FAIL=0

check() {
  local id="$1" desc="$2" cmd="$3"
  if eval "$cmd" >/dev/null 2>&1; then
    echo "✅ $id $desc"
    PASS=$((PASS + 1))
  else
    echo "❌ $id $desc"
    FAIL=$((FAIL + 1))
  fi
}

echo "=== HistoryV Gate A Smoke ==="
echo "BASE: $BASE"
echo ""

check "HV-A01" "后端健康检查" \
  "curl -sf '${BASE}/index/index/index' | grep -q '200\|部署成功\|success'"

check "HV-A02" "前端根路径 /" \
  "curl -sf 'http://127.0.0.1:8080/' | grep -q '/static/dist/js/'"

JS=$(curl -sf 'http://127.0.0.1:8080/' | grep -oE '/static/dist/js/app\.[a-f0-9]+\.js' | head -1)
check "HV-A02b" "前端 JS 可加载" \
  "curl -sfI 'http://127.0.0.1:8080${JS}' | grep -qi 'Content-Type: application/javascript'"

# 登录（password 须为 MD5，与前端 md5() 行为一致）
LOGIN_RESP=$(curl -s -X POST "${BASE}/project/login/index" \
  -d "account=${DEMO_ACCOUNT}&password=${DEMO_PASSWORD_MD5}" 2>/dev/null || echo "")

check "HV-A03" "登录返回 token" \
  "echo '$LOGIN_RESP' | grep -q 'accessToken'"

TOKEN=$(echo "$LOGIN_RESP" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('tokenList',{}).get('accessToken',''))" 2>/dev/null || echo "")

if [ -n "$TOKEN" ]; then
  check "HV-A04" "获取菜单" \
    "curl -sf -X POST '${BASE}/project/index/index' -H 'Authorization: bearer ${TOKEN}' | grep -q 'data'"
else
  echo "⏭️  HV-A04 跳过（无 token）"
fi

echo ""
echo "通过: $PASS  失败: $FAIL"
[ "$FAIL" -eq 0 ]
