# Likeshop Docker 部署指南

镜像由 GitHub Actions 自动构建,有两种打包方式:

| 镜像类型 | tag 模式 | 包含组件 | 适合场景 |
|---|---|---|---|
| **标准版**(推荐) | `:latest` / `:<sha>` | 仅 PHP-FPM + Nginx | 生产环境,数据库/Redis 单独跑(docker-compose) |
| **All-in-One**(单容器) | `:aio-latest` / `:aio-<sha>` | PHP + Nginx + MariaDB + Redis | 测试/试玩,一行 `docker run` 起来 |

两种镜像都同时推到 GHCR 和阿里云 ACR(后者需配置 secrets)。

---

## 一、All-in-One:一行 `docker run` 起来

最快上手方式,**单容器自带数据库**,跟旧版 `php-b2c` 一致的体验。

```bash
docker run -d --name likeshop \
  -p 20208:80 \
  -e MYSQL_ROOT_PASSWORD=root \
  -v likeshop-data:/var/lib/mysql \
  -v likeshop-uploads:/server/public/uploads \
  -v likeshop-config:/server/config \
  ghcr.io/op4219sr-bot/likeshop:aio-latest
```

> ⚠️ 不挂卷也能跑,但 `docker rm` 后数据会丢。生产环境**强烈建议**至少挂 `/var/lib/mysql` 和 `/server/public/uploads`。

启动 30-60 秒后访问:
- 后台:`http://你的IP:20208/admin/login` 账号 `admin` / `123456`
- H5:`http://你的IP:20208/mobile/`

可以传更多环境变量(都有默认值):

| 变量 | 默认 | 说明 |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | `root` | MySQL root 密码 |
| `DB_USER` | `root` | 应用连库用户(默认就用 root) |
| `DB_PASSWORD` | 同 `MYSQL_ROOT_PASSWORD` | 应用连库密码 |
| `DB_NAME` | `likeshop` | |
| `ADMIN_USER` | `admin` | 后台管理员账号 |
| `ADMIN_PASSWORD` | `123456` | 后台管理员密码 |
| `IMPORT_DEMO` | `yes` | 首启是否导入示例数据 |
| `APP_DEBUG` | `false` | |

国内服务器拉不动 GHCR 时换阿里云镜像:
```bash
docker run -d --name likeshop -p 20208:80 \
  -e MYSQL_ROOT_PASSWORD=root \
  registry.cn-hangzhou.aliyuncs.com/你的命名空间/likeshop:aio-latest
```

---

## 二、标准版:docker-compose(推荐生产)

镜像只包含 app,数据库/Redis 走独立容器,数据更易管理。

### 2.1 海外服务器

```bash
git clone https://github.com/op4219sr-bot/likeshop.git
cd likeshop
docker-compose -f docker-compose.prod.yml up -d
```

启动 3 个容器(`likeshop-app` + `likeshop-mysql` + `likeshop-redis`),30 秒后访问:
- 后台:`http://你的服务器/admin/login` 账号 `admin` / `123456`
- H5 商城:`http://你的服务器/mobile/`

### 2.2 中国大陆服务器

GHCR 和 Docker Hub 在国内拉取不稳定,走阿里云 ACR + 镜像加速。

#### 配置 docker daemon 镜像加速(一次性)

```bash
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json <<'EOF'
{
  "registry-mirrors": [
    "https://docker.mirrors.ustc.edu.cn",
    "https://hub-mirror.c.163.com",
    "https://mirror.baidubce.com"
  ]
}
EOF
sudo systemctl restart docker
```

这步解决 mysql / redis / php / nginx 等 Docker Hub 镜像拉不动的问题。

#### 启动 —— 两种方式任选其一

**方式 A:环境变量指定你的阿里云镜像**

```bash
git clone https://github.com/op4219sr-bot/likeshop.git
export LIKESHOP_IMAGE=registry.cn-hangzhou.aliyuncs.com/你的命名空间/likeshop:latest
docker-compose -f docker-compose.prod.cn.yml up -d
```

**方式 B:直接改 docker-compose.prod.cn.yml**

把 `app:` 下面的 `image:` 那一行改成你的实际地址,然后:
```bash
docker-compose -f docker-compose.prod.cn.yml up -d
```

### 2.3 仓库拥有者:让 GitHub Actions 自动推阿里云

在 https://github.com/op4219sr-bot/likeshop/settings/secrets/actions 添加 4 个 Secret:

| Secret | 示例值 |
|---|---|
| `ALIYUN_REGISTRY` | `registry.cn-hangzhou.aliyuncs.com` |
| `ALIYUN_NAMESPACE` | `op4219sr-bot`(你在 ACR 创建的命名空间) |
| `ALIYUN_USERNAME` | ACR 控制台 → 访问凭证 里的用户名 |
| `ALIYUN_PASSWORD` | ACR 访问凭证里设的固定密码 |

