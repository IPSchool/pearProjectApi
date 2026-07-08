#!/usr/bin/env python3
"""Gate B-ε — Watcher, Issue Link, Webhook, Filter."""
from __future__ import annotations

import os
import sys
import uuid
import urllib.parse

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "lib"))
from jira_client import JiraClient  # noqa: E402

PASS = 0
FAIL = 0

client = JiraClient()


def ok(case_id: str, name: str) -> None:
    global PASS
    PASS += 1
    print(f"✅ {case_id} {name}")


def bad(case_id: str, name: str, detail: str = "") -> None:
    global FAIL
    FAIL += 1
    suffix = f" — {detail}" if detail else ""
    print(f"❌ {case_id} {name}{suffix}")


def main() -> int:
    print("=== Gate B-ε Jira Watcher / Link / Webhook / Filter ===")
    print(f"BASE: {client.base}\n")

    summary_a = f"epsilon-a-{uuid.uuid4().hex[:8]}"
    summary_b = f"epsilon-b-{uuid.uuid4().hex[:8]}"
    status, created_a = client.create_task(summary_a)
    status_b, created_b = client.create_task(summary_b)
    key_a = created_a.get("key", "") if isinstance(created_a, dict) else ""
    key_b = created_b.get("key", "") if isinstance(created_b, dict) else ""
    if status != 201 or status_b != 201 or not key_a or not key_b:
        bad("JIRA-L1-WH01", "前置创建 Issue", f"a={status} b={status_b}")
        print(f"\n通过: {PASS}  失败: {FAIL}")
        return 1

    # --- Watcher ---
    status, myself = client.request("GET", "/rest/api/3/myself")
    account_id = myself.get("accountId", "") if isinstance(myself, dict) else ""
    if status == 200 and account_id:
        ok("JIRA-L1-WH01", "myself accountId 前置")
    else:
        bad("JIRA-L1-WH01", "myself accountId 前置", f"HTTP {status}")
        account_id = ""

    if account_id:
        status, _ = client.request(
            "POST",
            f"/rest/api/3/issue/{key_a}/watchers",
            body=account_id,
        )
        if status == 204:
            ok("JIRA-L1-WH02", "POST watcher 204")
        else:
            bad("JIRA-L1-WH02", "POST watcher 204", f"HTTP {status}")

        status, watchers = client.request("GET", f"/rest/api/3/issue/{key_a}/watchers")
        count = watchers.get("watchCount", 0) if isinstance(watchers, dict) else 0
        if status == 200 and count >= 1:
            ok("JIRA-L1-WH03", "GET watchers watchCount>=1")
        else:
            bad("JIRA-L1-WH03", "GET watchers", f"HTTP {status} count={count}")

        status, _ = client.request(
            "DELETE",
            f"/rest/api/3/issue/{key_a}/watchers?accountId={urllib.parse.quote(account_id, safe='')}",
        )
        if status == 204:
            ok("JIRA-L1-WH04", "DELETE watcher 204")
        else:
            bad("JIRA-L1-WH04", "DELETE watcher 204", f"HTTP {status}")

    # --- Issue Link ---
    status, link = client.request(
        "POST",
        "/rest/api/3/issueLink",
        body={
            "type": {"name": "Blocks"},
            "inwardIssue": {"key": key_b},
            "outwardIssue": {"key": key_a},
        },
    )
    link_id = link.get("id", "") if isinstance(link, dict) else ""
    if status == 201 and link_id:
        ok("JIRA-L1-LK01", "POST issueLink 201")
    else:
        bad("JIRA-L1-LK01", "POST issueLink 201", f"HTTP {status} {str(link)[:120]}")

    status, issue = client.request("GET", f"/rest/api/3/issue/{key_a}")
    links = issue.get("fields", {}).get("issuelinks", []) if isinstance(issue, dict) else []
    if status == 200 and isinstance(links, list) and len(links) >= 1:
        ok("JIRA-L1-LK02", "GET issue fields.issuelinks")
    else:
        bad("JIRA-L1-LK02", "GET issue issuelinks", f"HTTP {status} len={len(links) if isinstance(links, list) else 'n/a'}")

    if link_id:
        status, _ = client.request("DELETE", f"/rest/api/3/issueLink/{link_id}")
        if status == 204:
            ok("JIRA-L1-LK03", "DELETE issueLink 204")
        else:
            bad("JIRA-L1-LK03", "DELETE issueLink 204", f"HTTP {status}")

    # --- Filter ---
    filter_name = f"GateB Filter {uuid.uuid4().hex[:6]}"
    status, filt = client.request(
        "POST",
        "/rest/api/3/filter",
        body={
            "name": filter_name,
            "description": "Gate B epsilon",
            "jql": f"project = {client.project_key}",
        },
    )
    filter_id = filt.get("id", "") if isinstance(filt, dict) else ""
    if status == 201 and filter_id and filt.get("jql"):
        ok("JIRA-L1-FL01", "POST filter 201")
    else:
        bad("JIRA-L1-FL01", "POST filter 201", f"HTTP {status}")

    if filter_id:
        status, got = client.request("GET", f"/rest/api/3/filter/{filter_id}")
        if status == 200 and got.get("name") == filter_name:
            ok("JIRA-L1-FL02", "GET filter by id")
        else:
            bad("JIRA-L1-FL02", "GET filter by id", f"HTTP {status}")

        status, search = client.request("GET", f"/rest/api/3/filter/search?filterName=GateB")
        values = search.get("values", []) if isinstance(search, dict) else []
        if status == 200 and isinstance(values, list) and len(values) >= 1:
            ok("JIRA-L1-FL03", "GET filter/search")
        else:
            bad("JIRA-L1-FL03", "GET filter/search", f"HTTP {status}")

        status, _ = client.request(
            "PUT",
            f"/rest/api/3/filter/{filter_id}",
            body={"description": "updated"},
        )
        if status == 200:
            ok("JIRA-L1-FL04", "PUT filter")
        else:
            bad("JIRA-L1-FL04", "PUT filter", f"HTTP {status}")

        status, _ = client.request("DELETE", f"/rest/api/3/filter/{filter_id}")
        if status == 204:
            ok("JIRA-L1-FL05", "DELETE filter 204")
        else:
            bad("JIRA-L1-FL05", "DELETE filter 204", f"HTTP {status}")

    # --- Webhook ---
    status, hook = client.request(
        "POST",
        "/rest/webhooks/1.0/webhook",
        body={
            "name": "Gate B Hook",
            "url": "https://example.com/jira-webhook",
            "events": ["jira:issue_created", "jira:issue_updated"],
            "filters": f"project = {client.project_key}",
        },
    )
    hook_id = hook.get("id") if isinstance(hook, dict) else None
    if status == 201 and hook_id and hook.get("url"):
        ok("JIRA-L1-WB01", "POST webhook 201")
    else:
        bad("JIRA-L1-WB01", "POST webhook 201", f"HTTP {status} {str(hook)[:120]}")

    status, hooks = client.request("GET", "/rest/webhooks/1.0/webhook")
    if status == 200 and isinstance(hooks, list) and len(hooks) >= 1:
        ok("JIRA-L1-WB02", "GET webhooks list")
    else:
        bad("JIRA-L1-WB02", "GET webhooks list", f"HTTP {status}")

    if hook_id:
        status, _ = client.request("DELETE", f"/rest/webhooks/1.0/webhook/{hook_id}")
        if status == 204:
            ok("JIRA-L1-WB03", "DELETE webhook 204")
        else:
            bad("JIRA-L1-WB03", "DELETE webhook 204", f"HTTP {status}")

    # cleanup issues
    client.request("DELETE", f"/rest/api/3/issue/{key_a}")
    client.request("DELETE", f"/rest/api/3/issue/{key_b}")

    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL:
        print("🔴 Gate B-ε 存在失败")
        return 1
    print("🟢 Gate B-ε 全部通过")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
