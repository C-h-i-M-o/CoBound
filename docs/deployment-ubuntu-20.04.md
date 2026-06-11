# Ubuntu 20.04 x86_64 部署 CoBound

## 1. 在开发机构建 amd64 镜像

在仓库根目录执行：

```bash
docker buildx build --platform linux/amd64 -t cobound:3.8.0-custom --load .
docker image inspect cobound:3.8.0-custom --format '{{.Architecture}}'
docker save cobound:3.8.0-custom | gzip > cobound-3.8.0-custom-amd64.tar.gz
```

架构检查结果应为 `amd64`。

将以下文件传到服务器同一目录：

- `cobound-3.8.0-custom-amd64.tar.gz`
- `.docker/docker-compose.production.yml`
- `.docker/.env.production.example`

## 2. 服务器准备

服务器为 Ubuntu 20.04 x86_64，并已安装 Docker Engine 与 Docker Compose 插件。导入镜像：

```bash
gunzip -c cobound-3.8.0-custom-amd64.tar.gz | docker load
docker image inspect cobound:3.8.0-custom --format '{{.Architecture}}'
```

## 3. 配置环境变量

```bash
cp .env.production.example .env.production
```

编辑 `.env.production`，至少替换以下内容：

- `MYSQL_ROOT_PASSWORD`
- `MYSQL_PASSWORD`
- `LEAN_DB_PASSWORD`，必须与 `MYSQL_PASSWORD` 相同
- `LEAN_SESSION_PASSWORD`
- `LEAN_APP_URL=http://服务器IP:5180`

默认不配置 SMTP：

```text
LEAN_EMAIL_USE_SMTP=false
```

## 4. 启动

在 `docker-compose.production.yml` 所在目录执行：

```bash
docker compose -f docker-compose.production.yml up -d
docker compose -f docker-compose.production.yml ps
```

浏览器访问：

```text
http://服务器IP:5180
```

Compose 只公开 `5180:8080`，MySQL 仅在内部网络中可访问。

## 5. 持久化与重启

数据库、用户文件、插件和日志使用 Docker volume 保存。验证重启恢复：

```bash
docker compose -f docker-compose.production.yml restart
docker compose -f docker-compose.production.yml ps
```

停止服务但保留数据：

```bash
docker compose -f docker-compose.production.yml down
```

不要使用 `down -v`，该参数会删除持久化数据。

## 6. 更新镜像

在开发机重新构建并传输同名镜像，服务器导入后执行：

```bash
docker compose -f docker-compose.production.yml up -d --force-recreate
```
