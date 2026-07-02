# How to use ?

## Gate A + Gate B — master TP6（推荐）

Legacy `project/*` 与 Jira `/rest/api/3/*` 共用 **`docker/jira`**（8090）：

```bash
cd docker/jira
./start-jira.sh
# 或: docker compose up -d && docker exec jira-app-1 php /app/docker/jira/fixture-init.php

bash tests/gate-a/run.sh          # Gate A 321 + Gate B 79
bash tests/ci/run-regression.sh   # CI 同款
```

- API（Jira）：http://127.0.0.1:8090/rest/api/3/
- Swagger UI：http://127.0.0.1:8090/swagger-ui

详见 [docker/jira/README.md](jira/README.md)、[tests/jira/README.md](../tests/jira/README.md)、[UPGRADE-TP6.md](../UPGRADE-TP6.md)。

## Gate A — HistoryV 基线（HistoryV 分支）

见 `HistoryV` 分支下 `docker/historyv/`（8080，TP5 对照）。

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
