# Gate A — Legacy PearProject API 自动化

Gate A 验证 **master TP6** 上 Legacy `project/*` 接口不回退。与 Gate B 共用 `docker/jira`（8090）。

| 套件 | 脚本 | 用例 ID | 说明 |
|------|------|---------|------|
| Core | `test_gate_a.py` | HV-A01 ~ A17 | 核心 CRUD / 登录 |
| Extended | `test_gate_a_extended.py` | HV-A18 ~ A107 | 模块覆盖 + 语义断言 |
| Durable | `test_gate_a_durable.py` | HV-DUR-01 ~ 12 | 长期语义往返（非 check_no500） |
| Durable Gaps | `test_gate_a_durable_gaps.py` | HV-DUR-13 ~ 22 | Phase-2 弱路由升级为语义断言 |
| OpenAPI | `test_gate_a_openapi.py` | HV-OAPI-01 ~ 09 | swagger-spec 与 route/*.php 一致 |
| Phase 2 | `test_gate_a_phase2.py` | HV-A96+ | 路由差集 + 边界（逐步升级为语义） |

**Durable / L2 / L3** 为长期稳定性门禁：断言业务结果与响应结构，而非仅「路由可达 / 非 500」。

## 运行

```bash
cd pearProjectApi
bash tests/gate-a/run.sh              # Core + Extended + Phase 2 + Gate B
bash tests/ci/run-regression.sh       # Docker 启动 + 全量（CI 同款）
```

## 环境变量

| 变量 | 默认 | 说明 |
|------|------|------|
| `GATE_A_BASE_URL` | `http://127.0.0.1:8090` | API Base URL |
| `GATE_A_ACCOUNT` | `Lincoln` | 演示账号 |
| `GATE_A_PASSWORD` | `e10adc3949ba59abbe56e057f20f883e` | md5(123456) |

## 相关

- [验收测试总纲](../../../pearProjectDocs/Manual/验收测试.md)
- [UPGRADE-TP6.md](../../UPGRADE-TP6.md)
