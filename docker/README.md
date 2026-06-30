# How to use ?

## HistoryV 验收环境（推荐）

一键部署 HistoryV 基线前后端，用于改造前功能回归测试：

```bash
cd docker/historyv
# 详见 historyv/README.md
docker compose --profile build run --rm frontend-build
docker compose up -d --build
# 访问 http://127.0.0.1:8080
```

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