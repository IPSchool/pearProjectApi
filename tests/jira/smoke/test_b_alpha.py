#!/usr/bin/env python3
"""Gate B-α regression — Jira REST API v3 compatibility (expect RED until implemented)."""
from __future__ import annotations

import base64
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ.get("JIRA_BASE_URL", "http://127.0.0.1:8090").rstrip("/")
EMAIL = os.environ.get("JIRA_EMAIL", "jira-test@example.com")
TOKEN = os.environ.get("JIRA_API_TOKEN", "gate-b-test-token")
PROJECT_KEY = os.environ.get("JIRA_PROJECT_KEY", "TST")

PASS = 0
FAIL = 0
SKIP = 0


def ok(test_id: str, desc: str) -> None:
    global PASS
    PASS += 1
    print(f"✅ {test_id} {desc}")


def bad(test_id: str, desc: str, detail: str = "") -> None:
    global FAIL
    FAIL += 1
    suffix = f" — {detail}" if detail else ""
    print(f"❌ {test_id} {desc}{suffix}")


def skip(test_id: str, desc: str, reason: str) -> None:
    global SKIP
    SKIP += 1
    print(f"⏭️  {test_id} {desc} ({reason})")


def basic_auth(email: str, token: str) -> str:
    raw = f"{email}:{token}".encode()
    return "Basic " + base64.b64encode(raw).decode()


