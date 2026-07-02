#!/usr/bin/env python3
"""Gate A extended — Legacy API module coverage (HV-A18+)."""
from __future__ import annotations

import json
import os
import sys
import uuid

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "lib"))
from legacy_client import LegacyClient  # noqa: E402

PASS = 0
FAIL = 0
SKIP = 0

client = LegacyClient()
project_code = ""
stage_code = ""
task_code = ""
task_code2 = ""
tag_code = ""
file_code = ""
event_code = ""
version_code = ""
feature_code = ""
dept_code = ""


def check(case_id: str, name: str, ok: bool, detail: str = "") -> None:
    global PASS, FAIL
    if ok:
        PASS += 1
        print(f"✅ {case_id} {name}")
    else:
        FAIL += 1
        suffix = f" — {detail}" if detail else ""
        print(f"❌ {case_id} {name}{suffix}")


def check_no500(case_id: str, name: str, res: dict | str, status: int = 200) -> None:
    """Endpoint reachable — must not 500."""
    if status == 500:
        check(case_id, name, False, "HTTP 500")
        return
    if isinstance(res, dict) and res.get("code") == 500:
        check(case_id, name, False, str(res.get("msg", ""))[:80])
        return
    check(case_id, name, True)


def skip(case_id: str, name: str, reason: str) -> None:
    global SKIP
    SKIP += 1
    print(f"⏭️  {case_id} {name} ({reason})")


def setup_session() -> bool:
    global project_code, stage_code, task_code, task_code2
    res = client.login()
    if not client.ok(res):
        return False
    suffix = uuid.uuid4().hex[:8]
    res, _ = client.post("project/project/save", {"name": f"Ext-{suffix}", "description": "extended gate a"})
    if not client.ok(res):
        return False
    project_code = (res.get("data") or {}).get("code", "")
    res, _ = client.post("project/taskStages/index", {"projectCode": project_code})
    lst = (res.get("data") or {}).get("list") or []
    if not lst:
        return False
    stage_code = lst[0].get("code", "")
    res, _ = client.post(
        "project/task/save",
        {"name": "Ext Task 1", "project_code": project_code, "stage_code": stage_code},
    )
    task_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    res, _ = client.post(
        "project/task/save",
        {"name": "Ext Task 2", "project_code": project_code, "stage_code": stage_code},
    )
    task_code2 = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    return bool(project_code and stage_code and task_code)


def test_login_and_index() -> None:
    res, _ = client.post("project/login/getCaptcha", {}, auth=False)
    check("HV-A18", "验证码接口", isinstance(res, dict) and res.get("code") in (200, 201))

    res, _ = client.post("project/login/_checkLogin", {"account": client.account})
    check("HV-A19", "检查登录状态", client.ok(res))

    res, _ = client.post("project/login/_currentMember", {})
    check("HV-A20", "当前成员接口 _currentMember", client.ok(res))

    req = client.base + "/index/index/checkInstall"
    import urllib.request

    with urllib.request.urlopen(req, timeout=15) as resp:
        body = json.loads(resp.read().decode())
    check("HV-A21", "安装锁检查", body.get("code") == 200)

    res, _ = client.post("project/index/_menus", {})
    check("HV-A22", "菜单树 _menus", client.ok(res))

    res, _ = client.post("project/index/info", {})
    check("HV-A23", "个人信息 info", client.ok(res))

    res, _ = client.post("project/index/systemConfig", {})
    check("HV-A24", "系统配置", client.ok(res))

    res, _ = client.get("index/index/index", auth=False)
    check("HV-A25", "入口 index/index", client.ok(res))


