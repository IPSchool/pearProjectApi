#!/usr/bin/env python3
"""重启 pearProjectApi docker/jira 栈并等待 API 就绪。

用法:
  .venv/bin/python scripts/restart_docker.py
  .venv/bin/python scripts/restart_docker.py --rebuild
  .venv/bin/python scripts/restart_docker.py --service app
"""
from __future__ import annotations

import argparse
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
COMPOSE_FILE = REPO_ROOT / "docker" / "jira" / "docker-compose.yml"
DEFAULT_BASE_URL = "http://127.0.0.1:8090"
HEALTH_PATHS = ("/index/index/checkInstall", "/index/index/index")
MAX_WAIT_SECONDS = 180
POLL_INTERVAL = 3


def log(msg: str) -> None:
    print(f"[docker-restart] {msg}", flush=True)


def run(cmd: list[str], *, check: bool = True) -> subprocess.CompletedProcess[str]:
    log(f"$ {' '.join(cmd)}")
    return subprocess.run(cmd, check=check, text=True, cwd=REPO_ROOT)


def ensure_docker() -> None:
    if not shutil.which("docker"):
        sys.exit("未找到 docker 命令，请先安装 Docker Desktop。")
    try:
        run(["docker", "info"], check=True)
    except subprocess.CalledProcessError as exc:
        sys.exit(f"Docker 未运行或不可用: {exc}")


def compose_base() -> list[str]:
    return ["docker", "compose", "-f", str(COMPOSE_FILE)]


def restart_stack(*, rebuild: bool, services: list[str]) -> None:
    if not COMPOSE_FILE.is_file():
        sys.exit(f"找不到 compose 文件: {COMPOSE_FILE}")

    base = compose_base()
    if rebuild:
        run(base + ["up", "-d", "--build"] + services)
        return

    if services:
        run(base + ["restart"] + services)
    else:
        run(base + ["restart"])


def fix_runtime_permissions() -> None:
    script = (
        "mkdir -p runtime/cache runtime/log runtime/temp static/upload data "
        "&& chmod -R 777 runtime static/upload data "
        "&& chown -R www-data:www-data runtime static/upload data 2>/dev/null || true"
    )
    try:
        run(compose_base() + ["exec", "-T", "app", "bash", "-lc", script])
    except subprocess.CalledProcessError:
        log("跳过 runtime 权限修复（app 容器可能尚未就绪）")


def wait_for_api(base_url: str) -> None:
    deadline = time.monotonic() + MAX_WAIT_SECONDS
    last_error = ""

    while time.monotonic() < deadline:
        for path in HEALTH_PATHS:
            url = f"{base_url.rstrip('/')}{path}"
            try:
                with urllib.request.urlopen(url, timeout=5) as resp:
                    if 200 <= resp.status < 500:
                        log(f"API 就绪: {url} (HTTP {resp.status})")
                        return
            except urllib.error.HTTPError as exc:
                if exc.code in (401, 404):
                    log(f"API 就绪: {url} (HTTP {exc.code})")
                    return
                last_error = f"{url} -> HTTP {exc.code}"
            except Exception as exc:  # noqa: BLE001 — 轮询需捕获网络异常
                last_error = f"{url} -> {exc}"

        time.sleep(POLL_INTERVAL)

    log("等待超时，最近 app 日志:")
    try:
        run(compose_base() + ["logs", "app", "--tail", "30"], check=False)
    except Exception:
        pass
    sys.exit(f"API 在 {MAX_WAIT_SECONDS}s 内未就绪。{last_error}")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="重启 pearProjectApi docker/jira 环境")
    parser.add_argument(
        "--rebuild",
        action="store_true",
        help="重新 build 并 up（等价于 docker compose up -d --build）",
    )
    parser.add_argument(
        "--service",
        action="append",
        default=[],
        metavar="NAME",
        help="仅重启指定服务（可多次指定，如 --service app）",
    )
    parser.add_argument(
        "--base-url",
        default=DEFAULT_BASE_URL,
        help=f"健康检查地址（默认 {DEFAULT_BASE_URL}）",
    )
    parser.add_argument(
        "--skip-perms",
        action="store_true",
        help="跳过 runtime 目录权限修复",
    )
    parser.add_argument(
        "--no-wait",
        action="store_true",
        help="重启后不等待 API 就绪",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    services = args.service

    ensure_docker()
    log(f"Compose: {COMPOSE_FILE}")

    if args.rebuild:
        log("重建并启动容器…")
    elif services:
        log(f"重启服务: {', '.join(services)}")
    else:
        log("重启全部服务…")

    restart_stack(rebuild=args.rebuild, services=services)

    if not args.skip_perms:
        fix_runtime_permissions()

    if args.no_wait:
        log("完成（未等待 API）")
        return

    log(f"等待 API ({args.base_url})…")
    wait_for_api(args.base_url)
    log("Docker 环境已重启并就绪。")


if __name__ == "__main__":
    main()
