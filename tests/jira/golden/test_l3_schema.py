#!/usr/bin/env python3
"""Gate B Layer 3 — response structure contract (schemas.yaml driven)."""
from __future__ import annotations

import base64
import json
import os
import sys
import uuid
from pathlib import Path

import urllib.error
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

SCHEMAS = Path(__file__).resolve().parent / "schemas.yaml"
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
    body: dict | None = None,
) -> tuple[int, dict | list | str]:
    url = f"{BASE}{path}"
    hdrs = {"Accept": "application/json", "Authorization": basic_auth()}
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


def type_name(value: object) -> str:
    if value is None:
        return "null"
    if isinstance(value, bool):
        return "bool"
    if isinstance(value, int) and not isinstance(value, bool):
        return "int"
    if isinstance(value, float):
        return "float"
    if isinstance(value, str):
        return "str"
    if isinstance(value, list):
        return "list"
    if isinstance(value, dict):
        return "object"
    return type(value).__name__


def check_types(obj: dict, types: dict) -> str | None:
    for key, expected in types.items():
        if key not in obj:
            continue
        actual = type_name(obj[key])
        if actual != expected:
            return f"{key}: want {expected} got {actual}"
    return None


def validate_schema(entry: dict, payload: dict | list) -> str | None:
    if entry.get("response") == "list":
        if not isinstance(payload, list):
            return f"expected list got {type_name(payload)}"
        return None

    if not isinstance(payload, dict):
        return f"expected object got {type_name(payload)}"

    required = entry.get("required") or []
    missing = [k for k in required if k not in payload]
    if missing:
        return f"missing keys {missing}"

    err = check_types(payload, entry.get("types") or {})
    if err:
        return err

    nested = entry.get("nested") or {}
    for key, rules in nested.items():
        child = payload.get(key)
        if not isinstance(child, dict):
            return f"{key} not object"
        miss = [k for k in rules.get("required", []) if k not in child]
        if miss:
            return f"{key} missing {miss}"
        err = check_types(child, rules.get("types") or {})
        if err:
            return f"{key}.{err}"
    return None


def resolve_path(path: str) -> str:
    return path.replace("{projectKey}", PROJECT_KEY).replace("{issueKey}", issue_key or "TST-1")


def ensure_issue() -> bool:
    global issue_key
    status, created = request(
        "POST",
        "/rest/api/3/issue",
        body={
            "fields": {
                "project": {"key": PROJECT_KEY},
                "summary": f"l3-{uuid.uuid4().hex[:8]}",
                "issuetype": {"name": "Task"},
            }
        },
    )
    if status == 201 and isinstance(created, dict):
        issue_key = created.get("key", "")
    return bool(issue_key)


def run_entry(entry: dict) -> None:
    case_id = entry.get("id", "JIRA-L3-?")
    method = entry.get("method", "GET").upper()
    path = resolve_path(entry.get("path", ""))

    body = None
    if entry.get("body") == "jql":
        body = {"jql": f"project = {PROJECT_KEY}", "maxResults": 5}

    status, payload = request(method, path, body=body)
    if status != 200:
        bad(case_id, f"{method} {path}", f"HTTP {status}")
        return

    err = validate_schema(entry, payload)
    if err:
        bad(case_id, f"{method} {path}", err)
        return
    ok(case_id, f"{method} {path}")


def main() -> int:
    print("=== Gate B Layer 3 Schema Contract ===")
    print(f"BASE: {BASE}\n")

    if yaml is None:
        bad("JIRA-L3-00", "PyYAML 可用", "pip install pyyaml")
        return 1

    status, myself = request("GET", "/rest/api/3/myself")
    if status != 200:
        bad("JIRA-L3-00", "myself 前置", f"HTTP {status}")
        return 1

    if not ensure_issue():
        bad("JIRA-L3-00", "issue 前置", "create failed")
        return 1

    data = yaml.safe_load(SCHEMAS.read_text(encoding="utf-8"))
    for entry in data.get("schemas", []):
        if entry.get("setup_issue") and not issue_key:
            bad(entry.get("id", "?"), "setup_issue", "no issue_key")
            continue
        run_entry(entry)

    if issue_key:
        request("DELETE", f"/rest/api/3/issue/{issue_key}")

    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate B L3 Schema 全部通过")
        return 0
    print("🔴 Gate B L3 Schema 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
