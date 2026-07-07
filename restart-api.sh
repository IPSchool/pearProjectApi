#!/usr/bin/env bash
# 从 pearProjectApi 根目录重启本地开发 API（8090）
exec "$(cd "$(dirname "$0")" && pwd)/docker/jira/restart-jira.sh" "$@"
