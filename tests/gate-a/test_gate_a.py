#!/usr/bin/env python3
"""Gate A — Legacy project API regression (HV-A01 ~ HV-A17)."""
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("GATE_A_BASE_URL", "http://127.0.0.1:8090").rstrip("/")
ACCOUNT = os.environ.get("GATE_A_ACCOUNT", "123456")
PASSWORD = os.environ.get("GATE_A_PASSWORD", "e10adc3949ba59abbe56e057f20f883e")

passed = 0
failed = 0
skipped = 0
token = ""
org_code = ""
project_code = ""
stage_code = ""
task_code = ""


def post(path, data=None, auth=True, multipart=False):
    url = f"{BASE}/{path.lstrip('/')}"
    headers = {}
    body = None
    if multipart:
        body = data
    else:
        body = urllib.parse.urlencode(data or {}).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    if auth and token:
        headers["Authorization"] = f"Bearer {token}"
    if org_code:
        headers["organizationCode"] = org_code
    req = urllib.request.Request(url, data=body, method="POST", headers=headers)
    with urllib.request.urlopen(req, timeout=30) as resp:
        raw = resp.read().decode()
        try:
            return json.loads(raw), resp.status
        except json.JSONDecodeError:
            return raw, resp.status


def check(case_id, name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"✅ {case_id} {name}")
    else:
        failed += 1
        print(f"❌ {case_id} {name} — {detail}")


