# upgrade/tp6 分支说明

> **ThinkPHP 6 升级试验轨道** — Gate B Jira API 已在 TP6 下验收通过；Legacy `project` 模块尚未迁移。

## 与 master 差异

| 项 | master (TP5.1) | upgrade/tp6 |
|----|----------------|-------------|
| 框架 | 内嵌 `thinkphp/` 5.1.37 | Composer `topthink/framework` ^6.1 |
| PHP | 8.2（Docker Gate B） | 8.2 |
| 入口 | `index.php` → TP5 Container | `index.php` → TP6 Http |
| 公共函数 | `application/common.php` | `application/common-gateb.php`（无 PhpSpreadsheet 等 legacy 依赖） |
| Jira 模块 | `application/jira/` | `app/jira/` |
| 路由 | `application/jira/init.php` | `route/jira.php`（`Class@method` 字符串，勿用 `[Class, 'method']` 数组） |
| Legacy 模型 | `application/common/Model/` | 暂保留，Composer PSR-4 映射 |
| Composer 依赖 | 全量 legacy 包 | 仅 `topthink/framework` + `think-orm` |

## TP6 迁移要点（Gate B 已验证）

1. **路由**：`Route::get('x', [Foo::class, 'bar'])` 在 TP6.1 会触发 `strpos(array)` 500；改用 `'app\jira\controller\v3\Foo@bar'`。
2. **Db**：模型与脚本统一 `think\facade\Db`。
3. **表前缀**：`config/database.php` 增加顶层 `prefix`，兼容 `config('database.prefix')`。
4. **Hook**：TP6 无 `think\facade\Hook`；Gate B 路径下 `Task::taskHook` 为空操作。
5. **异常**：`app/ExceptionHandle.php` 对 `/rest/api/3/*` 返回 Jira 风格 JSON。

## Gate B 验收（2026-07-01）

```bash
cd docker/jira && docker compose build app && docker compose up -d
docker exec jira-app-1 composer install --no-interaction
docker exec jira-app-1 php /app/docker/jira/fixture-init.php
cd ../.. && tests/jira/smoke/run.sh
```

**结果**：B-α 13/13、B-β 11/11 全绿（PHP 8.2 + ThinkPHP 6.1.5）。

## Phase 3 Batch 1（Legacy project — Login + Index）

| 项 | 说明 |
|----|------|
| 控制器 | `app/project/controller/Login.php`、`Index.php` |
| 中间件 | `app/project/middleware/Auth.php`、`ProjectAuth.php` |
| 路由 | `route/project.php`（`Class@method` 字符串） |
| Docker | `nginx.conf` + compose 挂载 `/project/` |
| 依赖 | `firebase/php-jwt` |
| 验收 | `POST /project/login/index` 返回 `tokenList`（Gate A HV-A02 路径） |

## Phase 3 Batch 2（Legacy project — Project / Task 核心）

| 项 | 说明 |
|----|------|
| 控制器 | `Project`、`ProjectMember`、`Task`、`TaskStages`、`TaskMember` |
| 路由 | `route/project.php` 扩展 ~70 端点 |
| TP6 修复 | `Db` facade；`Task::find` 替代 `::get`；`request_only()` 兼容 `Request::only` 逗号字符串 |
| 验收 | `POST /project/project/index`、`/project/task/selfList`、`/project/taskStages/index` 返回 200 |

**下一批**：`File`、`Notify`、`Organization` 等。

## Phase 3 Batch 3（Legacy project — File / Notify / Organization）

| 项 | 说明 |
|----|------|
| 控制器 | `File`、`Notify`、`Organization` |
| 路由 | `route/project.php` 扩展 21 端点 |
| TP6 修复 | `request_only()`；`Model::find` 替代 `::get`；Organization `read` 按 `code` 查询 |
| 验收 | `POST /project/organization/index`、`/project/notify/index`、`/project/notify/noReads` 返回 200 |

**下一批**：`Account`、`Department`、`TaskTag` 等。

## Phase 3 Batch 4（Legacy project — Account / Department / TaskTag）

| 项 | 说明 |
|----|------|
| 控制器 | `Account`、`Department`、`DepartmentMember`、`TaskTag`、`ProjectCollect` |
| 路由 | `route/project.php` 扩展 33 端点 |
| TP6 修复 | 全量 `request_only()` 替换 `Request::only` / `$request::only` |
| 验收 | `POST /project/account/index`、`/project/department/index`、`/project/departmentMember/index` 返回 200 |

**下一批**：`Auth`、`Menu`、`Node`、`Events` 等。

## HistoryV / Gate A

仍在 **HistoryV 分支 + TP5 + PHP 7.4**，本分支不保证 Legacy Web API 可用。
