"""Shared Gate A assertion helpers."""
from __future__ import annotations


def legacy_ok(res: dict | str) -> bool:
    return isinstance(res, dict) and res.get("code") == 200


def legacy_rejected(res: dict | str) -> bool:
    return isinstance(res, dict) and res.get("code") not in (200, 201)


def list_payload(res: dict) -> list:
    data = res.get("data") if isinstance(res, dict) else None
    if isinstance(data, list):
        return data
    if isinstance(data, dict):
        return data.get("list") or []
    return []
