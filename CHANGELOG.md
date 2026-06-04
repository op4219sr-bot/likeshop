# Changelog

记录本仓库相对原版 LikeShop 的修改 + 自身版本演进。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/),版本号遵循 [SemVer](https://semver.org/lang/zh-CN/)。

---

## [v1.0.0] - 2026-05-11

**首个稳定版本**,包含:易支付完整集成 + AIO 单容器镜像 + 6 个关键 bug 修复 + 性能优化 + 完整中文文档。

### 🎯 回滚锚点

如果未来某次升级后出现严重问题,可以无损回到这个版本:

- **Git 锚点**:`release/v1.0.0` 分支(永久指向 commit `3947186229a365c226d7915510055edd1e9dfc5f`)
- **Docker 镜像锚点**:`ghcr.io/op4219sr-bot/likeshop:aio-3947186229a365c226d7915510055edd1e9dfc5f`

回滚方法见 [README.md - 🔁 回滚到已知稳定版本](README.md#🔁-回滚到已知稳定版本v100) 一节。

### ✨ 新增

- **易支付(彩虹易支付 / 自建免签支付)** 完整支付通道
  - 后台 → 设置 → 支付设置 → 易支付 配置入口
  - 商城下单收银台「易支付」选项
  - 异步回调 + 验签 + 订单状态更新
  - 涉及文件:`EpayServer.php` / `PayConfig.php` / `PayConfigLogic.php` / `edit_epay.html` / `Payment.php` / `PaymentLogic.php`
- **All-in-One Docker 镜像**(`ghcr.io/op4219sr-bot/likeshop:aio-latest`)
  - 单容器集成 PHP-FPM + Nginx + MariaDB + Redis
  - 一行 `docker run` 即可起来
  - GitHub Actions 自动构建 + 推送到 GHCR + 阿里云 ACR(secrets 配置后)
- **三容器分离部署**(`docker-compose.prod.yml` / `docker-compose.prod.cn.yml`)适合生产环境
- **完整中文 README**,包含 6 大章节:
  - docker 部署
  - 服务器重启后自动启动
  - 安全建议(默认密码风险评估 + 改法 + 上线清单)
  - 易支付配置
  - 回滚到稳定版本
  - 易支付 + 微信小程序 / 公众号说明
- **DOCKER.md** 详细说明 AIO 镜像 / 阿里云 ACR / 三容器分离 三种部署方式
- **`auto_install.php`** 让首次启动自动跑 LikeShop 安装(无需访问 `/install/install.php`)
- **`ensureEpayRow()`** PayConfigLogic 自愈逻辑,老库自动补齐 `ls_dev_pay` 里缺失的 epay 行

### 🐛 修复

| 修复 | 现象 | commit |
|---|---|---|
| AIO 镜像 mariadb 初始化卡死 52 分钟 | `--skip-grant-tables` 导致 ALTER USER 失败,wait PID 永不退出 | `b443608` |
| AIO 镜像 datadir 已被 apt 预初始化 | `/var/lib/mysql` 不空,bootstrap 被跳过,root 密码未设 | `e8c6fd2` |
| `mariadbd --bootstrap` 模式拒绝 grant 操作 | 改用 `--init-file` 模式跑首次 SQL | `3b12f75` |
| `UrlServer.php` 空 `file_domain` 致 admin 500 | `Uninitialized string offset: -1`,补 fallback 到 `$_SERVER['HTTP_HOST']` | `851c045` |
| 微信浏览器打开 H5 弹「公众号配置出错」 | `WeChatLogic::jsConfig` 在 OA appid 未配时硬抛错,改为静默返回空 config | `078af43` |
| GHCR 镜像构建在 PR 上不触发 | docker.yml 工作流增加 `pull_request` + `claude/**` 触发器 | `ca37796` / `b7b29f2` |

### ⚡ 性能优化

- **PHP opcache** 完整配置(128M 内存 / 20000 文件槽 / realpath cache)
- **nginx gzip** 压缩 HTML/CSS/JS/JSON/SVG
- **静态资源浏览器缓存** `expires 7d` + `Cache-Control: public`,二次访问秒开
- 实测后台页面响应提速 **2-5x**

### 📚 文档

- README 加入完整的故障排查 + 安全建议 + 性能优化 + 易支付配置 + 回滚操作
- DOCKER.md 三种部署方式对照
- `doc/epay-install.md` 易支付对接详细文档

---

## 历史(原版 LikeShop)

历史变更见 [likeshop 官方仓库](https://gitee.com/likeshop_gitee/likeshop-php-b2c)。本仓库 fork 自 v3.0.3。
