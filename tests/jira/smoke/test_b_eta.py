#!/usr/bin/env python3
"""Gate B-η — Issue fields, Version/Component, Changelog."""
from __future__ import annotations

import os
import sys
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


def main() -> int:
    print("=== Gate B-η Issue Fields / Version / Component / Changelog ===")
    print(f"BASE: {client.base}\n")

    project_key = client.project_key

    # --- Issue fields shell ---
    summary = f"gate-b-eta-{uuid.uuid4().hex[:8]}"
    status, created = client.create_task(summary)
    issue_key = created.get("key", "") if isinstance(created, dict) else ""
    if status != 201 or not issue_key:
        bad("JIRA-L1-FL06", "前置创建 Issue", f"HTTP {status}")
        print(f"\n通过: {PASS}  失败: {FAIL}")
        return 1

    status, issue = client.request("GET", f"/rest/api/3/issue/{issue_key}")
    fields = issue.get("fields", {}) if isinstance(issue, dict) else {}
    shell_ok = (
        status == 200
        and isinstance(fields.get("labels"), list)
        and isinstance(fields.get("components"), list)
        and isinstance(fields.get("fixVersions"), list)
        and isinstance(fields.get("versions"), list)
        and fields.get("priority", {}).get("name")
        and "created" in fields
    )
    if shell_ok:
        ok("JIRA-L1-FL06", "GET issue 标准 fields 壳")
    else:
        bad("JIRA-L1-FL06", "GET issue 标准 fields 壳", f"HTTP {status}")

    # --- Component API ---
    comp_name = f"Comp-{uuid.uuid4().hex[:6]}"
    status, comp = client.request(
        "POST",
        "/rest/api/3/component",
        body={"name": comp_name, "project": project_key},
    )
    comp_id = comp.get("id", "") if isinstance(comp, dict) else ""
    if status == 201 and comp_id and comp.get("name") == comp_name:
        ok("JIRA-L1-CM01", "POST /component 201")
    else:
        bad("JIRA-L1-CM01", "POST /component 201", f"HTTP {status}")

    status, components = client.request("GET", f"/rest/api/3/project/{project_key}/components")
    if status == 200 and isinstance(components, list) and any(
        isinstance(c, dict) and c.get("name") == comp_name for c in components
    ):
        ok("JIRA-L1-CM02", f"GET /project/{project_key}/components")
    else:
        bad("JIRA-L1-CM02", "GET project components", f"HTTP {status}")

    # --- Version API ---
    ver_name = f"Ver-{uuid.uuid4().hex[:6]}"
    status, project = client.request("GET", f"/rest/api/3/project/{project_key}")
    project_id = project.get("id", "") if isinstance(project, dict) else ""
    status, version = client.request(
        "POST",
        "/rest/api/3/version",
        body={"name": ver_name, "projectId": project_id},
    )
    ver_id = version.get("id", "") if isinstance(version, dict) else ""
    if status == 201 and ver_id and version.get("name") == ver_name:
        ok("JIRA-L1-VN01", "POST /version 201")
    else:
        bad("JIRA-L1-VN01", "POST /version 201", f"HTTP {status}")

    status, versions = client.request("GET", f"/rest/api/3/project/{project_key}/versions")
    if status == 200 and isinstance(versions, list) and any(
        isinstance(v, dict) and v.get("name") == ver_name for v in versions
    ):
        ok("JIRA-L1-VN02", f"GET /project/{project_key}/versions")
    else:
        bad("JIRA-L1-VN02", "GET project versions", f"HTTP {status}")

    # --- Changelog ---
    status, _ = client.request(
        "POST",
        f"/rest/api/3/issue/{issue_key}/transitions",
        body={"transition": {"id": "31"}},
    )
    status, changelog = client.request("GET", f"/rest/api/3/issue/{issue_key}/changelog")
    histories = changelog.get("histories", []) if isinstance(changelog, dict) else []
    if status == 200 and isinstance(histories, list) and changelog.get("total", 0) >= 1:
        ok("JIRA-L1-CL01", "GET /issue/changelog histories>=1")
    else:
        bad("JIRA-L1-CL01", "GET /issue/changelog", f"HTTP {status} total={changelog}")

    client.request("DELETE", f"/rest/api/3/issue/{issue_key}")

    print("")
    print(f"通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate B-η 全部通过")
        return 0
    print("🔴 Gate B-η 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
