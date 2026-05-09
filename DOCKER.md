# Likeshop Docker 部署指南

镜像由 GitHub Actions 自动构建,同时推送到 **两个镜像仓库**:

| 场景 | 拉取地址 |
|---|---|
| 海外 / 能访问 GitHub | `ghcr.io/op4219sr-bot/likeshop:latest` |
| 中国大陆 | `registry.cn-hangzhou.aliyuncs.com/op4219sr-bot/likeshop:latest`(示例,以实际配置为准） |

## 一、最快启动(海外服务器)

```bash
git clone https://github.com/op4219sr-bot/likeshop.git
cd likeshop
docker-compose -f docker-compose.prod.yml up -d
```

启动 3 个容器(`likeshop-app` + `likeshop-mysql` + `likeshop-redis`),30 秒后访问:
- 后台:`http://你的服务器/admin/login` 账号 `admin` / `123456`
- H5 商城:`http://你的服务器/mobile/`

## 二、中国大陆服务器部署

GHCR 和 Docker Hub 在国内拉取不稳定,走阿里云 ACR + 镜像加速。

### 2.1 为 docker daemon 配置镜像加速(一次性)

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

### 2.2 启动 —— 两种方式任选其一

**方式 A:环境变量指定你的阿里云镜像**

```bash
git clone https://github.com/op4219sr-bot/likeshop.git   # 如果这步也慢可以用镜像站
export LIKESHOP_IMAGE=registry.cn-hangzhou.aliyuncs.com/你的命名空间/likeshop:latest
docker-compose -f docker-compose.prod.cn.yml up -d
```

**方式 B:直接改 docker-compose.prod.cn.yml**

把 `app:` 下面的 `image:` 那一行改成你的实际地址,然后:
```bash
docker-compose -f docker-compose.prod.cn.yml up -d
```

### 2.3 如果你是仓库拥有者,希望自动推镜像到阿里云

在 https://github.com/op4219sr-bot/likeshop/settings/secrets/actions 添加 4 个 Secret:

| Secret | 示例值 |
|---|---|
| `ALIYUN_REGISTRY` | `registry.cn-hangzhou.aliyuncs.com` |
| `ALIYUN_NAMESPACE` | `op4219sr-bot`(你在 ACR 创建的命名空间) |
| `ALIYUN_USERNAME` | 阿里云账号名 (不是主账号,是 ACR 控制台 → 访问凭证 里的那个) |
| `ALIYUN_PASSWORD` | 你在 ACR 访问凭证里设的固定密码 |

加完后下一次推 master 会自动同时推到 GHCR + 阿里云。没加这四个 Secret 的话工作流只推 GHCR(不会报错)。

> 注意:首次推送前需要先在 ACR 控制台手动创建一个名为 `likeshop` 的仓库,或者在命名空间级别开启「自动创建仓库」。

## 三、环境变量

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
| `ADMIN_PASSWORD` | `123456` | 首次安装的管理员密码,登录后请到后台改掉 |
| `IMPORT_DEMO` | `yes` | 首次启动是否导入示例数据 |
| `APP_DEBUG` | `false` | 调试模式 |

> 这些变量**只在第一次启动**生效(用于自动安装)。已初始化的数据库不会重新跑安装。

## 四、首次启动的自动安装流程

1. 等待 MySQL 就绪
2. 写出 `/server/.env`
3. 检查 `/server/config/install.lock`:
   - 不存在 → 跑建表 + 创建管理员 + touch lock 文件
   - 存在 → 跳过,直接启动服务

安装失败看:`docker-compose -f docker-compose.prod.yml logs app`

## 五、数据持久化

5 个 named volume,删容器不会丢数据:

| volume | 作用 |
|---|---|
| `likeshop_mysql` | MySQL 数据 |
| `likeshop_redis` | Redis 持久化 |
| `likeshop_runtime` | PHP 缓存 / 日志 |
| `likeshop_uploads` | 用户上传图片 |
| `likeshop_config` | install.lock |

## 六、本地构建镜像(可选)

```bash
docker build -t likeshop:local .
# 然后在 docker-compose 里把 image 改成 likeshop:local
```

## 七、对接易支付

镜像已包含易支付驱动。部署完后:
1. 单独把 `op4219sr-bot/epay` 用它自己的 docker-compose 跑起来(另一个端口,比如 8090)
2. 商城后台 → 设置 → 支付设置 → 易支付 → 填好网关地址 / PID / KEY → 启用

详见 [`doc/epay-install.md`](doc/epay-install.md)。

## 八、升级镜像

```bash
# 海外
docker-compose -f docker-compose.prod.yml pull && docker-compose -f docker-compose.prod.yml up -d
# 国内
docker-compose -f docker-compose.prod.cn.yml pull && docker-compose -f docker-compose.prod.cn.yml up -d
```

数据(库 + 上传 + 配置)都在 volume 里,镜像更新不影响。

## 九、故障排查

```bash
docker-compose -f docker-compose.prod.yml ps
docker-compose -f docker-compose.prod.yml logs -f app
docker-compose -f docker-compose.prod.yml exec app bash
```

进容器后:
- PHP 错误日志:`/server/runtime/log/`
- nginx 日志:`/var/log/nginx/`
- 重新触发安装(慎用,会覆盖 admin):`rm /server/config/install.lock && supervisorctl restart all`