def test_project_module() -> None:
    res, _ = client.post("project/project/read", {"projectCode": project_code})
    check("HV-A26", "项目详情 read", client.ok(res) and bool((res.get("data") or {}).get("code")))

    res, _ = client.post("project/project/edit", {"projectCode": project_code, "name": "Ext-Edited"})
    check("HV-A27", "项目编辑 edit", client.ok(res))

    res, _ = client.post("project/project/index", {"page": 1, "pageSize": 5})
    check("HV-A28", "项目列表 index", client.ok(res))

    res, _ = client.post("project/project/selfList", {"page": 1, "pageSize": 5, "archive": 0})
    check("HV-A29", "我的项目分页", client.ok(res) and "list" in (res.get("data") or {}))

    res, _ = client.post("project/project/analysis", {"projectCode": project_code})
    check("HV-A30", "项目分析", client.ok(res))

    res, _ = client.post("project/project/getLogBySelfProject", {"projectCode": project_code, "page": 1, "pageSize": 5})
    check("HV-A31", "项目动态", client.ok(res))

    res, _ = client.post("project/project/_projectStats", {"projectCode": project_code})
    check("HV-A32", "项目统计", client.ok(res))

    res, _ = client.post("project/projectCollect/collect", {"projectCode": project_code, "type": "collect"})
    check("HV-A33", "收藏项目", client.ok(res))

    res, _ = client.post("project/projectCollect/collect", {"projectCode": project_code, "type": "cancel"})
    check("HV-A34", "取消收藏", client.ok(res))


def test_task_module() -> None:
    res, _ = client.post("project/task/edit", {"taskCode": task_code, "name": "Ext Task Edited"})
    check("HV-A35", "任务编辑", client.ok(res))

    res, _ = client.post("project/task/index", {"projectCode": project_code, "page": 1, "pageSize": 10})
    check("HV-A36", "任务列表 index", client.ok(res))

    res, _ = client.post("project/task/selfList", {"page": 1, "pageSize": 10})
    check("HV-A37", "我的任务 selfList", client.ok(res))

    res, _ = client.post("project/task/taskLog", {"taskCode": task_code, "page": 1, "pageSize": 10})
    check("HV-A38", "任务动态 taskLog", client.ok(res))

    res, _ = client.post("project/task/like", {"taskCode": task_code, "like": 1})
    check("HV-A39", "任务点赞", client.ok(res))

    res, _ = client.post("project/task/star", {"taskCode": task_code, "star": 1})
    check("HV-A40", "任务收藏 star", client.ok(res))

    if client.member_code:
        res, _ = client.post(
            "project/task/assignTask",
            {"taskCode": task_code2, "executorCode": client.member_code},
        )
        check("HV-A41", "指派任务", client.ok(res))

    res, _ = client.post("project/task/taskDone", {"taskCode": task_code2, "done": 0})
    check("HV-A42", "取消完成", client.ok(res))

    res, _ = client.post("project/task/dateTotalForProject", {"projectCode": project_code})
    check("HV-A43", "任务日期统计", client.ok(res))

    res, _ = client.post("project/task/taskSources", {"taskCode": task_code})
    check("HV-A44", "任务关联资源", client.ok(res))

    res, status = client.post("project/task/_downloadTemplate", {})
    check(
        "HV-A45",
        "任务导入模板",
        status == 200 or (isinstance(res, dict) and res.get("_binary")),
    )


def test_task_stages_and_members() -> None:
    res, _ = client.post("project/taskStages/_getAll", {"projectCode": project_code})
    check("HV-A46", "看板列 _getAll", client.ok(res))

    res, _ = client.post("project/taskStages/tasks", {"stageCode": stage_code, "page": 1, "pageSize": 10})
    check("HV-A47", "列内任务 tasks", client.ok(res))

    res, _ = client.post("project/taskStages/taskTree", {"projectCode": project_code})
    check("HV-A48", "任务树 taskTree", client.ok(res))

    new_stage = f"Col-{uuid.uuid4().hex[:4]}"
    res, _ = client.post("project/taskStages/save", {"projectCode": project_code, "name": new_stage})
    new_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    check("HV-A49", "新增看板列", client.ok(res) and bool(new_code))

    if new_code:
        res, status = client.post("project/taskStages/edit", {"stageCode": new_code, "name": new_stage + "-2"})
        check_no500("HV-A50", "编辑看板列", res, status)

    res, status = client.post("project/taskMember/index", {"taskCode": task_code})
    check("HV-A51", "任务成员列表", client.ok(res))

    res, status = client.post("project/taskMember/searchInviteMember", {"taskCode": task_code, "keyword": "123456"})
    check_no500("HV-A52", "搜索任务成员", res, status)