def main():
    global token, org_code, project_code, stage_code, task_code

    print(f"=== Gate A Legacy API Regression ===")
    print(f"BASE: {BASE}\n")

    # HV-A01 — TP6 路由 /index/index/index（非 index.php 前缀）
    try:
        req = urllib.request.Request(f"{BASE}/index/index/index", method="GET")
        with urllib.request.urlopen(req, timeout=15) as resp:
            raw = resp.read().decode()
            res = json.loads(raw)
            ok = resp.status == 200 and res.get("code") == 200
        check("HV-A01", "后端可达", ok, raw[:120] if not ok else "")
    except Exception as e:
        check("HV-A01", "后端可达", False, str(e))

    # HV-A02
    try:
        res, _ = post("project/login/index", {"account": ACCOUNT, "password": PASSWORD}, auth=False)
        token = res.get("data", {}).get("tokenList", {}).get("accessToken", "")
        orgs = res.get("data", {}).get("organizationList") or []
        if orgs:
            org_code = orgs[0].get("code", "")
        menus = res.get("data", {}).get("menuList")
        check("HV-A02", "登录 tokenList", res.get("code") == 200 and bool(token), f"code={res.get('code')}")
    except Exception as e:
        check("HV-A02", "登录 tokenList", False, str(e))
        print("\n后续用例跳过（无 token）")
        summary()
        return 1

    # HV-A03
    try:
        res, _ = post("project/index/index", {"page": 1, "pageSize": 10})
        menus = res.get("data") or res.get("data", {}).get("list")
        check("HV-A03", "菜单", res.get("code") == 200, f"code={res.get('code')}")
    except Exception as e:
        check("HV-A03", "菜单", False, str(e))

    # HV-A04
    try:
        res, _ = post("project/project/selfList", {"page": 1, "pageSize": 10})
        lst = (res.get("data") or {}).get("list") or []
        if lst:
            project_code = lst[0].get("code", "")
        check("HV-A04", "我的项目列表", res.get("code") == 200, f"total={(res.get('data') or {}).get('total')}")
    except Exception as e:
        check("HV-A04", "我的项目列表", False, str(e))

    # HV-A05
    try:
        res, _ = post("project/project/save", {"name": "GateA-Auto-Project", "description": "gate a test"})
        if res.get("code") == 200:
            project_code = (res.get("data") or {}).get("code") or project_code
        check("HV-A05", "创建项目", res.get("code") == 200 and bool(project_code), str(res.get("msg", "")))
    except Exception as e:
        check("HV-A05", "创建项目", False, str(e))

    # HV-A06
    try:
        res, _ = post("project/taskStages/index", {"projectCode": project_code})
        lst = (res.get("data") or {}).get("list") or []
        if isinstance(lst, list) and lst:
            stage_code = lst[0].get("code", "")
        elif isinstance(res.get("data"), list) and res.get("data"):
            stage_code = res["data"][0].get("code", "")
        check("HV-A06", "看板列", res.get("code") == 200 and bool(stage_code), f"stages={len(lst) if isinstance(lst, list) else 0}")
    except Exception as e:
        check("HV-A06", "看板列", False, str(e))

    # HV-A07
    try:
        res, _ = post("project/task/save", {
            "name": "GateA Task",
            "project_code": project_code,
            "stage_code": stage_code,
        })
        if res.get("code") == 200:
            task_code = (res.get("data") or {}).get("code") or task_code
        check("HV-A07", "创建任务", res.get("code") == 200 and bool(task_code), str(res.get("msg", "")))
    except Exception as e:
        check("HV-A07", "创建任务", False, str(e))

    # HV-A08
    try:
        res, _ = post("project/task/read", {"taskCode": task_code})
        data = res.get("data") or {}
        check("HV-A08", "任务详情", res.get("code") == 200 and bool(data.get("code") or data.get("name")), str(res.get("msg", "")))
    except Exception as e:
        check("HV-A08", "任务详情", False, str(e))

    # HV-A09
    try:
        res, _ = post("project/task/taskDone", {"taskCode": task_code, "done": 1})
        check("HV-A09", "完成任务", res.get("code") == 200, str(res.get("msg", "")))
    except Exception as e:
        check("HV-A09", "完成任务", False, str(e))

    # HV-A10
    try:
        res, _ = post("project/task/createComment", {"taskCode": task_code, "comment": "gate-a comment"})
        check("HV-A10", "任务评论", res.get("code") == 200, str(res.get("msg", "")))
    except Exception as e:
        check("HV-A10", "任务评论", False, str(e))

    # HV-A11 — 分片上传首块（验证 _uploadFile 路径）
    try:
        boundary = "----GateABoundary"
        body = (
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="identifier"\r\n\r\n'
            f"gatea-file-id\r\n"
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="filename"\r\n\r\n'
            f"gatea.txt\r\n"
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="chunkNumber"\r\n\r\n'
            f"1\r\n"
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="totalChunks"\r\n\r\n'
            f"1\r\n"
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="totalSize"\r\n\r\n'
            f"11\r\n"
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="projectCode"\r\n\r\n'
            f"{project_code}\r\n"
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="file"; filename="gatea.txt"\r\n'
            f"Content-Type: text/plain\r\n\r\n"
            f"hello gatea\r\n"
            f"--{boundary}--\r\n"
        ).encode()
        url = f"{BASE}/project/file/uploadFiles"
        headers = {
            "Content-Type": f"multipart/form-data; boundary={boundary}",
            "Authorization": f"Bearer {token}",
        }
        if org_code:
            headers["organizationCode"] = org_code
        req = urllib.request.Request(url, data=body, method="POST", headers=headers)
        with urllib.request.urlopen(req, timeout=30) as resp:
            res = json.loads(resp.read().decode())
        has_url = bool((res.get("data") or {}).get("url") or (res.get("data") or {}).get("key"))
        check("HV-A11", "文件上传", res.get("code") == 200 and has_url, str(res)[:200])
    except Exception as e:
        check("HV-A11", "文件上传", False, str(e))

    # HV-A12
    try:
        res, _ = post("project/projectMember/index", {"projectCode": project_code})
        check("HV-A12", "项目成员", res.get("code") == 200, "")
    except Exception as e:
        check("HV-A12", "项目成员", False, str(e))

    # HV-A13
    try:
        res, _ = post("project/projectMember/searchInviteMember", {"projectCode": project_code, "keyword": "Ali"})
        check("HV-A13", "搜索邀请成员", res.get("code") == 200, "")
    except Exception as e:
        check("HV-A13", "搜索邀请成员", False, str(e))

    # HV-A14
    try:
        res, _ = post("project/task/recycle", {"taskCode": task_code})
        ok_recycle = res.get("code") == 200
        res2, _ = post("project/task/recovery", {"taskCode": task_code})
        check("HV-A14", "回收站/recovery", ok_recycle and res2.get("code") == 200, f"recycle={res.get('code')} recovery={res2.get('code')}")
    except Exception as e:
        check("HV-A14", "回收站/recovery", False, str(e))

    # HV-A15
    try:
        res, _ = post("project/index/changeCurrentOrganization", {"organizationCode": org_code})
        check("HV-A15", "组织切换", res.get("code") == 200, str(res.get("msg", "")))
    except Exception as e:
        check("HV-A15", "组织切换", False, str(e))

    # HV-A16
    try:
        url = f"{BASE}/project/project/index"
        req = urllib.request.Request(url, data=urllib.parse.urlencode({"page": 1}).encode(), method="POST")
        with urllib.request.urlopen(req, timeout=15) as resp:
            res = json.loads(resp.read().decode())
        check("HV-A16", "无 token 401", res.get("code") == 401, f"code={res.get('code')}")
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        try:
            res = json.loads(body)
            check("HV-A16", "无 token 401", res.get("code") == 401, body[:120])
        except json.JSONDecodeError:
            check("HV-A16", "无 token 401", e.code == 401, body[:120])
    except Exception as e:
        check("HV-A16", "无 token 401", False, str(e))

    # HV-A17
    try:
        res, _ = post("project/notify/index", {"page": 1, "pageSize": 10})
        check("HV-A17", "通知列表", res.get("code") == 200, "")
    except Exception as e:
        check("HV-A17", "通知列表", False, str(e))

    return summary()


def summary():
    print(f"\n通过: {passed}  失败: {failed}  跳过: {skipped}")
    if failed == 0:
        print("🟢 Gate A 全部通过")
        return 0
    print("🔴 Gate A 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
