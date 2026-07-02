#!/usr/bin/env python3
"""Gate B Layer 4 extended — jira-python create / search / delete (零修改)."""
from __future__ import annotations

import os
import sys
import uuid

BASE = os.environ.get("JIRA_BASE_URL", "http://127.0.0.1:8090").rstrip("/")
EMAIL = os.environ.get("JIRA_EMAIL", "jira-test@example.com")
TOKEN = os.environ.get("JIRA_API_TOKEN", "gate-b-test-token")
PROJECT_KEY = os.environ.get("JIRA_PROJECT_KEY", "TST")


def main() -> int:
    try:
        from jira import JIRA  # type: ignore
    except ImportError:
        print("⏭️  jira-python 未安装，跳过 Layer 4 extended")
        return 0

    print("=== Gate B Layer 4 Extended: jira-python ===")
    print(f"server: {BASE}")

    try:
        jira = JIRA(
            server=BASE,
            basic_auth=(EMAIL, TOKEN),
            options={"rest_api_version": "3"},
        )
        myself = jira.myself()
        assert myself.get("accountId"), "missing accountId"
        print(f"✅ JIRA-L4-01 myself: {myself.get('displayName')}")

        summary = f"smoke-ext-{uuid.uuid4().hex[:8]}"
        issue = jira.create_issue(
            fields={
                "project": {"key": PROJECT_KEY},
                "summary": summary,
                "issuetype": {"name": "Task"},
            }
        )
        assert issue.key.startswith(PROJECT_KEY + "-"), issue.key
        print(f"✅ JIRA-L4-02 create_issue: {issue.key}")

        found = jira.issue(issue.key)
        assert found.key == issue.key
        assert found.fields.summary == summary
        print("✅ JIRA-L4-03 issue() by key")

        jira.add_comment(issue.key, "gate-b layer4 comment")
        comments = jira.comments(issue.key)
        assert len(comments) >= 1
        print("✅ JIRA-L4-04 add_comment + comments")

        project = jira.project(PROJECT_KEY)
        assert project.key == PROJECT_KEY
        print(f"✅ JIRA-L4-05 project: {project.key}")

        transitions = jira.transitions(issue)
        assert transitions, "no transitions"
        jira.transition_issue(issue, transitions[0]["id"])
        print("✅ JIRA-L4-06 transition_issue")

        issue.delete()
        print("✅ JIRA-L4-07 delete_issue")
    except Exception as exc:  # noqa: BLE001
        print(f"❌ JIRA-L4 extended failed: {exc}")
        return 1

    print("🟢 jira-python extended 全部通过")
    return 0


if __name__ == "__main__":
    sys.exit(main())
