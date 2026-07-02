# PearProject API

**Pear，梨子项目管理系统 — 后端 API**

需要配合 [前端项目 pearProject](https://github.com/a54552239/pearProject) 使用。

## master（ThinkPHP 6）

| 项 | 说明 |
|----|------|
| 框架 | ThinkPHP **6.1** + PHP **8.2**（Docker） |
| Legacy API | `POST /project/*`（27 控制器 / ~198 路由） |
| Jira 兼容 | `GET\|POST /rest/api/3/*` |
| 文档 | [UPGRADE-TP6.md](UPGRADE-TP6.md) · [pearProjectDocs](https://github.com/a54552239/pearProjectDocs) |

### 本地 Docker 验收

```bash
cd docker/jira && docker compose up -d
docker exec jira-app-1 composer install --no-interaction
docker exec jira-app-1 php /app/docker/jira/fixture-init.php
bash tests/ci/fix-runtime-perms.sh
bash tests/gate-a/run.sh    # Gate A 321 + Gate B 79
```

| 入口 | URL |
|------|-----|
| Legacy 登录 | `POST http://127.0.0.1:8090/project/login/index` |
| Jira myself | `GET http://127.0.0.1:8090/rest/api/3/myself` |
| Swagger UI | http://127.0.0.1:8090/swagger-ui |
| OpenAPI JSON | http://127.0.0.1:8090/swagger-spec |

**Gate A 账号**：`123456` / 密码 md5 `e10adc3949ba59abbe56e057f20f883e`  
**Gate B 账号**：`jira-test@example.com` / Token `gate-b-test-token`

CI：`.github/workflows/gate-regression.yml`（push/PR → master）

### HistoryV 基线（TP5）

改造前 v2.8.x 见 **`HistoryV`** 分支 + `docker/historyv`（8080）。

---

## 相关资料

- 语雀：https://www.yuque.com/bzsxmz
- 安装指南：https://www.yuque.com/bzsxmz/siuq1w/kggzna
- 文档仓库：[pearProjectDocs](https://github.com/a54552239/pearProjectDocs)

有不明白的地方可以加群：**275264059**，或 QQ：**545522390**

### 演示地址

> [https://home.vilson.xyz](https://home.vilson.xyz)

### 登录

账号：`123456` 密码：`123456`

### 友情链接

**JAVA 版本**：https://gitee.com/wulon/mk-teamwork-server

### 界面截图

![1](https://static.vilson.xyz/overview/1.png)

![1](https://static.vilson.xyz/overview/2.png)

![1](https://static.vilson.xyz/overview/3.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568905177-dfaae477-7edd-4862-8b73-04af5aa2c174.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568918658-c51079e5-5995-45ad-a073-b89f6919aee0.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568949579-f01eeaca-2052-44d6-be7d-eb58011732f3.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568992455-a8ccee61-3757-42b4-9ffb-0be73ce94d96.png)

![1](https://static.vilson.xyz/overview/8.png)

![1](https://static.vilson.xyz/overview/9.png)

![1](https://static.vilson.xyz/overview/10.png)

![1](https://static.vilson.xyz/overview/11.png)

![1](https://static.vilson.xyz/overview/12.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562569075060-d41ae959-fca4-460e-a123-2ccff6ac6208.png)

### 功能设计

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562467192538-6a4a949a-1dad-411e-af9f-ddec3f553276.png)

### 鼓励一下

<img src="https://static.vilson.xyz/pay/wechat.png" alt="Sample" width="150" height="150">

<img src="https://static.vilson.xyz/pay/alipay2.png" alt="Sample" width="150" height="150">
