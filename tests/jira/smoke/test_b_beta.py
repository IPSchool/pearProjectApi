#!/usr/bin/env python3
"""Gate B-β regression — Search, Comment, Transition, User search."""
from __future__ import annotations

import base64
import json
import os
import sys
import uuid

import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("JIRA_BASE_URL", "http://127.0.0.1:8090").rstrip("/")
EMAIL = os.environ.get("JIRA_EMAIL", "jira-test@example.com")
TOKEN = os.environ.get("JIRA_API_TOKEN", "gate-b-test-token")
PROJECT_KEY = os.environ.get("JIRA_PROJECT_KEY", "TST")

PASS = 0
FAIL = 0


def ok(test_id: str, desc: str) -> None:
    global PASS
    PASS += 1
    print(f"✅ {test_id} {desc}")


def bad(test_id: str, desc: str, detail: str = "") -> None:
    global FAIL
    FAIL += 1
    suffix = f" — {detail}" if detail else ""
    print(f"❌ {test_id} {desc}{suffix}")


def basic_auth(email: str, token: str) -> str:
    raw = f"{email}:{token}".encode()
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
    print("=== Gate B-β Jira API Regression ===")
    print(f"BASE: {BASE}")
    print("")

    auth = basic_auth(EMAIL, TOKEN)

    status, users = request(
        "GET",
        f"/rest/api/3/user/search?query={urllib.parse.quote('jira-test')}",
        auth=auth,
    )
    if status == 200 and isinstance(users, dict) and isinstance(users.get("users"), list):
        ok("JIRA-L1-U03", "用户搜索 users[]")
    else:
        bad("JIRA-L1-U03", "用户搜索 users[]", f"HTTP {status}")

    status, search = request(
        "POST",
        "/rest/api/3/search",
        auth=auth,
        body={"jql": f"project = {PROJECT_KEY}", "startAt": 0, "maxResults": 10},
    )
    if status == 200 and isinstance(search, dict) and "issues" in search and "total" in search:
        ok("JIRA-L1-S01", "JQL project = TST")
    else:
        bad("JIRA-L1-S01", "JQL project = TST", f"HTTP {status}")

    status, _ = request(
        "POST",
        "/rest/api/3/search",
        auth=auth,
        body={"jql": "assignee = currentUser()", "startAt": 0, "maxResults": 10},
    )
    if status == 200:
        ok("JIRA-L1-S02", "JQL assignee = currentUser()")
    else:
        bad("JIRA-L1-S02", "JQL assignee = currentUser()", f"HTTP {status}")

    status, _ = request(
        "POST",
        "/rest/api/3/search",
        auth=auth,
        body={"jql": 'status = "To Do"', "startAt": 0, "maxResults": 10},
    )
    if status == 200:
        ok("JIRA-L1-S03", 'JQL status = "To Do"')
    else:
        bad("JIRA-L1-S03", 'JQL status = "To Do"', f"HTTP {status}")

    status, page = request(
        "POST",
        "/rest/api/3/search",
        auth=auth,
        body={"jql": f"project = {PROJECT_KEY}", "startAt": 0, "maxResults": 10},
    )
    if status == 200 and isinstance(page, dict) and page.get("startAt") == 0 and page.get("maxResults") == 10:
        ok("JIRA-L1-S04", "JQL 分页 startAt/maxResults")
    else:
        bad("JIRA-L1-S04", "JQL 分页", f"HTTP {status}")

    status, err = request(
        "POST",
        "/rest/api/3/search",
        auth=auth,
        body={"jql": "invalid!!!"},
    )
    if status == 400 and jira_error(err):
        ok("JIRA-L1-S05", "非法 JQL 400")
    else:
        bad("JIRA-L1-S05", "非法 JQL 400", f"HTTP {status}")

    summary = f"gate-b-beta-{uuid.uuid4().hex[:8]}"
    status, created = request(
        "POST",
        "/rest/api/3/issue",
        auth=auth,
        body={
            "fields": {
                "project": {"key": PROJECT_KEY},
                "summary": summary,
                "issuetype": {"name": "Task"},
            }
        },
    )
    issue_key = created.get("key", "") if isinstance(created, dict) else ""
    if not issue_key:
        bad("JIRA-L1-C01", "POST comment 前置创建 Issue", f"HTTP {status}")
        bad("JIRA-L1-C02", "GET comments", "skipped")
        bad("JIRA-L1-T01", "GET transitions", "skipped")
        bad("JIRA-L1-T02", "POST transition", "skipped")
        bad("JIRA-L1-T03", "非法 transition", "skipped")
    else:
        status, comment = request(
            "POST",
            f"/rest/api/3/issue/{issue_key}/comment",
            auth=auth,
            body={"body": JiraCommentService_text(summary)},
        )
        if status == 201 and isinstance(comment, dict) and comment.get("id"):
            ok("JIRA-L1-C01", "POST comment 201 + id")
        else:
            bad("JIRA-L1-C01", "POST comment", f"HTTP {status} {str(comment)[:120]}")

        status, comments = request(
            "GET",
            f"/rest/api/3/issue/{issue_key}/comment",
            auth=auth,
        )
        if status == 200 and isinstance(comments, dict) and isinstance(comments.get("comments"), list):
            ok("JIRA-L1-C02", "GET comments[]")
        else:
            bad("JIRA-L1-C02", "GET comments[]", f"HTTP {status}")

        status, transitions = request(
            "GET",
            f"/rest/api/3/issue/{issue_key}/transitions",
            auth=auth,
        )
        transition_id = ""
        if status == 200 and isinstance(transitions, dict) and transitions.get("transitions"):
            transition_id = transitions["transitions"][0].get("id", "")
            ok("JIRA-L1-T01", "GET transitions")
        else:
            bad("JIRA-L1-T01", "GET transitions", f"HTTP {status}")

        if transition_id:
            status, _ = request(
                "POST",
                f"/rest/api/3/issue/{issue_key}/transitions",
                auth=auth,
                body={"transition": {"id": transition_id}},
            )
            if status == 204:
                ok("JIRA-L1-T02", "POST transition 204")
            else:
                bad("JIRA-L1-T02", "POST transition 204", f"HTTP {status}")

        status, _ = request(
            "POST",
            f"/rest/api/3/issue/{issue_key}/transitions",
            auth=auth,
            body={"transition": {"id": "99999"}},
        )
        if status == 400 and jira_error(_):
            ok("JIRA-L1-T03", "非法 transition 400")
        else:
            bad("JIRA-L1-T03", "非法 transition 400", f"HTTP {status}")

        request("DELETE", f"/rest/api/3/issue/{issue_key}", auth=auth)

    print("")
    print(f"通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate B-β 全部通过")
        return 0
    print("🔴 Gate B-β 存在失败")
    return 1


def JiraCommentService_text(text: str) -> dict:
    return {
        "type": "doc",
        "version": 1,
        "content": [
            {
                "type": "paragraph",
                "content": [{"type": "text", "text": f"comment on {text}"}],
            }
        ],
    }


if __name__ == "__main__":
    sys.exit(main())
