# Likeshop Docker 部署指南

镜像由 GitHub Actions 自动构建,推送到 GitHub Container Registry。
> 拉取地址:`ghcr.io/op4219sr-bot/likeshop:latest`

## 一、最快启动(整套环境)

只要服务器装了 docker + docker-compose 即可:

```bash
# 1. 拉一份仓库（只用 docker-compose.prod.yml 这一个文件其实也行)
git clone https://github.com/op4219sr-bot/likeshop.git
cd likeshop

# 2. 启动
docker-compose -f docker-compose.prod.yml up -d

# 3. 等 30 秒,然后访问
#   PC 后台:http://你的服务器/admin/login
#   H5 商城:http://你的服务器/mobile/
#   默认管理员:admin / 123456
```

启动 3 个容器:
- `likeshop-app` — nginx + php-fpm(80 端口对外)
- `likeshop-mysql` — MySQL 5.7
- `likeshop-redis` — Redis 6

## 二、环境变量(可在 docker-compose.prod.yml 修改)

| 变量 | 默认 | 说明 |
|---|---|---|
| `DB_HOST` | `mysql` | 数据库主机(同 compose 内服务名) |
| `DB_PORT` | `3306` | |
| `DB_USER` | `likeshop` | |
| `DB_PASSWORD` | `likeshop123` | 上线请改成强密码 |
| `DB_NAME` | `likeshop` | |
| `DB_PREFIX` | `ls_` | 表前缀,改了会重新建表 |
| `ADMIN_USER` | `admin` | 首次安装的管理员账号 |
| `ADMIN_PASSWORD` | `123456` | 首次安装的管理员密码,**请在登录后到后台改掉** |
| `IMPORT_DEMO` | `yes` | 首次启动是否导入示例数据(商品/分类等) |
| `APP_DEBUG` | `false` | 调试模式 |

> 这些变量**只在第一次启动**生效（用于自动安装）。已经初始化过的数据库不会被重新跑安装。

## 三、首次启动的自动安装

容器启动后 entrypoint 会:

1. 等待 MySQL 就绪
2. 写出 `/server/.env`(根据上面的环境变量)
3. 检查 `/server/config/install.lock`:
   - 不存在 → 跑 `auto_install.php`(复用仓库自带的 `installModel`),建库 / 建表 / 写入管理员账号 / 创建 lock 文件
   - 存在 → 跳过,直接启动服务

如果安装失败,容器会退出并打印错误。`docker-compose logs app` 即可看到原因(常见原因:数据库还没就绪、密码错误、端口被占)。

## 四、数据持久化

挂了 4 个 named volume,删容器不会丢数据:

| volume | 作用 |
|---|---|
| `likeshop_mysql` | MySQL 数据 |
| `likeshop_redis` | Redis 持久化 |
| `likeshop_runtime` | PHP 缓存 / 日志 |
| `likeshop_uploads` | 用户上传图片 |
| `likeshop_config` | install.lock |

备份:`docker run --rm -v likeshop_mysql:/data -v $PWD:/backup alpine tar czf /backup/mysql.tgz /data`

## 五、本地构建镜像(可选)

如果不想用 GHCR 上的镜像,把 `docker-compose.prod.yml` 里 `image:` 注释掉,放开 `build:` 那两行,然后:

```bash
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d
```

## 六、对接易支付

镜像已经包含易支付驱动。部署完后:
1. 单独把 `op4219sr-bot/epay` 用它自己的 docker-compose 跑起来(在另一个端口,比如 8090)
2. 商城后台 → 设置 → 支付设置 → 易支付 → 填好网关地址 / PID / KEY → 启用

详见 [`doc/epay-install.md`](doc/epay-install.md)。

## 七、升级镜像

```bash
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d
```

数据(库 + 上传 + 配置)都在 volume 里,镜像更新不影响。

## 八、故障排查

```bash
docker-compose -f docker-compose.prod.yml ps           # 容器是否都 Up
docker-compose -f docker-compose.prod.yml logs -f app  # 看应用日志
docker-compose -f docker-compose.prod.yml exec app bash # 进容器
```

进容器后:
- PHP 错误日志:`/server/runtime/log/`
- nginx 日志:`/var/log/nginx/`
- 重新触发安装(慎用,会覆盖 admin):`rm /server/config/install.lock && supervisorctl restart all`
