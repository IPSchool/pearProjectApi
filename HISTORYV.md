# HistoryV — 改造前基线分支

本分支永久保留 **PearProject 大刀阔斧改造之前** 的代码快照，供对照与回滚参考。

| 项 | 值 |
|----|-----|
| 分支名 | `HistoryV` |
| 创建日期 | 2026-06-30 |
| 后端版本 | 2.8.16（`.env.example` → `app_version`） |
| 用途 | 历史参考；**不在此分支继续开发** |
| 活跃开发 | `master`（Jira API 兼容改造） |

## 说明

- 原生 PearProject API（ThinkPHP 5.1，`/index.php/project/*`），**非** Jira REST API
- 数据库基线：`data/pearproject.sql`（v2.8.x）
- Jira 兼容改造将在 `master` 新增 `/rest/api/3/*` 接口层

## 仓库

- 前端：pearProject
- 后端：pearProjectApi（本仓库）
- 文档：pearProjectDocs
