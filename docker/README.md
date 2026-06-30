# How to use ?

## Gate B — Jira API 改造环境（master，推荐）

```bash
cd docker/jira
./start-jira.sh
# API: http://127.0.0.1:8090/rest/api/3/
tests/jira/smoke/run.sh   # 红灯测试
```

详见 [docker/jira/README.md](jira/README.md)、[tests/jira/README.md](../tests/jira/README.md)。

## Gate A — HistoryV 基线（HistoryV 分支）

见 `HistoryV` 分支下 `docker/historyv/`。

---

## 单容器 PHP 开发（旧方式）

You need build PearProject docker image.

choise php version , and `cd` to dir.

```
# build
docker build -t pear-docker:1.0.0 .

# cd to root path
cd ../..

# attach container install composer vendor
docker run --rm -it --mount type=bind,source="$(pwd)",target=/app pear-docker:1.0.0 /bin/bash
composer install
exit

# run your docker
docker run --mount type=bind,source="$(pwd)",target=/app -p 1234:8081 pear-docker:1.0.0
```

Right , now visit http://127.0.0.1:1234 