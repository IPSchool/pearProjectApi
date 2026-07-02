"""Jira REST API v3 HTTP client for Gate B smoke tests."""
from __future__ import annotations

import base64
import json
import os
import urllib.error
import urllib.parse
import urllib.request


class JiraClient:
    def __init__(
        self,
        base: str | None = None,
        email: str | None = None,
        token: str | None = None,
        project_key: str | None = None,
    ):
        self.base = (base or os.environ.get("JIRA_BASE_URL", "http://127.0.0.1:8090")).rstrip("/")
        self.email = email or os.environ.get("JIRA_EMAIL", "jira-test@example.com")
        self.token = token or os.environ.get("JIRA_API_TOKEN", "gate-b-test-token")
        self.project_key = project_key or os.environ.get("JIRA_PROJECT_KEY", "TST")

    def basic_auth(self) -> str:
        raw = f"{self.email}:{self.token}".encode()
        return "Basic " + base64.b64encode(raw).decode()

    def request(
        self,
        method: str,
        path: str,
        *,
        auth: bool = True,
        body: dict | None = None,
        headers: dict | None = None,
    ) -> tuple[int, dict | str]:
        url = f"{self.base}{path}"
        hdrs = {"Accept": "application/json"}
        if auth:
            hdrs["Authorization"] = self.basic_auth()
        if headers:
            hdrs.update(headers)
        data = None
        if body is not None:
            data = json.dumps(body).encode()
            hdrs["Content-Type"] = "application/json"
        req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
        try:
            with urllib.request.urlopen(req, timeout=25) as resp:
                raw = resp.read().decode("utf-8", errors="replace")
                status = resp.status
        except urllib.error.HTTPError as exc:
            status = exc.code
            raw = exc.read().decode("utf-8", errors="replace")
        except urllib.error.URLError as exc:
            return 0, str(exc.reason)
        try:
            return status, json.loads(raw) if raw else {}
        except json.JSONDecodeError:
            return status, raw[:500]

    @staticmethod
    def jira_error(payload: dict | str) -> bool:
        if not isinstance(payload, dict):
            return False
        return bool(payload.get("errorMessages") or payload.get("errors"))

    def adf_comment(self, text: str) -> dict:
        return {
            "type": "doc",
            "version": 1,
            "content": [
                {
                    "type": "paragraph",
                    "content": [{"type": "text", "text": text}],
                }
            ],
        }

    def create_task(self, summary: str) -> tuple[int, dict | str]:
        return self.request(
            "POST",
            "/rest/api/3/issue",
            body={
                "fields": {
                    "project": {"key": self.project_key},
                    "summary": summary,
                    "issuetype": {"name": "Task"},
                }
            },
        )
