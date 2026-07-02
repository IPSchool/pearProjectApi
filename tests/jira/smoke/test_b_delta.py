#!/usr/bin/env python3
"""Gate B-δ — additional Jira coverage (gaps, negatives, pagination, OpenAPI cross-check)."""
from __future__ import annotations

import json
import os
import sys
import urllib.request
import uuid

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


def check(case_id: str, name: str, cond: bool, detail: str = "") -> None:
    if cond:
        ok(case_id, name)
    else:
        bad(case_id, name, detail)


def main() -> int:
    print("=== Gate B-δ Jira API Additional ===")
    print(f"BASE: {client.base}\n")

    # Previously untested routes
    status, info = client.request("GET", "/rest/api/latest/serverInfo", auth=False)
    if status == 200 and isinstance(info, dict) and info.get("version"):
        ok("JIRA-L1-SV02", "GET /rest/api/latest/serverInfo")
    else:
        bad("JIRA-L1-SV02", "GET /rest/api/latest/serverInfo", f"HTTP {status}")

    status, fields = client.request("GET", "/rest/api/3/field")
    if status == 200 and isinstance(fields, list) and len(fields) >= 1:
        ok("JIRA-L1-F01", "GET /rest/api/3/field")
    else:
        bad("JIRA-L1-F01", "GET /rest/api/3/field", f"HTTP {status}")

    # Negative auth
    status, _ = client.request("GET", "/rest/api/3/myself", auth=False)
    check("JIRA-L1-D01", "myself 无认证 401", status == 401)

    status, _ = client.request("GET", "/rest/api/3/project/search", auth=False)
    check("JIRA-L1-D02", "project/search 无认证 401", status == 401)

    status, body = client.request(
        "POST",
        "/rest/api/3/search",
        body={"jql": f"project = {client.project_key}", "startAt": 0, "maxResults": 0},
    )
    check("JIRA-L1-D03", "JQL maxResults=0", status == 200 and isinstance(body, dict))

    status, body = client.request(
        "POST",
        "/rest/api/3/search",
        body={"jql": f"project = {client.project_key} ORDER BY created DESC", "startAt": 0, "maxResults": 50},
    )
    issues = body.get("issues", []) if isinstance(body, dict) else []
    check("JIRA-L1-D04", "JQL ORDER BY created", status == 200 and isinstance(issues, list))

    status, body = client.request(
        "POST",
        "/rest/api/3/search",
        body={"jql": 'summary ~ "gamma"', "maxResults": 5},
    )
    check("JIRA-L1-D05", "JQL summary~ 二次校验", status == 200)

    status, _ = client.request(
        "POST",
        "/rest/api/3/search",
        body={"jql": "project = NONEXISTENT999", "maxResults": 1},
    )
    check("JIRA-L1-D06", "JQL 未知 project", status == 200)

    # Issue lifecycle extras
    summary = f"delta-{uuid.uuid4().hex[:8]}"
    status, created = client.create_task(summary)
    key = created.get("key", "") if isinstance(created, dict) else ""
    if not key:
        bad("JIRA-L1-D07", "创建 Issue for delta", f"HTTP {status}")
    else:
        ok("JIRA-L1-D07", "创建 Issue for delta")

        status, read = client.request("GET", f"/rest/api/3/issue/{key}?fields=summary,status")
        fields = read.get("fields", {}) if isinstance(read, dict) else {}
        check("JIRA-L1-D08", "GET issue fields 子集", status == 200 and fields.get("summary") == summary)

        status, _ = client.request(
            "PUT",
            f"/rest/api/3/issue/{key}",
            body={"fields": {"summary": summary + "-updated"}},
        )
        check("JIRA-L1-D09", "PUT issue summary", status in (204, 200))

        status, trans = client.request("GET", f"/rest/api/3/issue/{key}/transitions")
        transitions = trans.get("transitions", []) if isinstance(trans, dict) else []
        check("JIRA-L1-D10", "transitions 非空或结构正确", status == 200 and isinstance(transitions, list))

        status, comment = client.request(
            "POST",
            f"/rest/api/3/issue/{key}/comment",
            body={"body": "delta comment"},
        )
        check("JIRA-L1-D11", "POST comment body 字符串", status == 201 and isinstance(comment, dict))

        status, comments = client.request("GET", f"/rest/api/3/issue/{key}/comment")
        total = comments.get("total", 0) if isinstance(comments, dict) else 0
        check("JIRA-L1-D12", "GET comments total>=1", status == 200 and total >= 1)

        status, wl = client.request(
            "POST",
            f"/rest/api/3/issue/{key}/worklog",
            body={
                "timeSpentSeconds": 120,
                "started": "2024-06-01T10:00:00.000+0000",
                "comment": client.adf_comment("delta wl"),
            },
        )
        check("JIRA-L1-D13", "POST worklog 120s", status in (200, 201))

        status, wls = client.request("GET", f"/rest/api/3/issue/{key}/worklog")
        worklogs = wls.get("worklogs", []) if isinstance(wls, dict) else []
        check("JIRA-L1-D14", "GET worklog total>=1", status == 200 and len(worklogs) >= 1)

        status, _ = client.request("DELETE", f"/rest/api/3/issue/{key}")
        check("JIRA-L1-D15", "DELETE issue cleanup", status in (204, 200))

    status, _ = client.request("GET", "/rest/api/3/issue/NOPE-99999")
    check("JIRA-L1-D16", "不存在 issue 404", status == 404)

    status, _ = client.request("GET", "/rest/api/3/user?accountId=invalid-account-id")
    check("JIRA-L1-D17", "无效 accountId", status in (400, 404))

    status, users = client.request("GET", "/rest/api/3/user/search?query=jira-test")
    user_list = users.get("users", []) if isinstance(users, dict) else users
    check("JIRA-L1-D18", "user/search query=jira-test", status == 200 and isinstance(user_list, list) and len(user_list) >= 1)

    status, proj = client.request("GET", f"/rest/api/3/project/{client.project_key}")
    check("JIRA-L1-D19", "project key 大小写", status == 200 and proj.get("key") == client.project_key)

    status, search = client.request("GET", "/rest/api/3/project/search?maxResults=1&startAt=0")
    check("JIRA-L1-D20", "project/search 分页", status == 200 and "total" in (search if isinstance(search, dict) else {}))

    # OpenAPI lists Jira paths
    with urllib.request.urlopen(f"{client.base}/swagger-spec", timeout=15) as resp:
        spec = json.loads(resp.read().decode())
    jira_paths = [p for p in spec.get("paths", {}) if p.startswith("/rest/api/")]
    check("JIRA-L1-D21", "OpenAPI 含 Jira 路径", len(jira_paths) >= 15)

    status, _ = client.request(
        "POST",
        "/rest/api/3/issue",
        body={"fields": {"project": {"key": client.project_key}, "summary": ""}},
    )
    check("JIRA-L1-D22", "空 summary 400", status == 400)

    status, _ = client.request(
        "POST",
        "/rest/api/3/issue",
        body={"fields": {"project": {"key": "NOPE"}, "summary": "x", "issuetype": {"name": "Task"}}},
    )
    check("JIRA-L1-D23", "无效 project 400/404", status in (400, 404))

    status, myself = client.request("GET", "/rest/api/3/myself")
    check("JIRA-L1-D24", "myself email 格式", status == 200 and "@" in (myself.get("emailAddress") or ""))

    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL == 0:
        print("🟢 Gate B-δ 全部通过")
        return 0
    print("🔴 Gate B-δ 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
