# Gate A — Legacy PearProject API 自动化

| 套件 | 脚本 | 用例数 |
|------|------|--------|
| Core | `test_gate_a.py` | HV-A01 ~ A17（17） |
| Extended | `test_gate_a_extended.py` | HV-A18 ~ A95（78） |

```bash
cd pearProjectApi
bash tests/gate-a/run.sh          # Core + Extended + Gate B
bash tests/ci/run-regression.sh   # Docker 启动 + 全量（CI 同款）
```

环境变量：`GATE_A_BASE_URL`（默认 8090）、`GATE_A_ACCOUNT`（123456）、`GATE_A_PASSWORD`（md5）。
