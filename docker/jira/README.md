# Gate B — Jira API 兼容改造环境

用于 **Gate B** 验收：在 `master` 分支开发 `/rest/api/3/*` 兼容层。

> 文档：[tests/jira/README.md](../../tests/jira/README.md) · [JiraAPI测试设计.md](../../../pearProjectDocs/Manual/JiraAPI测试设计.md)

## 与 HistoryV 的关系

| 环境 | Compose | 分支 | 端口 | 用途 |
|------|---------|------|------|------|
| HistoryV | `docker/historyv` | HistoryV | 8080 | Gate A 功能回归 |
| **Jira 改造** | `docker/jira` | master | **8090** | Gate B API 兼容 |

两者可同时运行（MySQL/Redis 端口不冲突：3307 vs 3308，6380 vs 6381）。

## 快速启动

```bash
cd pearProjectApi/docker/jira
chmod +x start-jira.sh init-jira-fixture.sh
./start-jira.sh
```

## 运行 B-α 红灯测试

```bash
cp tests/jira/env.sh.example tests/jira/env.sh
tests/jira/smoke/run.sh   # 预期失败，直到实现兼容层
```

## 服务端口

| 服务 | 端口 |
|------|------|
| Jira API (Nginx+PHP) | 8090 |
| MySQL 5.7 | 3308 |
| Redis | 6381 |

## 待实现

- [ ] ThinkPHP `jira` 模块 + `/rest/api/3` 路由
- [ ] Basic Auth + API Token
- [ ] `init-jira-fixture.sh` 写入测试用户与 TST 项目
- [ ] B-α 测试转绿
