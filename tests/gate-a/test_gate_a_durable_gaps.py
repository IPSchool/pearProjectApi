#!/usr/bin/env python3
"""Gate A Durable Gaps — semantic tests for Phase-2 weak routes (HV-DUR-13+)."""
from __future__ import annotations

import json
import os
import sys
import uuid

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "lib"))
from gate_a_assert import legacy_ok, list_payload  # noqa: E402
from legacy_client import LegacyClient  # noqa: E402

PASS = 0
FAIL = 0

client = LegacyClient()
project_code = ""
archive_project = ""
stage_code = ""
stage_code2 = ""
task_code = ""
tag_code = ""
file_code = ""


def check(case_id: str, name: str, ok: bool, detail: str = "") -> None:
    global PASS, FAIL
    if ok:
        PASS += 1
        print(f"✅ {case_id} {name}")
    else:
        FAIL += 1
        suffix = f" — {detail}" if detail else ""
        print(f"❌ {case_id} {name}{suffix}")


def upload_test_file(project: str) -> str:
    global file_code
    ident = f"gap-{uuid.uuid4().hex[:8]}"
    res, _ = client.upload_multipart(
        "project/file/uploadFiles",
        {
            "identifier": ident,
            "filename": "gap.txt",
            "chunkNumber": "1",
            "totalChunks": "1",
            "totalSize": "11",
            "projectCode": project,
        },
        filename="gap.txt",
        content=b"hello gap-a",
    )
    if legacy_ok(res):
        res2, _ = client.post("project/file/index", {"projectCode": project, "page": 1, "pageSize": 5})
        files = list_payload(res2)
        if files:
            file_code = files[0].get("code", "")
    return file_code


def setup() -> bool:
    global project_code, archive_project, stage_code, stage_code2, task_code, tag_code, file_code
    if not legacy_ok(client.login()):
        return False

    suffix = uuid.uuid4().hex[:8]
    res, _ = client.post(
        "project/project/save",
        {"name": f"Gap-{suffix}", "description": "durable gaps"},
    )
    if not legacy_ok(res):
        return False
    project_code = (res.get("data") or {}).get("code", "")

    res, _ = client.post(
        "project/project/save",
        {"name": f"Arc-{suffix}", "description": "archive gaps"},
    )
    archive_project = (res.get("data") or {}).get("code", "") if legacy_ok(res) else ""

    res, _ = client.post("project/taskStages/index", {"projectCode": project_code})
    stages = (res.get("data") or {}).get("list") or []
    stage_code = stages[0].get("code", "") if stages else ""
    if len(stages) > 1:
        stage_code2 = stages[1].get("code", "")

    res, _ = client.post(
        "project/task/save",
        {"name": "gap-task", "project_code": project_code, "stage_code": stage_code},
    )
    task_code = (res.get("data") or {}).get("code", "") if legacy_ok(res) else ""

    tag_name = f"gap-{uuid.uuid4().hex[:4]}"
    res, _ = client.post(
        "project/taskTag/save",
        {"projectCode": project_code, "name": tag_name, "color": "blue"},
    )
    tag_code = (res.get("data") or {}).get("code", "") if legacy_ok(res) else ""

    res, _ = client.post("project/file/index", {"projectCode": project_code, "page": 1, "pageSize": 5})
    files = list_payload(res)
    file_code = files[0].get("code", "") if files else ""
    if not file_code:
        upload_test_file(project_code)

    return bool(project_code and stage_code and task_code)


