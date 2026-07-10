#!/usr/bin/env python3
"""Gate B-ζ — Resolution meta, transition close/reopen, Legacy PATCH."""
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
LEGACY_ACCOUNT = os.environ.get("GATE_A_ACCOUNT", "Lincoln")
LEGACY_PASSWORD = os.environ.get("GATE_A_PASSWORD", "e10adc3949ba59abbe56e057f20f883e")


def ok(case_id: str, name: str) -> None:
    global PASS
    PASS += 1
    print(f"✅ {case_id} {name}")


def bad(case_id: str, name: str, detail: str = "") -> None:
    global FAIL
    FAIL += 1
    suffix = f" — {detail}" if detail else ""
    print(f"❌ {case_id} {name}{suffix}")


def legacy_post(path: str, data: dict, token: str, org_code: str) -> tuple[dict | str, int]:
    import json
    import urllib.error
    import urllib.request

    url = f"{client.base}/{path.lstrip('/')}"
    body = urllib.parse.urlencode(data).encode()
    hdrs = {
        "Content-Type": "application/x-www-form-urlencoded",
        "Authorization": f"Bearer {token}",
        "organizationCode": org_code,
    }
    req = urllib.request.Request(url, data=body, headers=hdrs, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
            raw = resp.read().decode()
            return json.loads(raw), resp.status
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode()
        try:
            return json.loads(raw), exc.code
        except json.JSONDecodeError:
            return raw[:200], exc.code


def legacy_login() -> tuple[str, str]:
    import json
    import urllib.error
    import urllib.request

    url = f"{client.base}/project/login/index"
    body = urllib.parse.urlencode(
        {"account": LEGACY_ACCOUNT, "password": LEGACY_PASSWORD}
    ).encode()
    req = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
            res = json.loads(resp.read().decode())
    except urllib.error.HTTPError as exc:
        return "", ""
    if res.get("code") != 200:
        return "", ""
    data = res.get("data") or {}
    token = (data.get("tokenList") or {}).get("accessToken", "")
    orgs = data.get("organizationList") or []
    org_code = orgs[0].get("code", "") if orgs else ""
    return token, org_code


def issue_resolution(issue_key: str) -> dict | None:
    status, issue = client.request("GET", f"/rest/api/3/issue/{issue_key}")
    if status != 200 or not isinstance(issue, dict):
        return None
    fields = issue.get("fields") or {}
    resolution = fields.get("resolution")
    return resolution if isinstance(resolution, dict) else None


def transition_id_for(issue_key: str, target_name: str) -> str:
    status, data = client.request("GET", f"/rest/api/3/issue/{issue_key}/transitions")
    if status != 200 or not isinstance(data, dict):
        return ""
    for item in data.get("transitions") or []:
        if not isinstance(item, dict):
            continue
        name = item.get("name") or item.get("to", {}).get("name")
        if isinstance(name, str) and name.lower() == target_name.lower():
            return str(item.get("id", ""))
    return ""


def main() -> int:
    print("=== Gate B-ζ Jira Resolution ===")
    print(f"BASE: {client.base}\n")

    # R01 — resolution catalog
    status, resolutions = client.request("GET", "/rest/api/3/resolution")
    names = []
    if status == 200 and isinstance(resolutions, list):
        names = [r.get("name", "") for r in resolutions if isinstance(r, dict)]
    if status == 200 and "Fixed" in names and "Duplicate" in names:
        ok("JIRA-L1-R01", "GET /resolution 标准列表")
    else:
        bad("JIRA-L1-R01", "GET /resolution 标准列表", f"HTTP {status} names={names[:5]}")

    summary = f"gate-b-res-{uuid.uuid4().hex[:8]}"
    status, created = client.create_task(summary)
    issue_key = created.get("key", "") if isinstance(created, dict) else ""
    if status != 201 or not issue_key:
        bad("JIRA-L1-R02", "开放 Issue resolution=null", "create failed")
        bad("JIRA-L1-T04", "Transition Done + resolution", "skipped")
        bad("JIRA-L1-R03", "关闭 Issue resolution 结构", "skipped")
        bad("JIRA-L1-T05", "Reopen resolution=null", "skipped")
        bad("JIRA-L1-R04", "Legacy PATCH 未关闭 400", "skipped")
        bad("JIRA-L1-R05", "Legacy PATCH 已关闭 200", "skipped")
        print(f"\n通过: {PASS}  失败: {FAIL}")
        return 1

    res_open = issue_resolution(issue_key)
    if res_open is None:
        ok("JIRA-L1-R02", "开放 Issue resolution=null")
    else:
        bad("JIRA-L1-R02", "开放 Issue resolution=null", str(res_open))

    done_id = transition_id_for(issue_key, "Done")
    if not done_id:
        bad("JIRA-L1-T04", "Transition Done + resolution 204", "no Done transition")
    else:
        status, _ = client.request(
            "POST",
            f"/rest/api/3/issue/{issue_key}/transitions",
            body={
                "transition": {"id": done_id},
                "fields": {"resolution": {"name": "Fixed"}},
            },
        )
        if status == 204:
            ok("JIRA-L1-T04", "Transition Done + resolution 204")
        else:
            bad("JIRA-L1-T04", "Transition Done + resolution 204", f"HTTP {status}")

    res_closed = issue_resolution(issue_key)
    if res_closed and res_closed.get("name") == "Fixed" and res_closed.get("id"):
        ok("JIRA-L1-R03", "关闭 Issue fields.resolution")
    else:
        bad("JIRA-L1-R03", "关闭 Issue fields.resolution", str(res_closed))

    reopen_id = transition_id_for(issue_key, "To Do")
    if not reopen_id:
        bad("JIRA-L1-T05", "Reopen transition + resolution=null", "no reopen transition")
    else:
        status, _ = client.request(
            "POST",
            f"/rest/api/3/issue/{issue_key}/transitions",
            body={"transition": {"id": reopen_id}},
        )
        res_reopen = issue_resolution(issue_key)
        if status == 204 and res_reopen is None:
            ok("JIRA-L1-T05", "Reopen transition + resolution=null")
        else:
            bad("JIRA-L1-T05", "Reopen transition + resolution=null", f"HTTP {status} res={res_reopen}")

    client.request("DELETE", f"/rest/api/3/issue/{issue_key}")

    # R04/R05 — Legacy PATCH resolution
    token, org_code = legacy_login()
    if not token or not org_code:
        bad("JIRA-L1-R04", "Legacy PATCH 未关闭 400", "login failed")
        bad("JIRA-L1-R05", "Legacy PATCH 已关闭 200", "login failed")
    else:
        suffix = uuid.uuid4().hex[:6]
        res, _ = legacy_post(
            "project/project/save",
            {"name": f"Res-{suffix}", "description": "resolution smoke"},
            token,
            org_code,
        )
        project_code = (res.get("data") or {}).get("code", "") if isinstance(res, dict) and res.get("code") == 200 else ""
        stage_code = ""
        if project_code:
            res, _ = legacy_post(
                "project/taskStages/index",
                {"projectCode": project_code},
                token,
                org_code,
            )
            lst = (res.get("data") or {}).get("list") or []
            stage_code = lst[0].get("code", "") if lst else ""

        task_code = ""
        if project_code and stage_code:
            res, _ = legacy_post(
                "project/task/save",
                {
                    "name": "Res Task",
                    "project_code": project_code,
                    "stage_code": stage_code,
                },
                token,
                org_code,
            )
            task_code = (res.get("data") or {}).get("code", "") if isinstance(res, dict) and res.get("code") == 200 else ""

        if not task_code:
            bad("JIRA-L1-R04", "Legacy PATCH 未关闭 400", "setup failed")
            bad("JIRA-L1-R05", "Legacy PATCH 已关闭 200", "setup failed")
        else:
            res, _ = legacy_post(
                "project/task/edit",
                {"taskCode": task_code, "resolution": "wont_fix"},
                token,
                org_code,
            )
            if isinstance(res, dict) and res.get("code") == 400:
                ok("JIRA-L1-R04", "Legacy PATCH 未关闭 400")
            else:
                bad("JIRA-L1-R04", "Legacy PATCH 未关闭 400", str(res)[:120])

            legacy_post(
                "project/task/taskDone",
                {"taskCode": task_code, "done": 1},
                token,
                org_code,
            )
            res, _ = legacy_post(
                "project/task/edit",
                {"taskCode": task_code, "resolution": "wont_fix"},
                token,
                org_code,
            )
            if isinstance(res, dict) and res.get("code") == 200:
                ok("JIRA-L1-R05", "Legacy PATCH 已关闭 200")
            else:
                bad("JIRA-L1-R05", "Legacy PATCH 已关闭 200", str(res)[:120])

    print("")
    print(f"通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate B-ζ 全部通过")
        return 0
    print("🔴 Gate B-ζ 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
