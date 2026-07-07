# Gate B — Jira API 兼容改造环境

用于 **Gate A + Gate B** 验收：`master` 分支 Legacy `project/*` 与 `/rest/api/3/*` 兼容层共用本 Compose。

> 文档：[tests/jira/README.md](../../tests/jira/README.md) · [验收测试](../../../pearProjectDocs/Manual/验收测试.md) · [UPGRADE-TP6.md](../../UPGRADE-TP6.md)

## 与 HistoryV 的关系

| 环境 | Compose | 分支 | 端口 | 用途 |
|------|---------|------|------|------|
| HistoryV | `docker/historyv` | HistoryV | 8080 | TP5 基线对照 |
| **master TP6** | `docker/jira` | master | **8090** | Gate A Legacy + Gate B Jira |

两者可同时运行（MySQL/Redis 端口不冲突：3307 vs 3308，6380 vs 6381）。

## 快速启动

```bash
cd pearProjectApi/docker/jira
chmod +x start-jira.sh restart-jira.sh init-jira-fixture.sh
./start-jira.sh

# 修改 route/*.php 或 app/ 代码后
./restart-jira.sh

# 或从 pearProjectApi 根目录: bash restart-api.sh

# 或手动
docker compose up -d
docker exec jira-app-1 composer install --no-interaction
docker exec jira-app-1 php /app/docker/jira/fixture-init.php
bash ../../tests/ci/fix-runtime-perms.sh
```

## 运行全量回归

```bash
cd pearProjectApi
bash tests/gate-a/run.sh        # Gate A（321）+ Gate B（79）
bash tests/ci/run-regression.sh # CI 同款（含 Docker build）
```

## 服务端口

| 服务 | 端口 |
|------|------|
| Nginx + PHP（Legacy + Jira + Swagger） | **8090** |
| MySQL 5.7 | 3308 |
| Redis | 6381 |
| **WebSocket（GatewayWorker）** | **2345** |

## WebSocket 实时推送

Compose 含 `gateway` 服务（Workerman）：

```bash
docker compose up -d gateway
docker compose logs -f gateway
```

Hero 前端 `.env`：`VITE_WS_URL=ws://127.0.0.1:2345`。Docker 环境 `.env.docker` 已设 `notice_push = true` 与 `gateway.register_host = gateway`。说明见 [HERO.md](../../pearProject/HERO.md#实时推送websocket)。

## API 入口

| 路径 | 说明 |
|------|------|
| `POST /project/login/index` | Legacy 登录（Gate A） |
| `GET /rest/api/3/myself` | Jira 当前用户（Gate B，Basic Auth） |
| `GET /swagger-spec` | OpenAPI 3.0 JSON |
| `GET /swagger-ui` | Swagger UI |

## 测试账号

| 用途 | 账号 | 凭据 |
|------|------|------|
| Gate A Legacy | `Lincoln` | 密码传 md5 `e10adc3949ba59abbe56e057f20f883e`（明文 `123456`） |
| Gate B Jira | `jira-test@example.com` | API Token `gate-b-test-token` |

Fixture 脚本：`docker/jira/fixture-init.php`（写入演示组织、TST 项目、Jira 测试用户）。

## 已实现

- [x] ThinkPHP 6 `app/jira/` + `/rest/api/3` 路由
- [x] Basic Auth + API Token
- [x] `fixture-init.php` 测试用户与 TST 项目
- [x] B-α ~ B-δ smoke 全绿
- [x] Gate A Core + Extended + Phase 2 全绿
- [x] OpenAPI 自动生成 + Swagger UI
- [x] GitHub Actions `gate-regression.yml`

## 已知限制

- Layer 2 OpenAPI Contract / Golden File 全量对比仍在扩展中