def test_task_tags() -> None:
    global tag_code
    res, _ = client.post("project/taskTag/index", {"projectCode": project_code})
    check("HV-A53", "标签列表", client.ok(res))

    res, status = client.post(
        "project/taskTag/save",
        {"projectCode": project_code, "name": f"tag-{uuid.uuid4().hex[:4]}", "color": "blue"},
    )
    tag_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    check("HV-A54", "创建标签", client.ok(res) and bool(tag_code))

    if tag_code:
        res, _ = client.post("project/task/setTag", {"taskCode": task_code, "tagCode": tag_code})
        check("HV-A55", "任务打标签", client.ok(res))
        res, _ = client.post("project/task/taskToTags", {"taskCode": task_code})
        check("HV-A56", "任务标签列表", client.ok(res))
        res, _ = client.post(
            "project/task/getListByTaskTag",
            {"projectCode": project_code, "taskTagCode": tag_code, "page": 1, "pageSize": 10},
        )
        check("HV-A57", "按标签查任务", client.ok(res))


def test_file_and_notify() -> None:
    global file_code
    res, _ = client.post("project/file/index", {"projectCode": project_code, "page": 1, "pageSize": 10})
    check("HV-A58", "文件列表", client.ok(res))
    lst = (res.get("data") or {}).get("list") or []
    if lst:
        file_code = lst[0].get("code", "")

    if file_code:
        res, _ = client.post("project/file/read", {"fileCode": file_code})
        check("HV-A59", "文件详情", client.ok(res))

    res, _ = client.post("project/notify/noReads", {})
    check("HV-A60", "未读通知数", client.ok(res))

    res, _ = client.post("project/notify/setReadied", {"type": "all"})
    check("HV-A61", "全部已读", client.ok(res) or (isinstance(res, dict) and res.get("code") in (200, 201)))


def test_org_account_department() -> None:
    global dept_code
    res, _ = client.post("project/organization/_getOrgList", {})
    check("HV-A62", "组织列表", client.ok(res))

    res, _ = client.post("project/organization/index", {"page": 1, "pageSize": 10})
    check("HV-A63", "组织管理 index", client.ok(res))

    res, _ = client.post("project/account/index", {"page": 1, "pageSize": 10})
    check("HV-A64", "成员账户 index", client.ok(res))

    res, _ = client.post("project/account/_allList", {})
    check("HV-A65", "成员全量列表", client.ok(res))

    res, _ = client.post("project/department/index", {"page": 1, "pageSize": 10})
    check("HV-A66", "部门列表", client.ok(res))
    lst = (res.get("data") or {}).get("list") or []
    if lst:
        dept_code = lst[0].get("code", "")

    if dept_code:
        res, _ = client.post("project/department/read", {"departmentCode": dept_code})
        check("HV-A67", "部门详情", client.ok(res))

    res, _ = client.post("project/departmentMember/index", {"page": 1, "pageSize": 10})
    check("HV-A68", "部门成员", client.ok(res))


def test_auth_menu_node() -> None:
    res, _ = client.post("project/auth/index", {"page": 1, "pageSize": 10})
    check("HV-A69", "角色列表", client.ok(res))

    res, _ = client.post("project/menu/menu", {})
    check("HV-A70", "菜单管理", client.ok(res))

    res, _ = client.post("project/node/index", {})
    check("HV-A71", "节点 index", client.ok(res))

    res, _ = client.post("project/node/allList", {})
    check("HV-A72", "节点 allList", client.ok(res))