加完后下一次推 master 会同时推 4 个 tag:`:latest`、`:<sha>`、`:aio-latest`、`:aio-<sha>` 到 GHCR + 阿里云。没加这四个 Secret 工作流只推 GHCR(不会报错)。

> 首次推送前需要先在 ACR 控制台手动创建一个名为 `likeshop` 的仓库,或开启命名空间级「自动创建」。

---

## 三、环境变量(标准版)

两个 compose 文件都支持下面这些环境变量(在 docker-compose.yml 里改,或者同目录放 .env 文件):

| 变量 | 默认 | 说明 |
|---|---|---|
| `DB_HOST` | `mysql` | 数据库主机 |
| `DB_PORT` | `3306` | |
| `DB_USER` | `likeshop` | |
| `DB_PASSWORD` | `likeshop123` | 上线请改强密码 |
| `DB_NAME` | `likeshop` | |
| `DB_PREFIX` | `ls_` | 表前缀 |
| `ADMIN_USER` | `admin` | 首次安装的管理员账号 |
| `ADMIN_PASSWORD` | `123456` | 首次安装的管理员密码 |
| `IMPORT_DEMO` | `yes` | 首次启动是否导入示例数据 |
| `APP_DEBUG` | `false` | 调试模式 |

> 这些变量**只在第一次启动**生效(用于自动安装)。已初始化的数据库不会重新跑安装。

---

## 四、首次启动的自动安装流程

1. 等待 MySQL 就绪
2. 写出 `/server/.env`
3. 检查 `/server/config/install.lock`:
   - 不存在 → 跑建表 + 创建管理员 + touch lock 文件
   - 存在 → 跳过,直接启动服务

安装失败看:`docker-compose -f docker-compose.prod.yml logs app`(标准版)
或 `docker logs likeshop`(AIO)

---

## 五、数据持久化(标准版)

5 个 named volume,删容器不会丢数据:

| volume | 作用 |
|---|---|
| `likeshop_mysql` | MySQL 数据 |
| `likeshop_redis` | Redis 持久化 |
| `likeshop_runtime` | PHP 缓存 / 日志 |
| `likeshop_uploads` | 用户上传图片 |
| `likeshop_config` | install.lock |

AIO 版的对应路径:
| 路径 | 作用 |
|---|---|
| `/var/lib/mysql` | MariaDB 数据 |
| `/var/lib/redis` | Redis 持久化 |
| `/server/public/uploads` | 用户上传 |
| `/server/runtime` | PHP 缓存/日志 |
| `/server/config` | install.lock |

---

## 六、本地构建镜像(可选)

```bash
# 标准版
docker build -t likeshop:local .

# All-in-One
docker build -f Dockerfile.aio -t likeshop:aio-local .
```

---

## 七、对接易支付

镜像已包含易支付驱动。部署完后:
1. 单独把 `op4219sr-bot/epay` 用它自己的 docker-compose 跑起来(另一个端口,比如 8090)
2. 商城后台 → 设置 → 支付设置 → 易支付 → 填好网关地址 / PID / KEY → 启用

详见 [`doc/epay-install.md`](doc/epay-install.md)。

---

## 八、升级镜像

```bash
# 标准版(海外)
docker-compose -f docker-compose.prod.yml pull && docker-compose -f docker-compose.prod.yml up -d
# 标准版(国内)
docker-compose -f docker-compose.prod.cn.yml pull && docker-compose -f docker-compose.prod.cn.yml up -d

# AIO 版
docker pull ghcr.io/op4219sr-bot/likeshop:aio-latest
docker stop likeshop && docker rm likeshop
docker run -d --name likeshop -p 20208:80 -e MYSQL_ROOT_PASSWORD=root \
  -v likeshop-data:/var/lib/mysql \
  -v likeshop-uploads:/server/public/uploads \
  -v likeshop-config:/server/config \
  ghcr.io/op4219sr-bot/likeshop:aio-latest
```

数据都在 volume / named volume 里,镜像更新不影响。

---

## 九、故障排查

**标准版**:
```bash
docker-compose -f docker-compose.prod.yml ps
docker-compose -f docker-compose.prod.yml logs -f app
docker-compose -f docker-compose.prod.yml exec app bash
```

**AIO 版**:
```bash
docker ps
docker logs -f likeshop
docker exec -it likeshop bash
```

进容器后:
- PHP 错误日志:`/server/runtime/log/`
- nginx 日志:`/var/log/nginx/`
- supervisor 状态:`supervisorctl status`(AIO 版可看 mariadb/redis/php-fpm/nginx 各自状态)
- 重新触发安装(慎用,会覆盖 admin):`rm /server/config/install.lock && supervisorctl restart all`
