# PearProject — ThinkPHP 5.1.37 PHP 8.2 兼容补丁

> Phase 1：在完整迁移至 ThinkPHP 6 之前，使内嵌 TP 5.1 可在 PHP 8.2 下运行。  
> 上游参考：top-think/framework 5.1.x 社区 PHP 8 适配。

## 补丁清单

| 文件 | 改动 |
|------|------|
| `library/think/Error.php` | E_DEPRECATED 不升级为异常 |
| `library/think/Container.php` | ReturnTypeWillChange + ReflectionParameter::getType() |
| `library/think/Config.php` | ArrayAccess ReturnTypeWillChange |
| `library/think/Model.php` | ArrayAccess ReturnTypeWillChange |
| `library/think/Collection.php` | ArrayAccess/Countable/Iterator/Json ReturnTypeWillChange |
| `library/think/Paginator.php` | 同上 |
| `library/think/db/Where.php` | ArrayAccess ReturnTypeWillChange |
| `library/think/model/concern/Conversion.php` | jsonSerialize ReturnTypeWillChange |
| `library/think/Request.php` | host() null-safe strpos |

## 验收

```bash
docker exec jira-app-1 php /app/docker/jira/fixture-init.php   # 无 Fatal
tests/jira/smoke/run.sh                                         # B-α + B-β 全绿
```

## 后续

Phase 2 迁移至 ThinkPHP 6 后，本目录补丁可整体移除。
