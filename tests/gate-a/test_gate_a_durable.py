#!/usr/bin/env python3
"""Gate A Durable — semantic CRUD / auth tests (not check_no500 / check_routed)."""
from __future__ import annotations

import os
import sys
import uuid

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "lib"))
from legacy_client import LegacyClient  # noqa: E402

PASS = 0
FAIL = 0

client = LegacyClient()
project_code = ""
stage_code = ""


def check(case_id: str, name: str, ok: bool, detail: str = "") -> None:
    global PASS, FAIL
    if ok:
        PASS += 1
        print(f"✅ {case_id} {name}")
    else:
        FAIL += 1
        suffix = f" — {detail}" if detail else ""
        print(f"❌ {case_id} {name}{suffix}")


def setup() -> bool:
    global project_code, stage_code
    if not client.ok(client.login()):
        return False
    suffix = uuid.uuid4().hex[:8]
    res, _ = client.post(
        "project/project/save",
        {"name": f"Dur-{suffix}", "description": "durable gate a"},
    )
    if not client.ok(res):
        return False
    project_code = (res.get("data") or {}).get("code", "")
    res, _ = client.post("project/taskStages/index", {"projectCode": project_code})
    lst = (res.get("data") or {}).get("list") or []
    stage_code = lst[0].get("code", "") if lst else ""
    return bool(project_code and stage_code)


def test_task_delete_roundtrip() -> None:
    res, _ = client.post(
        "project/task/save",
        {"name": "del-me", "project_code": project_code, "stage_code": stage_code},
    )
    code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not code:
        check("HV-DUR-01", "任务彻底删除", False, "setup failed")
        return
    res, _ = client.post("project/task/delete", {"taskCode": code})
    if not client.ok(res):
        check("HV-DUR-01", "任务彻底删除", False, "delete failed")
        return
    res, _ = client.post("project/task/read", {"taskCode": code})
    check(
        "HV-DUR-01",
        "任务彻底删除后 read 失败",
        isinstance(res, dict) and res.get("code") in (400, 404),
        str(res.get("code")),
    )


def test_comment_delete_roundtrip() -> None:
    res, _ = client.post(
        "project/task/save",
        {"name": "cmt-del", "project_code": project_code, "stage_code": stage_code},
    )
    task_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not task_code:
        check("HV-DUR-02", "评论删除往返", False, "task setup")
        return
    res, _ = client.post(
        "project/task/createComment",
        {"taskCode": task_code, "comment": "durable comment"},
    )
    if not client.ok(res):
        check("HV-DUR-02", "评论删除往返", False, "create comment")
        return
    data = res.get("data")
    log_code = ""
    if isinstance(data, dict):
        log_code = data.get("code", "")
    elif data is True:
        res2, _ = client.post(
            "project/task/taskLog",
            {"taskCode": task_code, "comment": 1, "all": 1},
        )
        lst = (res2.get("data") or {}).get("list") or []
        if lst:
            log_code = lst[-1].get("code", "")
    if not log_code:
        check("HV-DUR-02", "评论删除往返", False, "create comment")
        return
    res, _ = client.post("project/task/deleteComment", {"logCode": log_code})
    check("HV-DUR-02", "评论删除往返", client.ok(res))


def test_tag_edit_delete_roundtrip() -> None:
    tag_name = f"dur-{uuid.uuid4().hex[:4]}"
    res, _ = client.post(
        "project/taskTag/save",
        {"projectCode": project_code, "name": tag_name, "color": "blue"},
    )
    tag_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not tag_code:
        check("HV-DUR-03", "标签 edit/delete", False, "create tag")
        return
    new_name = tag_name + "-x"
    res, _ = client.post(
        "project/taskTag/edit",
        {"tagCode": tag_code, "name": new_name, "color": "green"},
    )
    if not client.ok(res):
        check("HV-DUR-03", "标签 edit/delete", False, "edit")
        return
    res, _ = client.post("project/taskTag/index", {"projectCode": project_code})
    data = res.get("data") if client.ok(res) else None
    if isinstance(data, dict):
        tags = data.get("list") or []
    elif isinstance(data, list):
        tags = data
    else:
        tags = []
    names = [t.get("name") for t in tags if isinstance(t, dict)]
    if new_name not in names:
        check("HV-DUR-03", "标签 edit/delete", False, "name not updated")
        return
    res, _ = client.post("project/taskTag/delete", {"tagCode": tag_code})
    check("HV-DUR-03", "标签 edit/delete", client.ok(res))


