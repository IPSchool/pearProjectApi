"""Parse Legacy/Jira route files for OpenAPI parity tests."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]


def legacy_project_paths() -> list[str]:
    text = (ROOT / "route" / "project.php").read_text(encoding="utf-8")
    routes = re.findall(r"\['([^']+)',\s*'\w+',\s*'\w+'\]", text)
    return sorted(f"/project/{p}" for p in routes)


def jira_routes() -> list[tuple[str, str]]:
    """Return (METHOD, /path) for every Route in route/jira.php."""
    text = (ROOT / "route" / "jira.php").read_text(encoding="utf-8")
    entries: list[tuple[str, str]] = []
    group_prefix = ""

    for line in text.splitlines():
        gm = re.search(r"Route::group\('([^']+)'", line)
        if gm:
            group_prefix = gm.group(1).rstrip("/")
            continue
        if "})->middleware" in line or re.search(r"^\}\)->middleware", line.strip()):
            group_prefix = ""
            continue

        rm = re.search(
            r"Route::(get|post|put|delete)\('([^']+)'",
            line,
            re.IGNORECASE,
        )
        if not rm:
            continue
        method = rm.group(1).upper()
        sub = rm.group(2).lstrip("/")
        if group_prefix:
            full = f"/{group_prefix}/{sub}"
        else:
            full = f"/{sub}"
        full = re.sub(r"/+", "/", full)
        entries.append((method, full))

    # de-dup while preserving order
    seen: set[tuple[str, str]] = set()
    unique: list[tuple[str, str]] = []
    for item in entries:
        if item not in seen:
            seen.add(item)
            unique.append(item)
    return unique


def jira_path_urls() -> list[str]:
    """OpenAPI-style path keys (method-agnostic, one entry per URL)."""
    urls = {path for _, path in jira_routes()}
    return sorted(urls)


def index_paths() -> list[str]:
    return [
        "/index/index/index",
        "/index/index/checkInstall",
        "/index/index/refreshAccessToken",
    ]
