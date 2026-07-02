# Gate A — Legacy PearProject API 自动化

Gate A 验证 **master TP6** 上 Legacy `project/*` 接口不回退。与 Gate B 共用 `docker/jira`（8090）。

| 套件 | 脚本 | 用例 ID | 通过 |
|------|------|---------|------|
| Core | `test_gate_a.py` | HV-A01 ~ A17 | 17 |
| Extended | `test_gate_a_extended.py` | HV-A18 ~ A95 | 75 |
| Phase 2 | `test_gate_a_phase2.py` | HV-A96+ | 150（+6 skip） |

**Phase 2 策略**：扫描 `route/project.php`，与 Core + Extended 已覆盖路由做差集，每条未覆盖路由 1 用例（`check_routed`）；另含 OpenAPI、无 token 401、`_currentMember` 等边界用例。multipart 上传类端点跳过（由 HV-A11 覆盖）。

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
| `GATE_A_ACCOUNT` | `123456` | 演示账号 |
| `GATE_A_PASSWORD` | `e10adc3949ba59abbe56e057f20f883e` | md5(123456) |

## 相关

- [验收测试总纲](../../../pearProjectDocs/Manual/验收测试.md)
- [UPGRADE-TP6.md](../../UPGRADE-TP6.md)