def test_logout_invalidates_token() -> None:
    tmp = LegacyClient()
    if not tmp.ok(tmp.login()):
        check("HV-DUR-04", "登出后 token 失效", False, "login")
        return
    res, _ = tmp.post("project/login/_out", {})
    if not tmp.ok(res):
        check("HV-DUR-04", "登出后 token 失效", False, "_out")
        return
    res, status = tmp.post("project/project/selfList", {"page": 1, "pageSize": 1})
    rejected = status in (401, 403) or (
        isinstance(res, dict) and res.get("code") in (401, 403)
    )
    check("HV-DUR-04", "登出后 token 失效", rejected, str(res)[:80])
    client.login()


def test_set_parent_child() -> None:
    res, _ = client.post(
        "project/task/save",
        {"name": "parent", "project_code": project_code, "stage_code": stage_code},
    )
    parent = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    res, _ = client.post(
        "project/task/save",
        {"name": "child", "project_code": project_code, "stage_code": stage_code},
    )
    child = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not parent or not child:
        check("HV-DUR-05", "setParent 子任务", False, "setup")
        return
    res, _ = client.post(
        "project/task/setParent",
        {"taskCode": child, "parentCode": parent},
    )
    if not client.ok(res):
        check("HV-DUR-05", "setParent 子任务", False, "setParent")
        return
    res, _ = client.post("project/task/read", {"taskCode": child})
    pcode = (res.get("data") or {}).get("pcode", "") if client.ok(res) else ""
    check("HV-DUR-05", "setParent 子任务", pcode == parent)


def test_task_done_and_redo() -> None:
    res, _ = client.post(
        "project/task/save",
        {"name": "done-redo", "project_code": project_code, "stage_code": stage_code},
    )
    task_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not task_code:
        check("HV-DUR-06", "taskDone/redo 状态", False, "setup")
        return
    res, _ = client.post("project/task/taskDone", {"taskCode": task_code, "done": 1})
    if not client.ok(res):
        check("HV-DUR-06", "taskDone/redo 状态", False, "done")
        return
    res, _ = client.post("project/task/read", {"taskCode": task_code})
    done = (res.get("data") or {}).get("done") if client.ok(res) else None
    res, _ = client.post("project/task/taskDone", {"taskCode": task_code, "done": 0})
    res2, _ = client.post("project/task/read", {"taskCode": task_code})
    undone = (res2.get("data") or {}).get("done") if client.ok(res2) else None
    check("HV-DUR-06", "taskDone/redo 状态", done == 1 and undone == 0)


def legacy_rejected(res: dict | str) -> bool:
    """Legacy API 业务拒绝：非 200/201 成功码。"""
    return isinstance(res, dict) and res.get("code") not in (200, 201)


def list_payload(res: dict) -> list:
    data = res.get("data") if isinstance(res, dict) else None
    if isinstance(data, list):
        return data
    if isinstance(data, dict):
        return data.get("list") or []
    return []


def test_invalid_refs_strict() -> None:
    res, _ = client.post("project/project/read", {"projectCode": "invalid-project-code"})
    check(
        "HV-DUR-07",
        "无效项目码拒绝",
        legacy_rejected(res),
        str(res.get("code")) if isinstance(res, dict) else "",
    )
    res, _ = client.post("project/task/read", {"taskCode": "invalid-task-code"})
    check(
        "HV-DUR-08",
        "无效任务码拒绝",
        legacy_rejected(res),
        str(res.get("code")) if isinstance(res, dict) else "",
    )


