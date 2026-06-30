#!/usr/bin/env python3
"""Layer 4 — jira-python 零修改冒烟（可选，需 pip install jira）。"""
from __future__ import annotations

import os
import sys

BASE = os.environ.get("JIRA_BASE_URL", "http://127.0.0.1:8090").rstrip("/")
EMAIL = os.environ.get("JIRA_EMAIL", "jira-test@example.com")
TOKEN = os.environ.get("JIRA_API_TOKEN", "gate-b-test-token")
PROJECT_KEY = os.environ.get("JIRA_PROJECT_KEY", "TST")


def main() -> int:
    try:
        from jira import JIRA  # type: ignore
    except ImportError:
        print("⏭️  jira-python 未安装，跳过 Layer 4（pip install jira）")
        return 0

    print("=== Gate B Layer 4: jira-python smoke ===")
    print(f"server: {BASE}")
    try:
        client = JIRA(server=BASE, basic_auth=(EMAIL, TOKEN))
        user = client.myself()
        assert user.get("accountId") or getattr(user, "accountId", None), "missing accountId"
        print(f"✅ jira-python myself: {user.get('displayName', user)}")
    except Exception as exc:  # noqa: BLE001
        print(f"❌ jira-python smoke failed (expected RED): {exc}")
        return 1

    try:
        project = client.project(PROJECT_KEY)
        print(f"✅ jira-python project: {project.key}")
    except Exception as exc:  # noqa: BLE001
        print(f"❌ jira-python project({PROJECT_KEY}): {exc}")
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