def test_work_time_roundtrip() -> None:
    res, _ = client.post(
        "project/task/saveTaskWorkTime",
        {
            "taskCode": task_code,
            "num": 45,
            "content": "gap work",
            "beginTime": "2030-06-01 09:00:00",
        },
    )
    if not legacy_ok(res):
        check("HV-DUR-13", "工时 save/list/del", False, "save")
        return
    res, _ = client.post("project/task/_taskWorkTimeList", {"taskCode": task_code})
    wt_list = res.get("data") if legacy_ok(res) else []
    wt_code = ""
    if isinstance(wt_list, list) and wt_list:
        wt_code = wt_list[0].get("code", "")
    if not wt_code:
        check("HV-DUR-13", "工时 save/list/del", False, "list empty")
        return
    res, _ = client.post("project/task/delTaskWorkTime", {"code": wt_code})
    if not legacy_ok(res):
        check("HV-DUR-13", "工时 save/list/del", False, "del")
        return
    res, _ = client.post("project/task/_taskWorkTimeList", {"taskCode": task_code})
    remaining = res.get("data") if legacy_ok(res) else []
    codes = [w.get("code") for w in remaining if isinstance(w, dict)] if isinstance(remaining, list) else []
    check("HV-DUR-13", "工时 save/list/del", wt_code not in codes)


def test_batch_assign_and_private() -> None:
    res, _ = client.post(
        "project/task/batchAssignTask",
        {"taskCodes": json.dumps([task_code]), "executorCode": client.member_code},
    )
    if not legacy_ok(res):
        check("HV-DUR-14", "batchAssign + setPrivate", False, "assign")
        return
    res, _ = client.post("project/task/read", {"taskCode": task_code})
    assign_to = (res.get("data") or {}).get("assign_to", "") if legacy_ok(res) else ""
    if assign_to != client.member_code:
        check("HV-DUR-14", "batchAssign + setPrivate", False, f"assign_to={assign_to}")
        return
    res, _ = client.post("project/task/setPrivate", {"taskCode": task_code, "private": 1})
    res2, _ = client.post("project/task/read", {"taskCode": task_code})
    private = (res2.get("data") or {}).get("private") if legacy_ok(res2) else None
    res, _ = client.post("project/task/setPrivate", {"taskCode": task_code, "private": 0})
    res3, _ = client.post("project/task/read", {"taskCode": task_code})
    undone = (res3.get("data") or {}).get("private") if legacy_ok(res3) else None
    check("HV-DUR-14", "batchAssign + setPrivate", legacy_ok(res) and private == 1 and undone == 0)


def test_task_member_invite() -> None:
    res, _ = client.post(
        "project/taskMember/inviteMember",
        {"taskCode": task_code, "memberCode": client.member_code},
    )
    check("HV-DUR-15", "taskMember inviteMember", legacy_ok(res))


def test_set_tag_roundtrip() -> None:
    if not tag_code:
        check("HV-DUR-16", "setTag + taskToTags", False, "no tag")
        return
    res, _ = client.post(
        "project/task/setTag",
        {"taskCode": task_code, "tagCode": tag_code},
    )
    if not legacy_ok(res):
        check("HV-DUR-16", "setTag + taskToTags", False, "setTag")
        return
    res, _ = client.post("project/task/taskToTags", {"taskCode": task_code})
    tags = res.get("data") if legacy_ok(res) else []
    tag_codes = [t.get("tag_code") for t in tags if isinstance(t, dict)] if isinstance(tags, list) else []
    check("HV-DUR-16", "setTag + taskToTags", tag_code in tag_codes)


def test_project_archive_roundtrip() -> None:
    if not archive_project:
        check("HV-DUR-17", "project archive/recoveryArchive", False, "no project")
        return
    res, _ = client.post("project/project/archive", {"projectCode": archive_project})
    if not legacy_ok(res):
        check("HV-DUR-17", "project archive/recoveryArchive", False, "archive")
        return
    res, _ = client.post("project/project/selfList", {"page": 1, "pageSize": 50, "archive": 1})
    codes = [p.get("code") for p in list_payload(res) if isinstance(p, dict)]
    if archive_project not in codes:
        check("HV-DUR-17", "project archive/recoveryArchive", False, "not in archive list")
        return
    res, _ = client.post("project/project/recoveryArchive", {"projectCode": archive_project})
    check("HV-DUR-17", "project archive/recoveryArchive", legacy_ok(res))


