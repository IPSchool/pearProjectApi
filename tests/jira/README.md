# Gate B — Jira API 兼容测试（红灯阶段）

> **当前状态**：测试已提交，**预期全部失败**，直到 `master` 实现 `/rest/api/3/*` 兼容层。  
> 权威定义：[pearProjectDocs/Manual/JiraAPI测试设计.md](../../../pearProjectDocs/Manual/JiraAPI测试设计.md)

## 目录

```text
tests/jira/
├── env.sh.example       # 环境变量模板
├── smoke/               # B-α 冒烟（Layer 1 + 4）
│   ├── run.sh           # 一键运行
│   ├── curl-myself.sh   # 官方 curl 回放
│   └── test_b_alpha.py  # B-α 自动化（Python 3 标准库）
├── contract/
│   └── allowlist.yaml   # B-α 端点清单
└── golden/              # Jira Cloud 录制响应（Golden File）
    └── README.md
```

## 快速开始

```bash
# 1. 启动改造环境（端口 8090）
cd docker/jira
./start-jira.sh

# 2. 配置环境变量
cp ../../tests/jira/env.sh.example ../../tests/jira/env.sh
# 编辑 env.sh 填入 JIRA_API_TOKEN 等

# 3. 运行 B-α 测试（当前应红灯）
cd ../../tests/jira/smoke
./run.sh
```

## 环境变量

| 变量 | 默认 | 说明 |
|------|------|------|
| `JIRA_BASE_URL` | `http://127.0.0.1:8090` | Pear Jira 兼容层 Base URL |
| `JIRA_EMAIL` | `jira-test@example.com` | Basic Auth 用户名 |
| `JIRA_API_TOKEN` | `gate-b-test-token` | Basic Auth 密码（API Token） |
| `JIRA_PROJECT_KEY` | `TST` | 测试项目 Key |

## B-α 范围

Auth + User + Project + Issue CRUD（见 `contract/allowlist.yaml`）。

## B-β 范围

Search (JQL) + User Search + Comment + Transition（`smoke/test_b_beta.py`）。

## 开始编码前 Checklist

见 JiraAPI测试设计.md §11。全部勾选后方可实现 `/rest/api/3`。
