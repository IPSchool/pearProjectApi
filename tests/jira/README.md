# Gate B — Jira API 兼容测试

> **当前状态（2026-06-30）**：B-α ~ B-δ smoke **全绿**；CI 与本地 `tests/gate-a/run.sh` 合并执行。  
> 权威定义：[pearProjectDocs/Manual/JiraAPI测试设计.md](../../../pearProjectDocs/Manual/JiraAPI测试设计.md)

## 目录

```text
tests/jira/
├── env.sh.example       # 环境变量模板
├── smoke/
│   ├── run.sh           # B-α ~ δ + jira-python
│   ├── curl-myself.sh   # 官方 curl 回放
│   ├── test_b_alpha.py  # B-α（13）
│   ├── test_b_beta.py   # B-β（11）
│   ├── test_b_gamma.py  # B-γ（22）
│   ├── test_b_delta.py  # B-δ（26）
│   ├── test_jira_python.py
│   └── test_jira_python_extended.py  # L4（7）
├── contract/
│   ├── allowlist.yaml   # L2 端点白名单
│   ├── test_l2_contract.py
│   └── run.sh
└── golden/              # L3 结构契约（schemas.yaml）
    ├── schemas.yaml
    ├── test_l3_schema.py
    └── run.sh
```

## 快速开始

```bash
# 1. 启动改造环境（端口 8090）
cd docker/jira
docker compose up -d
docker exec jira-app-1 php /app/docker/jira/fixture-init.php
bash tests/ci/fix-runtime-perms.sh

# 2. 配置环境变量（可选，有默认值）
cp tests/jira/env.sh.example tests/jira/env.sh

# 3. 运行 Gate B smoke
bash tests/jira/smoke/run.sh

# L2 + L3 契约（已并入 smoke/run.sh）
bash tests/jira/contract/run.sh
bash tests/jira/golden/run.sh

# 或合并 Gate A + B
bash tests/gate-a/run.sh
```

## 环境变量

| 变量 | 默认 | 说明 |
|------|------|------|
| `JIRA_BASE_URL` | `http://127.0.0.1:8090` | Pear Jira 兼容层 Base URL |
| `JIRA_EMAIL` | `jira-test@example.com` | Basic Auth 用户名 |
| `JIRA_API_TOKEN` | `gate-b-test-token` | Basic Auth 密码（API Token） |
| `JIRA_PROJECT_KEY` | `TST` | 测试项目 Key |

## 套件范围

| 阶段 | 脚本 | 通过 | 范围 |
|------|------|------|------|
| **B-α** | `test_b_alpha.py` | 13 | Auth + User + Project + Issue CRUD |
| **B-β** | `test_b_beta.py` | 11 | JQL Search + Comment + Transition |
| **B-γ** | `test_b_gamma.py` | 22 | Agile Board/Sprint + Worklog + Version |
| **B-δ** | `test_b_delta.py` | 26 | Webhook/Filter/Permission + Swagger 可达 |
| **L2 Contract** | `contract/test_l2_contract.py` | 23 | allowlist 白名单 + 响应结构 |
| **L3 Schema** | `golden/test_l3_schema.py` | 10 | schemas.yaml 字段/类型契约 |
| **L4** | `test_jira_python*.py` | 7 | jira-python 客户端零修改冒烟 |

## 相关

- [docker/jira/README.md](../../docker/jira/README.md)
- [JiraAPI兼容.md](../../../pearProjectDocs/Manual/JiraAPI兼容.md)
