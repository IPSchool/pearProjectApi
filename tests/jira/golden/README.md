# Golden File（Layer 3）

从 **Jira Cloud Developer 站点** 录制标准响应，供 Schema 对比。

## 录制步骤

```bash
source ../env.sh
# 指向 Jira Cloud（非 Pear）
curl -s -u "$JIRA_CLOUD_EMAIL:$JIRA_CLOUD_API_TOKEN" \
  "$JIRA_CLOUD_BASE_URL/rest/api/3/myself" \
  | jq . > L1-U01.myself.response.json
```

## B-α 必备文件（待录制）

| 文件 | 来源用例 |
|------|----------|
| `L1-U01.myself.response.json` | JIRA-L1-U01 |
| `L1-P02.project-TST.response.json` | JIRA-L1-P02 |
| `L1-I01.create-issue.response.json` | JIRA-L1-I01 |

实现阶段使用 `tests/jira/contract/` + diff 工具对比；允许差异写入 `golden/allow-diff.yaml`（待建）。
