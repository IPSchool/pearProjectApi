"""Legacy PearProject API HTTP client for Gate A tests."""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.parse
import urllib.request


class LegacyClient:
    def __init__(
        self,
        base: str | None = None,
        account: str | None = None,
        password: str | None = None,
    ):
        self.base = (base or os.environ.get("GATE_A_BASE_URL", "http://127.0.0.1:8090")).rstrip("/")
        self.account = account or os.environ.get("GATE_A_ACCOUNT", "Lincoln")
        self.password = password or os.environ.get("GATE_A_PASSWORD", "e10adc3949ba59abbe56e057f20f883e")
        self.token = ""
        self.org_code = ""
        self.member_code = ""

    def login(self) -> dict:
        res, _ = self.post("project/login/index", {"account": self.account, "password": self.password}, auth=False)
        if res.get("code") == 200:
            data = res.get("data") or {}
            self.token = (data.get("tokenList") or {}).get("accessToken", "")
            orgs = data.get("organizationList") or []
            if orgs:
                self.org_code = orgs[0].get("code", "")
            member = data.get("member") or {}
            self.member_code = member.get("code", "")
        return res

    def headers(self, auth: bool = True) -> dict[str, str]:
        hdrs: dict[str, str] = {}
        if auth and self.token:
            hdrs["Authorization"] = f"Bearer {self.token}"
        if self.org_code:
            hdrs["organizationCode"] = self.org_code
        return hdrs

    def request(
        self,
        method: str,
        path: str,
        data: dict | None = None,
        *,
        auth: bool = True,
        raw_body: bytes | None = None,
        content_type: str | None = None,
    ) -> tuple[dict | str, int]:
        url = f"{self.base}/{path.lstrip('/')}"
        hdrs = self.headers(auth)
        body = raw_body
        if body is None and data is not None:
            body = urllib.parse.urlencode(data).encode()
            hdrs["Content-Type"] = "application/x-www-form-urlencoded"
        if content_type:
            hdrs["Content-Type"] = content_type
        req = urllib.request.Request(url, data=body, method=method, headers=hdrs)
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                raw_bytes = resp.read()
                status = resp.status
                try:
                    return json.loads(raw_bytes.decode()), status
                except (UnicodeDecodeError, json.JSONDecodeError):
                    if not raw_bytes and 200 <= status < 300:
                        return {"code": 200, "msg": "", "data": None}, status
                    return {"_binary": True, "size": len(raw_bytes)}, status
        except urllib.error.HTTPError as exc:
            raw_bytes = exc.read()
            try:
                return json.loads(raw_bytes.decode()), exc.code
            except (UnicodeDecodeError, json.JSONDecodeError):
                if not raw_bytes and 200 <= exc.code < 300:
                    return {"code": 200, "msg": "", "data": None}, exc.code
                return {"_binary": True, "size": len(raw_bytes)}, exc.code

    def post(self, path: str, data: dict | None = None, auth: bool = True) -> tuple[dict | str, int]:
        return self.request("POST", path, data, auth=auth)

    def upload_multipart(
        self,
        path: str,
        fields: dict[str, str],
        *,
        file_field: str = "file",
        filename: str = "test.txt",
        content: bytes = b"gate-a upload",
        content_type: str = "text/plain",
    ) -> tuple[dict | str, int]:
        boundary = f"----GateA{os.getpid()}"
        parts: list[bytes] = []
        for key, value in fields.items():
            parts.append(
                f"--{boundary}\r\n"
                f'Content-Disposition: form-data; name="{key}"\r\n\r\n'
                f"{value}\r\n".encode()
            )
        parts.append(
            (
                f"--{boundary}\r\n"
                f'Content-Disposition: form-data; name="{file_field}"; filename="{filename}"\r\n'
                f"Content-Type: {content_type}\r\n\r\n"
            ).encode()
            + content
            + f"\r\n--{boundary}--\r\n".encode()
        )
        body = b"".join(parts)
        return self.request(
            "POST",
            path,
            auth=True,
            raw_body=body,
            content_type=f"multipart/form-data; boundary={boundary}",
        )

    def get(self, path: str, auth: bool = True) -> tuple[dict | str, int]:
        return self.request("GET", path, None, auth=auth)

    def ok(self, res: dict | str) -> bool:
        return isinstance(res, dict) and res.get("code") == 200