def test_project_info_roundtrip() -> None:
    name = f"info-{uuid.uuid4().hex[:6]}"
    res, _ = client.post(
        "project/projectInfo/save",
        {"projectCode": project_code, "name": name, "value": "v1", "description": "durable"},
    )
    info_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not info_code:
        check("HV-DUR-09", "projectInfo save/edit/delete", False, "save")
        return
    res, _ = client.post(
        "project/projectInfo/edit",
        {"projectCode": project_code, "infoCode": info_code, "name": name, "value": "v2"},
    )
    if not client.ok(res):
        check("HV-DUR-09", "projectInfo save/edit/delete", False, "edit")
        return
    res, _ = client.post("project/projectInfo/index", {"projectCode": project_code})
    values = {
        (item.get("code"), item.get("value"))
        for item in list_payload(res)
        if isinstance(item, dict)
    }
    if (info_code, "v2") not in values:
        check("HV-DUR-09", "projectInfo save/edit/delete", False, "value not updated")
        return
    res, _ = client.post("project/projectInfo/delete", {"infoCode": info_code})
    check("HV-DUR-09", "projectInfo save/edit/delete", client.ok(res))


def test_events_edit_roundtrip() -> None:
    title = f"dur-evt-{uuid.uuid4().hex[:6]}"
    res, _ = client.post(
        "project/events/save",
        {
            "project_code": project_code,
            "title": title,
            "begin_time": "2032-01-01 10:00:00",
            "end_time": "2032-01-01 11:00:00",
        },
    )
    evt_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not evt_code:
        check("HV-DUR-10", "events save/edit/read", False, "save")
        return
    new_title = title + "-edited"
    res, _ = client.post(
        "project/events/edit",
        {"code": evt_code, "title": new_title},
    )
    if not client.ok(res):
        check("HV-DUR-10", "events save/edit/read", False, "edit")
        return
    res, _ = client.post("project/events/read", {"eventsCode": evt_code})
    got = (res.get("data") or {}).get("title", "") if client.ok(res) else ""
    check("HV-DUR-10", "events save/edit/read", got == new_title)


def test_create_comment_returns_code() -> None:
    res, _ = client.post(
        "project/task/save",
        {"name": "cmt-shape", "project_code": project_code, "stage_code": stage_code},
    )
    task_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not task_code:
        check("HV-DUR-11", "createComment 返回 code", False, "task setup")
        return
    res, _ = client.post(
        "project/task/createComment",
        {"taskCode": task_code, "comment": "contract shape"},
    )
    data = res.get("data") if client.ok(res) else None
    shape_ok = isinstance(data, dict) and bool(data.get("code")) and data.get("content") == "contract shape"
    check("HV-DUR-11", "createComment 返回 code", shape_ok, str(data)[:80])


def test_stage_create_delete() -> None:
    name = f"DurCol-{uuid.uuid4().hex[:4]}"
    res, _ = client.post(
        "project/taskStages/save",
        {"projectCode": project_code, "name": name},
    )
    stage = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    if not stage:
        check("HV-DUR-12", "taskStages save/delete", False, "save")
        return
    res, _ = client.post("project/taskStages/delete", {"code": stage})
    check("HV-DUR-12", "taskStages save/delete", client.ok(res))


def main() -> int:
    print("=== Gate A Durable Semantic Tests ===")
    print(f"BASE: {client.base}\n")
    if not setup():
        print("❌ Durable 前置失败")
        return 1
    test_task_delete_roundtrip()
    test_comment_delete_roundtrip()
    test_tag_edit_delete_roundtrip()
    test_logout_invalidates_token()
    test_set_parent_child()
    test_task_done_and_redo()
    test_invalid_refs_strict()
    test_project_info_roundtrip()
    test_events_edit_roundtrip()
    test_create_comment_returns_code()
    test_stage_create_delete()
    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate A Durable 全部通过")
        return 0
    print("🔴 Gate A Durable 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