def request(
    method: str,
    path: str,
    *,
    auth: str | None = None,
    body: dict | None = None,
    headers: dict | None = None,
) -> tuple[int, dict | str]:
    url = f"{BASE}{path}"
    hdrs = {"Accept": "application/json"}
    if auth:
        hdrs["Authorization"] = auth
    if headers:
        hdrs.update(headers)
    data = None
    if body is not None:
        data = json.dumps(body).encode()
        hdrs["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            status = resp.status
    except urllib.error.HTTPError as exc:
        status = exc.code
        raw = exc.read().decode("utf-8", errors="replace")
    except urllib.error.URLError as exc:
        return 0, str(exc.reason)

    try:
        return status, json.loads(raw) if raw else {}
    except json.JSONDecodeError:
        return status, raw[:300]


def jira_error(payload: dict | str) -> bool:
    if not isinstance(payload, dict):
        return False
    return bool(payload.get("errorMessages") or payload.get("errors"))


def main() -> int:
    print("=== Gate B-α Jira API Regression (RED phase) ===")
    print(f"BASE: {BASE}")
    print(f"USER: {EMAIL}")
    print("")

    # --- Auth ---
    status, _ = request("GET", "/rest/api/3/myself")
    if status == 401:
        ok("JIRA-L1-A01", "无认证访问 myself 返回 401")
    else:
        bad("JIRA-L1-A01", "无认证访问 myself 返回 401", f"got HTTP {status}")

    status, _ = request("GET", "/rest/api/3/myself", auth=basic_auth(EMAIL, "wrong-token"))
    if status == 401:
        ok("JIRA-L1-A02", "错误 Token 返回 401")
    else:
        bad("JIRA-L1-A02", "错误 Token 返回 401", f"got HTTP {status}")

    status, myself = request("GET", "/rest/api/3/myself", auth=basic_auth(EMAIL, TOKEN))
    account_id = ""
    if status == 200 and isinstance(myself, dict) and myself.get("accountId"):
        account_id = myself["accountId"]
        ok("JIRA-L1-A03", "Basic Auth myself 返回 accountId")
        ok("JIRA-L1-U01", "myself 含 displayName/emailAddress")
    else:
        bad("JIRA-L1-A03", "Basic Auth myself 返回 accountId", f"HTTP {status} {str(myself)[:120]}")
        bad("JIRA-L1-U01", "myself 含 displayName/emailAddress", f"HTTP {status}")

    if account_id:
        status, user = request(
            "GET",
            f"/rest/api/3/user?accountId={urllib.parse.quote(account_id)}",
            auth=basic_auth(EMAIL, TOKEN),
        )
        if status == 200 and isinstance(user, dict) and user.get("accountId"):
            ok("JIRA-L1-U02", "按 accountId 查用户")
        else:
            bad("JIRA-L1-U02", "按 accountId 查用户", f"HTTP {status}")

    # --- Project ---
    status, projects = request(
        "GET", "/rest/api/3/project/search", auth=basic_auth(EMAIL, TOKEN)
    )
    if status == 200 and isinstance(projects, dict) and "values" in projects:
        ok("JIRA-L1-P01", "项目搜索 values/total")
    else:
        bad("JIRA-L1-P01", "项目搜索 values/total", f"HTTP {status} {str(projects)[:120]}")

    status, project = request(
        "GET", f"/rest/api/3/project/{PROJECT_KEY}", auth=basic_auth(EMAIL, TOKEN)
    )
    if status == 200 and isinstance(project, dict) and project.get("key") == PROJECT_KEY:
        ok("JIRA-L1-P02", f"GET project/{PROJECT_KEY}")
    else:
        bad("JIRA-L1-P02", f"GET project/{PROJECT_KEY}", f"HTTP {status} {str(project)[:120]}")

    status, err = request("GET", "/rest/api/3/project/NOPE", auth=basic_auth(EMAIL, TOKEN))
    if status == 404 and jira_error(err):
        ok("JIRA-L1-P04", "不存在项目 404 + Jira 错误体")
    else:
        bad("JIRA-L1-P04", "不存在项目 404 + Jira 错误体", f"HTTP {status}")

    # --- Issue CRUD ---
    summary = f"gate-b-{uuid.uuid4().hex[:8]}"
    create_body = {
        "fields": {
            "project": {"key": PROJECT_KEY},
            "summary": summary,
            "issuetype": {"name": "Task"},
        }
    }
    status, created = request(
        "POST", "/rest/api/3/issue", auth=basic_auth(EMAIL, TOKEN), body=create_body
    )
    issue_key = ""
    if status == 201 and isinstance(created, dict) and created.get("key"):
        issue_key = created["key"]
        ok("JIRA-L1-I01", "创建 Issue 返回 id/key")
    else:
        bad("JIRA-L1-I01", "创建 Issue 返回 id/key", f"HTTP {status} {str(created)[:160]}")

    status, invalid = request(
        "POST",
        "/rest/api/3/issue",
        auth=basic_auth(EMAIL, TOKEN),
        body={"fields": {"project": {"key": PROJECT_KEY}, "issuetype": {"name": "Task"}}},
    )
    if status == 400 and jira_error(invalid):
        ok("JIRA-L1-I06", "缺少 summary 返回 400 errors")
    else:
        bad("JIRA-L1-I06", "缺少 summary 返回 400 errors", f"HTTP {status}")

    if issue_key:
        status, issue = request(
            "GET", f"/rest/api/3/issue/{issue_key}", auth=basic_auth(EMAIL, TOKEN)
        )
        fields = issue.get("fields", {}) if isinstance(issue, dict) else {}
        if status == 200 and fields.get("summary") == summary:
            ok("JIRA-L1-I02", f"GET issue/{issue_key}")
        else:
            bad("JIRA-L1-I02", f"GET issue/{issue_key}", f"HTTP {status}")

        status, _ = request(
            "PUT",
            f"/rest/api/3/issue/{issue_key}",
            auth=basic_auth(EMAIL, TOKEN),
            body={"fields": {"summary": summary + "-updated"}},
        )
        if status == 204:
            ok("JIRA-L1-I04", "更新 Issue 204")
        else:
            bad("JIRA-L1-I04", "更新 Issue 204", f"HTTP {status}")

        status, _ = request(
            "DELETE", f"/rest/api/3/issue/{issue_key}", auth=basic_auth(EMAIL, TOKEN)
        )
        if status == 204:
            ok("JIRA-L1-I05", "删除 Issue 204")
        else:
            bad("JIRA-L1-I05", "删除 Issue 204", f"HTTP {status}")

    print("")
    print(f"通过: {PASS}  失败: {FAIL}  跳过: {SKIP}")
    if FAIL == 0 and PASS > 0:
        print("")
        print("🟢 Gate B-α 全部通过 — 可以进入 B-β 或扩大范围")
        return 0
    print("")
    print("🔴 红灯阶段：失败属预期，实现 /rest/api/3 后再跑本脚本")
    return 1


if __name__ == "__main__":
    sys.exit(main())