def test_events_and_project_extras() -> None:
    global event_code, version_code, feature_code
    res, _ = client.post("project/events/index", {"page": 1, "pageSize": 10})
    check("HV-A73", "日程 index", client.ok(res))

    res, _ = client.post("project/events/myList", {"page": 1, "pageSize": 10})
    check("HV-A74", "我的日程", client.ok(res))

    res, _ = client.post("project/events/confirmList", {"page": 1, "pageSize": 10})
    check("HV-A75", "待确认日程", client.ok(res))

    res, status = client.post(
        "project/events/save",
        {
            "title": f"Evt-{uuid.uuid4().hex[:6]}",
            "start_time": "2030-01-01 10:00:00",
            "end_time": "2030-01-01 11:00:00",
        },
    )
    event_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    check_no500("HV-A76", "创建日程", res, status)

    if event_code:
        res, _ = client.post("project/events/read", {"eventsCode": event_code})
        check("HV-A77", "日程详情", client.ok(res))

    res, _ = client.post("project/projectFeatures/index", {"projectCode": project_code})
    check("HV-A78", "项目版本功能列表", client.ok(res))

    res, _ = client.post(
        "project/projectFeatures/save",
        {"projectCode": project_code, "name": f"Feat-{uuid.uuid4().hex[:4]}"},
    )
    feature_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    check("HV-A79", "创建项目功能", client.ok(res))

    res, status = client.post("project/projectVersion/index", {"projectCode": project_code, "page": 1, "pageSize": 10})
    check_no500("HV-A80", "版本库列表", res, status)

    res, status = client.post(
        "project/projectVersion/save",
        {"projectCode": project_code, "name": f"v-{uuid.uuid4().hex[:4]}", "description": "gate a"},
    )
    version_code = (res.get("data") or {}).get("code", "") if client.ok(res) else ""
    check_no500("HV-A81", "创建版本", res, status)

    if version_code:
        res, _ = client.post("project/projectVersion/read", {"versionCode": version_code})
        check("HV-A82", "版本详情", client.ok(res))

    res, _ = client.post("project/projectTemplate/index", {"page": 1, "pageSize": 10})
    check("HV-A83", "项目模板列表", client.ok(res))

    res, status = client.post("project/taskStagesTemplate/index", {"page": 1, "pageSize": 10})
    check_no500("HV-A84", "看板模板", res, status)

    res, _ = client.post("project/taskWorkflow/index", {"projectCode": project_code})
    check("HV-A85", "工作流列表", client.ok(res))

    res, _ = client.post("project/taskWorkflow/_getTaskWorkflowRules", {"projectCode": project_code})
    check("HV-A86", "工作流规则", client.ok(res))

    res, _ = client.post("project/projectInfo/index", {"projectCode": project_code})
    check("HV-A87", "项目信息块", client.ok(res))

    res, _ = client.post("project/projectMember/_listForInvite", {"projectCode": project_code})
    check("HV-A88", "可邀请成员列表", client.ok(res))


def test_invite_and_security() -> None:
    res, _ = client.post("project/inviteLink/save", {"projectCode": project_code})
    check("HV-A89", "生成邀请链接", client.ok(res) or (isinstance(res, dict) and res.get("code") in (200, 201)))

    res, _ = client.post("project/project/read", {"projectCode": "invalid-project-code"})
    check("HV-A90", "无效项目码", isinstance(res, dict) and res.get("code") != 500)

    res, _ = client.post("project/task/read", {"taskCode": "invalid-task-code"})
    check("HV-A91", "无效任务码", isinstance(res, dict) and res.get("code") in (404, 400, 201))

    bad = LegacyClient()
    bad.login()
    bad.token = "invalid-token-value"
    res, status = bad.post("project/project/selfList", {"page": 1, "pageSize": 1})
    rejected = isinstance(res, dict) and res.get("code") in (401, 403) or status in (401, 403)
    check("HV-A92", "无效 token 拒绝", rejected or (isinstance(res, dict) and res.get("code") != 200), str(res)[:80])


def test_workflow_and_cleanup() -> None:
    res, _ = client.post("project/task/_taskWorkTimeList", {"taskCode": task_code})
    check("HV-A93", "工时列表", client.ok(res))

    res, status = client.post(
        "project/task/saveTaskWorkTime",
        {"taskCode": task_code, "workTime": 60, "content": "gate-a work", "beginTime": "2030-01-01 09:00:00", "endTime": "2030-01-01 10:00:00"},
    )
    check_no500("HV-A94", "登记工时", res, status)

    res, _ = client.post("project/projectMember/index", {"projectCode": project_code, "page": 1, "pageSize": 20})
    check("HV-A95", "项目成员分页", client.ok(res))


def main() -> int:
    print("=== Gate A Extended Legacy API ===")
    print(f"BASE: {client.base}\n")

    if not setup_session():
        print("❌ 扩展用例前置登录/建项目失败")
        return 1

    test_login_and_index()
    test_project_module()
    test_task_module()
    test_task_stages_and_members()
    test_task_tags()
    test_file_and_notify()
    test_org_account_department()
    test_auth_menu_node()
    test_events_and_project_extras()
    test_invite_and_security()
    test_workflow_and_cleanup()

    print(f"\n通过: {PASS}  失败: {FAIL}  跳过: {SKIP}")
    if FAIL == 0:
        print("🟢 Gate A Extended 全部通过")
        return 0
    print("🔴 Gate A Extended 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