def test_project_collect() -> None:
    res, _ = client.post(
        "project/projectCollect/collect",
        {"projectCode": project_code, "type": "collect"},
    )
    if not legacy_ok(res):
        check("HV-DUR-18", "projectCollect collect/uncollect", False, "collect")
        return
    res, _ = client.post(
        "project/projectCollect/collect",
        {"projectCode": project_code, "type": "uncollect"},
    )
    check("HV-DUR-18", "projectCollect collect/uncollect", legacy_ok(res))


def test_task_recycle_roundtrip() -> None:
    res, _ = client.post(
        "project/task/save",
        {"name": "recycle-me", "project_code": project_code, "stage_code": stage_code},
    )
    code = (res.get("data") or {}).get("code", "") if legacy_ok(res) else ""
    if not code:
        check("HV-DUR-19", "task recycle/recovery", False, "setup")
        return
    res, _ = client.post("project/task/recycle", {"taskCode": code})
    if not legacy_ok(res):
        check("HV-DUR-19", "task recycle/recovery", False, "recycle")
        return
    res, _ = client.post("project/task/recovery", {"taskCode": code})
    if not legacy_ok(res):
        check("HV-DUR-19", "task recycle/recovery", False, "recovery")
        return
    res, _ = client.post("project/task/read", {"taskCode": code})
    check("HV-DUR-19", "task recycle/recovery", legacy_ok(res))


def test_task_stages_sort() -> None:
    global stage_code2
    if not stage_code2:
        res, _ = client.post(
            "project/taskStages/save",
            {"projectCode": project_code, "name": f"Sort-{uuid.uuid4().hex[:4]}"},
        )
        stage_code2 = (res.get("data") or {}).get("code", "") if legacy_ok(res) else ""
    if not stage_code2:
        check("HV-DUR-20", "taskStages sort", False, "need 2 stages")
        return
    res, _ = client.post(
        "project/taskStages/sort",
        {"preCode": stage_code2, "nextCode": stage_code},
    )
    check("HV-DUR-20", "taskStages sort", legacy_ok(res))


def test_notify_index_and_clear() -> None:
    res, _ = client.post("project/notify/index", {"page": 1, "pageSize": 10})
    if not legacy_ok(res):
        check("HV-DUR-21", "notify index + _clearAll", False, "index")
        return
    res, _ = client.post("project/notify/_clearAll", {})
    check("HV-DUR-21", "notify index + _clearAll", legacy_ok(res))


def test_file_edit_recycle() -> None:
    if not file_code:
        check("HV-DUR-22", "file edit/recycle/recovery", False, "no file in project")
        return
    new_title = f"gap-file-{uuid.uuid4().hex[:4]}"
    res, _ = client.post(
        "project/file/edit",
        {"fileCode": file_code, "title": new_title},
    )
    if not legacy_ok(res):
        check("HV-DUR-22", "file edit/recycle/recovery", False, "edit")
        return
    res, _ = client.post("project/file/recycle", {"fileCode": file_code})
    if not legacy_ok(res):
        check("HV-DUR-22", "file edit/recycle/recovery", False, "recycle")
        return
    res, _ = client.post("project/file/recovery", {"fileCode": file_code})
    check("HV-DUR-22", "file edit/recycle/recovery", legacy_ok(res))


def main() -> int:
    print("=== Gate A Durable Gaps (Phase-2 upgrade) ===")
    print(f"BASE: {client.base}\n")
    if not setup():
        print("❌ Durable Gaps 前置失败")
        return 1

    test_work_time_roundtrip()
    test_batch_assign_and_private()
    test_task_member_invite()
    test_set_tag_roundtrip()
    test_project_archive_roundtrip()
    test_project_collect()
    test_task_recycle_roundtrip()
    test_task_stages_sort()
    test_notify_index_and_clear()
    test_file_edit_recycle()

    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate A Durable Gaps 全部通过")
        return 0
    print("🔴 Gate A Durable Gaps 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
