# ThinkPHP 6 升级说明（master）

> **2026-07-02**：`upgrade/tp6` 已合并入 `master`。Gate B Jira API 与 Legacy `project` 模块（27 控制器 / ~198 路由）均在 TP6 运行。

## 当前 master 栈

| 项 | 说明 |
|----|------|
| 框架 | Composer `topthink/framework` ^6.1 |
| PHP | 8.0+（Docker Gate B/A：8.2） |
| 入口 | `index.php` → TP6 Http |
| 公共函数 | `app/common/common-gateb.php` |
| Jira 模块 | `app/jira/` + `route/jira.php` |
| Legacy project | `app/project/controller/` + `route/project.php` |
| 模型 | `app/common/Model/` |
| 存储 | local / qiniu / oss（`gateb_storage_type()`） |

## 历史对照（合并前）

| 项 | 旧 master (TP5.1) | 现 master (TP6) |
|----|-------------------|-----------------|
| 框架 | 内嵌 `thinkphp/` 5.1.37 | Composer TP6 |
| Jira | `application/jira/init.php` | `route/jira.php` |
| Legacy | `application/project/controller/` | `app/project/controller/` |

## TP6 迁移要点

1. **路由**：`Route::get('x', [Foo::class, 'bar'])` 在 TP6.1 会触发 `strpos(array)` 500；改用 `'app\jira\controller\v3\Foo@bar'`。
2. **Db**：模型与脚本统一 `think\facade\Db`。
3. **表前缀**：`config/database.php` 增加顶层 `prefix`，兼容 `config('database.prefix')`。
4. **Hook**：TP6 无原生行为 Hook；Legacy `projectHook` 等已移除无效 `Hook::listen` 调用。
5. **异常**：`app/ExceptionHandle.php` 对 `/rest/api/3/*` 与 `/project/*` 返回 JSON。
6. **Request::only**：TP6 签名为 `only(array $name, $data, $filter)`；使用 `request_only()`（`common-gateb.php`）。
7. **Model::get**：TP6 已移除；改用 `find()` / `where()->find()`。
8. **field('id', true)**：TP6 改用 `withoutField('id')` 排除主键。

## Gate A + Gate B 一键验收

```bash
cd docker/jira && docker compose up -d
docker exec jira-app-1 composer install --no-interaction
docker exec jira-app-1 php /app/docker/jira/fixture-init.php
cd ../.. && tests/gate-a/run.sh   # Gate A 17/17 + Gate B smoke
```

**结果（2026-07-02）**：Gate A 17/17、B-α 13/13、B-β 11/11 全绿。

## Gate B 验收（单独）

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

## Phase 3 Batch 5（Legacy project — Auth / Menu / Node / Events）

| 项 | 说明 |
|----|------|
| 控制器 | `Auth`、`Menu`、`Node`、`Events`、`InviteLink` |
| 路由 | `route/project.php` 扩展 35 端点 |
| TP6 修复 | `request_only()`；`Db` facade；`EventsMember::select()` 替代 `::all()`；`Auth::_apply_filter` PHP 8 参数顺序；`NodeService` 扫描 `app/project/controller` |
| 验收 | `POST /project/auth/index`、`/project/menu/menu`、`/project/node/index`、`/project/events/index` 返回 200 |

**下一批**：`ProjectVersion`、`ProjectFeatures`、`TaskWorkflow`、`ProjectTemplate` 等。

## Phase 3 Batch 6（Legacy project — 收尾）

| 项 | 说明 |
|----|------|
| 控制器 | `ProjectVersion`、`ProjectFeatures`、`TaskWorkflow`、`ProjectTemplate`、`TaskStagesTemplate`、`ProjectInfo`、`SourceLink` |
| 路由 | `route/project.php` 扩展 33 端点，**累计 ~198 端点** |
| TP6 修复 | `request_only()`；`TaskWorkflow` Db facade + `find()`；`ProjectTemplate` 分页默认值 |
| 验收 | `POST /project/projectTemplate/index` 返回 200；27/27 控制器全部迁入 `app/project/` |

## Phase 3 完成（2026-07-01）

Legacy `project` 模块 **27 个控制器** 已全部迁至 TP6，`route/project.php` 显式注册全部端点，Gate B smoke 保持 B-α/B-β 全绿。

**已知限制**（非阻塞列表/读接口）：
- 文件上传（`file/uploadFiles`、`project/uploadCover` 等）依赖 `_uploadFile()` / OSS，尚未迁入 `common-gateb.php`
- 任务 Excel 导入依赖 PhpSpreadsheet（Gate B composer 未包含）

## Phase 4 — 上传与 Excel 导入（2026-07-01）

| 项 | 说明 |
|----|------|
| 新增 | `application/gateb-upload.php`（`_uploadFile`、TP6 `UploadedFile` 兼容） |
| 新增 | `application/gateb-import.php`（`importExcel`） |
| 依赖 | `phpoffice/phpspreadsheet` ^1.29 |
| 修复 | `CommonModel::_uploadImg` TP6 兼容；`FileService::local` 根路径与 URL；`gateb_root_path()` |
| 验收 | `_uploadImg` 返回 `/static/upload/...` URL；Gate B smoke 仍全绿 |

**Phase 5 — 云存储（2026-07-02）**

| 项 | 说明 |
|----|------|
| 依赖 | `qiniu/php-sdk` ^7.12、`aliyuncs/oss-sdk-php` ^2.7 |
| 路由 | `gateb_storage_type()` / `gateb_persist_uploaded_file()` — 按 `sysconf('storage_type')` 或 `config/storage.php` 选择 local/qiniu/oss |
| 上传 | `_uploadFile` / `_uploadImg` 先落本地临时文件，再按配置引擎持久化；非 local 时删除本地副本 |
| 分片 | `File::uploadFiles` 碎片仍走 local temp，合并后写入当前 `storage_type` |
| 验收 | 默认 `local`：Gate A HV-A11 + Gate B smoke 全绿；切换 qiniu/oss 需配置 `pear_system_config` |

## Phase 6 — TP5 遗留清理（2026-07-02）

| 项 | 说明 |
|----|------|
| 删除 | 内嵌 `thinkphp/`（217 文件）、`application/project/`、`application/jira/`、`application/index/` 等 TP5 重复目录（~260 文件） |
| 归位 | `application/common/Model/` → `app/common/Model/`；gateb 辅助文件 → `app/common/` |
| Autoload | `composer.json` 统一 `"app\\": "app/"`，移除分裂映射 |
| API 修复 | `think\Db` → facade；`Model::get()` → `find()`；移除无效 Hook；上传类型去掉 `think\File` |
| 保留 | `application/common/Plugins/GateWayWorker/`（WebSocket 脚本仍引用） |
| 验收 | Gate A + Gate B ~147 用例全绿 |

## HistoryV / Gate A

Gate A 脚本：`tests/gate-a/run.sh`（8090 Docker，账号 `123456` / md5 密码见 fixture）。HistoryV TP5 基线仍可在 `HistoryV` 分支 + `docker/historyv` 对照。
