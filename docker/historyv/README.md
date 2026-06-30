# HistoryV 验收环境（Docker）

用于 **Gate A — HistoryV 功能回归**：在本地一键部署 HistoryV 分支的前后端。

> 文档索引：[pearProjectDocs/README.md](../../../pearProjectDocs/README.md) · 验收用例：[验收测试.md](../../../pearProjectDocs/Manual/验收测试.md)

## 目录结构要求

```text
PearProject/
├── pearProject/          ← 前端（分支 HistoryV）
├── pearProjectApi/       ← 后端（分支 HistoryV）
└── pearProjectDocs/
```

## 快速启动

```bash
cd pearProjectApi/docker/historyv
chmod +x start-historyv.sh smoke-test.sh
./start-historyv.sh
```

脚本会：检查 Docker → 校验/构建前端（`VUE_APP_BUILD_PATH=/static/dist/`）→ 启动 MySQL + Redis + PHP → 等待 API 就绪。

### 访问

| 项 | 值 |
|----|-----|
| 地址 | http://127.0.0.1:8080 |
| 演示账号 | `123456` / `123456` |
| MySQL | `127.0.0.1:3307`（root/root） |
| Redis | `127.0.0.1:6380` |

### 冒烟测试

```bash
./smoke-test.sh
```

## 手动步骤（可选）

```bash
# 1. 基线分支
cd pearProject && git checkout HistoryV
cd ../pearProjectApi && git checkout HistoryV

# 2. 构建前端（必须在 pearProject 根目录）
cd pearProject
npm install
VUE_APP_CROSS_DOMAIN=false \
VUE_APP_API_URL=http://127.0.0.1:8080/index.php \
VUE_APP_WS_URI= \
VUE_APP_HOME_PAGE=/home \
VUE_APP_BUILD_PATH=/static/dist/ \
npm run build
cp -r dist/* ../pearProjectApi/static/dist/

# 3. 启动
cd ../pearProjectApi/docker/historyv
docker compose up -d --build
```

## 常见问题

| 问题 | 处理 |
|------|------|
| `Docker daemon not running` | 打开 Docker Desktop，等到 Running |
| 拉取镜像失败 | 确认网络可用；`docker compose pull` |
| `no matching manifest for linux/arm64` | MySQL 5.7 无 arm64；compose 已设 `platform: linux/amd64` |
| 前端空白 | 确认 `static/dist/index.html` 存在且资源路径为 `/static/dist/`；Cmd+Shift+R 硬刷新 |
| 登录失败 | `docker compose down -v` 重建数据库（重新导入 `pearproject.sql`） |
| 头像/封面不显示 | vilson CDN HTTPS 已过期；启动时 `fix-static-urls.php` 自动改 HTTP |
| API 404 | `docker compose logs -f app`，确认 composer install 完成 |

## 服务说明

| 服务 | 端口 | 说明 |
|------|------|------|
| Web（Nginx+PHP） | 8080 | 前端 SPA + ThinkPHP API |
| MySQL 5.7 | 3307 | 首次启动导入 `data/pearproject.sql` |
| Redis 7 | 6380 | 缓存 |

- `vendor/` 容器启动时 `composer install` 生成
- 启动时执行 `fix-static-urls.php` 修复数据库中 vilson HTTPS 链接
- **未包含** GateWayWorker（WebSocket）；在线人数等需另行 `start.sh`
- Jira API 兼容验收在改造后的 `master` + `docker/jira`（待建）

## 常用命令

```bash
docker compose logs -f app          # 应用日志
docker compose down -v              # 停止并清空 MySQL 数据卷
docker compose up -d --build app    # 仅重建 app 容器
docker compose --profile build run --rm frontend-build   # Docker 内构建前端
```
