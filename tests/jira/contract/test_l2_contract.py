#!/usr/bin/env python3
"""Gate B Layer 2 — response shape contract (driven by contract/allowlist.yaml)."""
from __future__ import annotations

import base64
import json
import os
import sys
import uuid
from pathlib import Path

import urllib.error
import urllib.parse
import urllib.request

try:
    import yaml  # type: ignore
except ImportError:
    yaml = None

PASS = 0
FAIL = 0

BASE = os.environ.get("JIRA_BASE_URL", "http://127.0.0.1:8090").rstrip("/")
EMAIL = os.environ.get("JIRA_EMAIL", "jira-test@example.com")
TOKEN = os.environ.get("JIRA_API_TOKEN", "gate-b-test-token")
PROJECT_KEY = os.environ.get("JIRA_PROJECT_KEY", "TST")

ALLOWLIST = Path(__file__).resolve().parent / "allowlist.yaml"
issue_key = ""


def ok(case_id: str, name: str) -> None:
    global PASS
    PASS += 1
    print(f"✅ {case_id} {name}")


def bad(case_id: str, name: str, detail: str = "") -> None:
    global FAIL
    FAIL += 1
    suffix = f" — {detail}" if detail else ""
    print(f"❌ {case_id} {name}{suffix}")


def basic_auth() -> str:
    raw = f"{EMAIL}:{TOKEN}".encode()
    return "Basic " + base64.b64encode(raw).decode()


def request(
    method: str,
    path: str,
    *,
    auth: str | None = None,
    body: dict | None = None,
) -> tuple[int, dict | str]:
    url = f"{BASE}{path}"
    hdrs = {"Accept": "application/json"}
    if auth:
        hdrs["Authorization"] = auth
    data = None
    if body is not None:
        data = json.dumps(body).encode()
        hdrs["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
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


def has_fields(obj: dict, fields: list[str]) -> bool:
    return all(f in obj for f in fields)


def jira_error(payload: dict | str) -> bool:
    return isinstance(payload, dict) and bool(
        payload.get("errorMessages") or payload.get("errors")
    )


def resolve_path(path: str, account_id: str) -> str:
    global issue_key
    return (
        path.replace("{projectKey}", PROJECT_KEY)
        .replace("{issueKey}", issue_key or "TST-1")
        .replace("{accountId}", urllib.parse.quote(account_id, safe=""))
    )


def run_allowlist_entry(entry: dict, account_id: str) -> None:
    global issue_key
    case_id = entry.get("id", "JIRA-L2-?")
    method = entry.get("method", "GET").upper()
    path = resolve_path(entry.get("path", ""), account_id)
    query_tpl = entry.get("query")
    if query_tpl:
        path = f"{path}?{resolve_path(query_tpl, account_id)}"
    expect_status = entry.get("expect_status", 200)
    auth_mode = entry.get("auth", "basic")

    auth = None
    if auth_mode == "basic":
        auth = basic_auth()
    elif auth_mode == "bad":
        auth = "Basic " + base64.b64encode(b"bad@creds:bad").decode()

    body = None
    if entry.get("body") == "invalid":
        body = {"fields": {"project": {"key": PROJECT_KEY}}}
    elif entry.get("body") == "jql":
        body = {"jql": f"project = {PROJECT_KEY}", "maxResults": 5}
    elif method == "POST" and "/issue" in path and expect_status == 201:
        body = {
            "fields": {
                "project": {"key": PROJECT_KEY},
                "summary": f"l2-{uuid.uuid4().hex[:8]}",
                "issuetype": {"name": "Task"},
            }
        }

    status, payload = request(method, path, auth=auth, body=body)

    if status != expect_status:
        bad(case_id, f"{method} {path}", f"HTTP {status} expected {expect_status}")
        return

    if status == 201 and isinstance(payload, dict) and payload.get("key"):
        issue_key = payload.get("key", issue_key)

    expect_fields = entry.get("expect_fields") or []
    if expect_fields:
        if isinstance(payload, dict) and not has_fields(payload, expect_fields):
            bad(case_id, f"fields {expect_fields}", str(list(payload.keys())[:8]))
            return
        if isinstance(payload, list) and expect_fields:
            bad(case_id, f"expected object fields {expect_fields}", "got list")

    if entry.get("expect_jira_error") and not jira_error(payload):
        bad(case_id, "Jira error body", str(payload)[:120])
        return

    ok(case_id, f"{method} {path}")


def test_shape_rules() -> None:
    """Additional structural rules beyond allowlist."""
    global issue_key

    if not issue_key:
        bad("JIRA-L2-S01", "issue create for shape", "no issue_key")
        return

    status, issue = request("GET", f"/rest/api/3/issue/{issue_key}", auth=basic_auth())
    fields = issue.get("fields", {}) if isinstance(issue, dict) else {}
    shape_ok = (
        status == 200
        and isinstance(issue.get("id"), str)
        and issue.get("key") == issue_key
        and isinstance(fields.get("labels"), list)
        and isinstance(fields.get("components"), list)
        and isinstance(fields.get("fixVersions"), list)
        and fields.get("priority", {}).get("name")
    )
    if shape_ok:
        ok("JIRA-L2-S01", "GET issue id/key + fields 数组壳")
    else:
        bad("JIRA-L2-S01", "GET issue shape", str(issue)[:120])

    status, changelog = request("GET", f"/rest/api/3/issue/{issue_key}/changelog", auth=basic_auth())
    if status == 200 and isinstance(changelog.get("histories"), list):
        ok("JIRA-L2-S02", "GET changelog histories[]")
    else:
        bad("JIRA-L2-S02", "GET changelog shape", str(changelog)[:80])

    status, _ = request("GET", "/rest/api/3/issue/NOPE-99999", auth=basic_auth())
    if status == 404:
        ok("JIRA-L2-S03", "404 不存在 issue")
    else:
        bad("JIRA-L2-S03", "404 不存在 issue", f"HTTP {status}")

    request("DELETE", f"/rest/api/3/issue/{issue_key}", auth=basic_auth())


def main() -> int:
    print("=== Gate B Layer 2 Contract ===")
    print(f"BASE: {BASE}\n")

    if yaml is None:
        bad("JIRA-L2-00", "PyYAML 可用", "pip install pyyaml")
        return 1

    status, myself = request("GET", "/rest/api/3/myself", auth=basic_auth())
    account_id = myself.get("accountId", "") if isinstance(myself, dict) else ""
    if status != 200 or not account_id:
        bad("JIRA-L2-00", "myself 前置", f"HTTP {status}")
        return 1

    data = yaml.safe_load(ALLOWLIST.read_text(encoding="utf-8"))
    for section in data.values():
        if not isinstance(section, list):
            continue
        for entry in section:
            if isinstance(entry, dict) and entry.get("id"):
                run_allowlist_entry(entry, account_id)

    if not issue_key:
        bad("JIRA-L2-00", "allowlist 未产出 issueKey", "I01 missing")
        return 1

    test_shape_rules()

    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate B L2 Contract 全部通过")
        return 0
    print("🔴 Gate B L2 Contract 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
