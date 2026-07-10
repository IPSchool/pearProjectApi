#!/usr/bin/env python3
"""Gate A OpenAPI parity — swagger-spec must match route/*.php."""
from __future__ import annotations

import json
import os
import sys
import urllib.request

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "lib"))
from legacy_client import LegacyClient  # noqa: E402
from route_inventory import index_paths, jira_path_urls, legacy_project_paths  # noqa: E402

PASS = 0
FAIL = 0

BASE = os.environ.get("GATE_A_BASE_URL", "http://127.0.0.1:8090").rstrip("/")


def check(case_id: str, name: str, ok: bool, detail: str = "") -> None:
    global PASS, FAIL
    if ok:
        PASS += 1
        print(f"✅ {case_id} {name}")
    else:
        FAIL += 1
        suffix = f" — {detail}" if detail else ""
        print(f"❌ {case_id} {name}{suffix}")


def load_spec() -> dict:
    with urllib.request.urlopen(f"{BASE}/swagger-spec", timeout=20) as resp:
        return json.loads(resp.read().decode())


def main() -> int:
    print("=== Gate A OpenAPI Parity ===")
    print(f"BASE: {BASE}\n")

    try:
        spec = load_spec()
    except Exception as exc:  # noqa: BLE001
        check("HV-OAPI-01", "swagger-spec 可达", False, str(exc))
        print(f"\n通过: {PASS}  失败: {FAIL}")
        return 1

    paths = spec.get("paths", {})
    check("HV-OAPI-01", "OpenAPI 3.x", spec.get("openapi", "").startswith("3."))
    check("HV-OAPI-02", "paths 非空", isinstance(paths, dict) and len(paths) > 0)

    legacy_expected = legacy_project_paths()
    legacy_missing = [p for p in legacy_expected if p not in paths]
    check(
        "HV-OAPI-03",
        f"Legacy 路由全覆盖 ({len(legacy_expected)})",
        not legacy_missing,
        f"missing {len(legacy_missing)} e.g. {legacy_missing[:3]}",
    )

    jira_expected = jira_path_urls()
    jira_missing = [p for p in jira_expected if p not in paths]
    check(
        "HV-OAPI-04",
        f"Jira 路由全覆盖 ({len(jira_expected)})",
        not jira_missing,
        f"missing {len(jira_missing)} e.g. {jira_missing[:3]}",
    )

    for i, idx_path in enumerate(index_paths()):
        check(f"HV-OAPI-05-{i}", f"index 路径 {idx_path}", idx_path in paths)

    check(
        "HV-OAPI-06",
        "refreshAccessToken 在 spec 中",
        "/index/index/refreshAccessToken" in paths,
    )

    # Legacy path must expose post operation
    sample = "/project/login/index"
    post_op = paths.get(sample, {}).get("post")
    check(
        "HV-OAPI-07",
        "Legacy path 含 post operation",
        isinstance(post_op, dict) and post_op.get("operationId"),
    )

    # Jira webhook group
    check(
        "HV-OAPI-08",
        "Webhook 路径在 spec",
        "/rest/webhooks/1.0/webhook" in paths,
    )

    # refreshAccessToken returns 401 by design (expired token path)
    client = LegacyClient()
    client.login()
    res, status = client.post("index/index/refreshAccessToken", {})
    token_expired = (
        status == 401
        or (isinstance(res, dict) and res.get("code") == 401)
    )
    check(
        "HV-OAPI-09",
        "refreshAccessToken 语义 401",
        token_expired,
        f"status={status} body={str(res)[:80]}",
    )

    print(f"\n通过: {PASS}  失败: {FAIL}")
    if FAIL == 0 and PASS > 0:
        print("🟢 Gate A OpenAPI 全部通过")
        return 0
    print("🔴 Gate A OpenAPI 存在失败")
    return 1


if __name__ == "__main__":
    sys.exit(main())
