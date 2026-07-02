#!/usr/bin/env python3
"""Gate B-γ — extended Jira REST coverage (implemented endpoints only)."""
from __future__ import annotations

import os
import sys
import uuid

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "lib"))
from jira_client import JiraClient  # noqa: E402

PASS = 0
FAIL = 0
SKIP = 0

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


def skip(case_id: str, name: str, reason: str) -> None:
    global SKIP
    SKIP += 1
    print(f"⏭️  {case_id} {name} ({reason})")


def main() -> int:
    print("=== Gate B-γ Jira API Extended ===")
    print(f"BASE: {client.base}\n")

    # --- serverInfo (Layer 4 client bootstrap) ---
    for path in ("/rest/api/2/serverInfo", "/rest/api/3/serverInfo"):
        status, info = client.request("GET", path, auth=False)
        if status == 200 and isinstance(info, dict) and info.get("version"):
            ok("JIRA-L1-SV01", f"GET {path}")
        else:
            bad("JIRA-L1-SV01", f"GET {path}", f"HTTP {status}")

    # --- Issue by numeric id ---
    summary = f"gamma-{uuid.uuid4().hex[:8]}"
    status, created = client.create_task(summary)
    issue_key = created.get("key", "") if isinstance(created, dict) else ""
    issue_id = str(created.get("id", "")) if isinstance(created, dict) else ""
    if issue_key and issue_id:
        ok("JIRA-L1-I01b", "创建 Issue 含 id")
        status, by_key = client.request("GET", f"/rest/api/3/issue/{issue_key}")
        status2, by_id = client.request("GET", f"/rest/api/3/issue/{issue_id}")
        fields_k = by_key.get("fields", {}) if isinstance(by_key, dict) else {}
        fields_i = by_id.get("fields", {}) if isinstance(by_id, dict) else {}
        if status == 200 and status2 == 200 and fields_k.get("summary") == summary and fields_i.get("summary") == summary:
            ok("JIRA-L1-I02b", "Key/ID 读取一致")
            ok("JIRA-L1-I03", "按 ID 获取 Issue")
        else:
            bad("JIRA-L1-I03", "按 ID 获取 Issue", f"key={status} id={status2}")
    else:
        bad("JIRA-L1-I01b", "创建 Issue 含 id", f"HTTP {status}")

    # --- Issue types ---
    status, bug = client.request(
        "POST",
        "/rest/api/3/issue",
        body={
            "fields": {
                "project": {"key": client.project_key},
                "summary": f"bug-{uuid.uuid4().hex[:6]}",
                "issuetype": {"name": "Bug"},
            }
        },
    )
    bug_key = bug.get("key", "") if isinstance(bug, dict) else ""
    if status == 201 and bug_key:
        ok("JIRA-L1-I08", "创建 Bug 类型 Issue")
        client.request("DELETE", f"/rest/api/3/issue/{bug_key}")
    else:
        bad("JIRA-L1-I08", "创建 Bug 类型 Issue", f"HTTP {status} {str(bug)[:100]}")

    skip("JIRA-L1-I07", "Sub-task 创建", "路线图 B-δ")

    # --- Search extended ---
    status, search = client.request(
        "POST",
        "/rest/api/3/search",
        body={"jql": f"project = {client.project_key} AND summary ~ \"gamma\"", "startAt": 0, "maxResults": 1},
    )
    if status == 200 and isinstance(search, dict) and search.get("maxResults") == 1:
        ok("JIRA-L1-S06", "JQL 关键词 + maxResults=1")
    else:
        bad("JIRA-L1-S06", "JQL 关键词", f"HTTP {status}")

    skip("JIRA-L1-S07", "JQL 无结果过滤", "未知 project 条件暂未过滤 — 路线图")

    # --- Project search structure ---
    status, projects = client.request("GET", "/rest/api/3/project/search?maxResults=50")
    if status == 200 and isinstance(projects, dict) and isinstance(projects.get("values"), list) and "total" in projects:
        ok("JIRA-L1-P01b", "project/search 分页字段")
        has_tst = any(p.get("key") == client.project_key for p in projects["values"] if isinstance(p, dict))
        if has_tst:
            ok("JIRA-L1-P02b", "project/search 含 TST")
        else:
            bad("JIRA-L1-P02b", "project/search 含 TST", "TST not in values")
    else:
        bad("JIRA-L1-P01b", "project/search 分页字段", f"HTTP {status}")

    skip("JIRA-L1-P03", "POST 创建项目", "未实现")

    # --- Comments structure ---
    if issue_key:
        status, comments = client.request("GET", f"/rest/api/3/issue/{issue_key}/comment")
        if status == 200 and isinstance(comments, dict) and isinstance(comments.get("comments"), list):
            ok("JIRA-L1-C02b", "comments 结构 total/comments")
        else:
            bad("JIRA-L1-C02b", "comments 结构", f"HTTP {status}")

        status, transitions = client.request("GET", f"/rest/api/3/issue/{issue_key}/transitions")
        if status == 200 and isinstance(transitions, dict):
            trans = transitions.get("transitions") or []
            if trans and all("id" in t and "name" in t for t in trans if isinstance(t, dict)):
                ok("JIRA-L1-T01b", "transitions 含 id/name")
            else:
                bad("JIRA-L1-T01b", "transitions 结构", "missing id/name")
        else:
            bad("JIRA-L1-T01b", "transitions 结构", f"HTTP {status}")

    # --- 404 issue ---
    status, nf = client.request("GET", "/rest/api/3/issue/NOPE-999999")
    if status == 404 and JiraClient.jira_error(nf):
        ok("JIRA-L1-I09", "不存在 Issue 404")
    else:
        bad("JIRA-L1-I09", "不存在 Issue 404", f"HTTP {status}")

    skip("JIRA-L1-AT01", "附件上传", "路线图 B-β+")
    skip("JIRA-L1-W01", "Worklog POST", "路线图 B-δ")
    skip("JIRA-L1-W02", "Worklog GET", "路线图 B-δ")
    skip("JIRA-L1-A04", "附件无 X-Atlassian-Token", "未实现 attachment API")
    skip("JIRA-L1-A05", "附件 X-Atlassian-Token", "未实现 attachment API")

    # --- User edge ---
    status, users = client.request("GET", "/rest/api/3/user/search?query=")
    if status in (200, 400):
        ok("JIRA-L1-U04", "空 query 用户搜索（200 或 400）")
    else:
        bad("JIRA-L1-U04", "空 query 用户搜索", f"HTTP {status}")

    status, myself = client.request("GET", "/rest/api/3/myself")
    if status == 200 and isinstance(myself, dict) and myself.get("active") is True:
        ok("JIRA-L1-U05", "myself.active=true")
    else:
        bad("JIRA-L1-U05", "myself.active", f"HTTP {status}")

    # cleanup
    if issue_key:
        client.request("DELETE", f"/rest/api/3/issue/{issue_key}")

    print(f"\n通过: {PASS}  失败: {FAIL}  跳过: {SKIP}")
    if FAIL == 0:
        print("🟢 Gate B-γ 全部通过")
        return 0
    print("🔴 Gate B-γ 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
