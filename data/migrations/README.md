# 数据库迁移

## 现行方案（Hero / Gate B）

| 场景 | 机制 |
|------|------|
| **全新 Docker 安装** | `data/pearproject.sql` 已全表 `utf8mb4_unicode_ci` |
| **已有数据库升级** | `docker/jira/run-migrations.php`（`restart-api.sh` 自动执行） |
| **列级修正** | `data/2.9.0/hero-utf8mb4-schema.sql` |

## 手动升级已有库

```bash
cd pearProjectApi/docker/jira
docker compose exec -T app php /app/docker/jira/run-migrations.php
```

## 历史文件

- `20260707_utf8mb4_*.sql` — 已并入 `hero-utf8mb4-schema.sql`
- `2.9.0/task-description-utf8mb4.sql` — 已并入，保留空壳兼容旧引用
