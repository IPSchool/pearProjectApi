# Golden File / Schema（Layer 3）

Pear 实现的 **结构基准**，用于下一阶段的 Jira 兼容开发防回归。不依赖 Jira Cloud 外网录制，CI 可直接跑。

## 运行

```bash
bash tests/jira/golden/run.sh
# 或合并门禁
bash tests/gate-a/run.sh
```

## 文件

| 文件 | 说明 |
|------|------|
| `schemas.yaml` | 10 个核心端点的 required 字段 + 类型契约 |
| `test_l3_schema.py` | JIRA-L3-* 结构校验（HV 同级长期门禁） |

## 扩展

新增 Jira 端点时：

1. 在 `schemas.yaml` 增加 `id` / `required` / `nested`
2. 在 `contract/allowlist.yaml` 增加 L2 白名单（状态码 + 关键字段）
3. 在 `smoke/` 增加行为用例（创建/更新/删除往返）

可选：从 Jira Cloud 录制 JSON 到 `pear/` 子目录做 diff（需 `JIRA_CLOUD_*` 环境变量）。
