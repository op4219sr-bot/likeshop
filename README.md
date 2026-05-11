 <a href="https://www.likeshop.cn">![likeshop全开源商城](https://server.likeshop.cn/uploads/gitad/likeshop.png?aa=1)</a><br>
<a href="https://www.buildingai.cc">![BuildingAI](https://server.likeshop.cn/uploads/gitad/fastbuildAI.png)</a><br>
 <h1 align="center">likeshop全开源商城系统（已集成易支付/彩虹易支付）</h1>
 <h4 align="center">🚀快速开发  🛠️代码易懂  ✅方便二开  🧑‍💻源码全开源  💳支持易支付</h4> 
 <p align="center">
<a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-7-8892bf"></a> <a href="https://www.tslang.cn/"> <a href="#"><img src="https://img.shields.io/badge/ThinkPHP-5.1-6fb737"></a> <a href="#"><img src="https://img.shields.io/badge/Vue.js-2-4eb883"></a> <a href="#"> <a href="#"><img src="https://img.shields.io/badge/Layui-2.7-118675"></a> <a href="https://uniapp.dcloud.io/"><img src="https://img.shields.io/badge/uniapp--d85806"></a> <a href="https://www.nuxtjs.cn/"><img src="https://img.shields.io/badge/Nuxt.js--18bc78"></a>
</p>
<div align="center">
  <img src="/server/public/readme/likeshop.png" /><br>
  <center class="half">
<img  width="19%"  src="/server/public/readme/mobile-1.png" />
<img  width="19%"  src="/server/public/readme/mobile-2.png" />
<img  width="19%"  src="/server/public/readme/mobile-3.png" />
<img  width="19%"  src="/server/public/readme/mobile-4.png" />
<img  width="19%"  src="/server/public/readme/mobile-5.png" />
</center> <br>
</div>
 
## 🚀🚀🚀docker本地一句命令快速部署体验
### 🐳快速部署
本仓库在原版 LikeShop 基础上**集成了易支付（彩虹易支付 / 自建免签支付）**，并打包成单容器镜像（PHP-FPM + Nginx + MariaDB + Redis 全在一个容器里）。镜像由 GitHub Actions 每次 master 提交自动构建并推送到 GHCR，无需自行构建。

安装启动 [docker](https://www.docker.com/) 之后，在终端运行以下命令即可体验。<br>
  ```shell
   docker run -d --name likeshop -p 20208:80 -e MYSQL_ROOT_PASSWORD=root ghcr.io/op4219sr-bot/likeshop:aio-latest
  ```
如果需要自定义参数永久挂载数据，在终端运行以下命令，其中"【】"改成自定义参数。
```shell
docker run -d --name likeshop \
-v 【主机存储数据库路径】:/var/lib/mysql \
-v 【主机存储Redis数据路径】:/var/lib/redis \
-v 【主机存储用户上传文件路径】:/server/public/uploads \
-v 【主机存储install.lock路径】:/server/config \
-p 【访问端口】:80 \
-e MYSQL_ROOT_PASSWORD=【Mysql密码】 \
ghcr.io/op4219sr-bot/likeshop:aio-latest
```

> 中国大陆服务器拉取 GHCR 不稳定，可改用阿里云 ACR 镜像（需自行配置仓库命名空间），详见 [DOCKER.md](DOCKER.md)。
>
> 生产环境推荐使用 **三容器分离部署**（app + mysql + redis 各自独立），避免数据与容器生命周期绑定，详见 [`docker-compose.prod.yml`](docker-compose.prod.yml)。

### ⚠️注意
1.首次启动需要 60-90 秒（初始化数据库 + 跑安装脚本），如出现无法访问请稍候后再试。<br>
2.默认管理员账号 `admin`，默认密码 `123456`，**登录后请立即修改**。<br>
3.默认 `MYSQL_ROOT_PASSWORD=root`，**生产环境务必改成强密码**（详见下面「🔒安全建议」一节）。<br>
4.单容器把数据库塞进容器内，方便快速体验，生产环境建议至少挂 `/var/lib/mysql` 和 `/server/public/uploads` 两个 volume。<br>
5.不熟悉 docker 请勿用于生产环境，可能造成数据丢失等问题。

### 🛜访问
PC端管理后台：http://127.0.0.1:20208/admin/account/login
<br>PC端前台：http://127.0.0.1:20208/pc/
<br>手机端前台：http://127.0.0.1:20208/mobile/

---

## 🔒 安全建议（上线前务必看一下）

### 默认密码现在会不会被攻破？

**结论：默认 `MYSQL_ROOT_PASSWORD=root` 在本镜像里不会被外网直接攻破**，但仍强烈建议改。

镜像内部的 MariaDB 配置为 `--bind-address=127.0.0.1`，**只监听容器内部回环地址**，不接受任何来自外网/宿主机的连接。`docker run` 也只映射了 `-p 20208:80`（HTTP 端口），**没有暴露 3306 数据库端口**。三道防线：

| 防线 | 内容 |
|---|---|
| ① 容器内 MariaDB | `bind-address=127.0.0.1`，不接受非容器内连接 |
| ② Docker 端口映射 | 只暴露 80 端口，3306 没映射 |
| ③ 宿主机防火墙 | 默认 3306 不在云服务器安全组里 |

所以外网攻击者**根本接触不到** MySQL，再弱的密码也穷举不了。

但**仍然建议改**，原因：

- 万一以后要调试数据库需要把端口暴露出来，忘记改密码就裸奔
- 如果商城代码出现 SQL 注入漏洞，弱密码会让攻击者更容易提权
- 上线产品时审计 / 等保 / 客户检查时 `root/root` 会被直接打回

### 改密码方式 A：**新部署直接指定**（推荐，最简单）

启动时把 `MYSQL_ROOT_PASSWORD` 改成你的强密码：

```bash
docker rm -fv likeshop
docker volume prune -f
docker run -d --name likeshop -p 20208:80 \
  -e MYSQL_ROOT_PASSWORD='你的强密码请改这里' \
  ghcr.io/op4219sr-bot/likeshop:aio-latest
```

> ⚠️ 密码里如果有 `$` `!` `\` `'` 等特殊字符，记得用**单引号**包起来，不然 bash 会预先解释。

### 改密码方式 B：**已经在用的容器，原地改**（不丢数据）

把下面三条命令里的 `NewStrongPassword` 都换成**同一个**新密码，依次跑：

```bash
# 1. 改 MySQL 里 root 用户的密码
docker exec likeshop mysql -h 127.0.0.1 -uroot -proot -e "
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NewStrongPassword';
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY 'NewStrongPassword';
FLUSH PRIVILEGES;
"

# 2. 改容器里的 /server/.env，让 PHP 用新密码连库
docker exec likeshop sed -i "s/^password = root\$/password = NewStrongPassword/" /server/.env

# 3. 重启容器（让 php-fpm 重新读 .env）
docker restart likeshop
```

改完用 `docker exec likeshop cat /server/.env` 确认 `[database]` 段的 `password = ` 后面是新密码。

### 强密码生成

```bash
openssl rand -base64 24
```

跑一下会出来类似 `kT4w8jR2vXpQ9mN3yL6sZ7bU5eH1iA8c` 这种 24 字符串，可直接当密码用。

### 其他上线前安全清单

| # | 建议 | 怎么做 |
|---|---|---|
| 1 | **改管理员密码** | 后台登录后右上角 `admin` → 个人 → 修改密码（默认 `123456` 太弱） |
| 2 | **改对外端口** | 把 `-p 20208:80` 换成 `-p <随机高位端口>:80`，避免被扫描器一眼盯上 |
| 3 | **配置 HTTPS** | 有域名的话用 nginx 反向代理 + Let's Encrypt 证书套一层 HTTPS |
| 4 | **云安全组最小开放** | 腾讯云/阿里云 → 安全组入站规则 → 只放行实际需要的端口（80/443/SSH） |
| 5 | **fail2ban / 登录限速** | 防止有人对管理后台暴力破解 |
| 6 | **定期备份** | `docker exec likeshop mysqldump -uroot -p<密码> likeshop > backup_$(date +%F).sql` |

---

## 💳易支付（彩虹易支付）配置 — 本仓库新增功能

本仓库相比原版 LikeShop **新增了易支付支付通道**，适用于已有彩虹易支付/自建免签支付平台、或者无法直接对接微信/支付宝官方支付接口的场景（如个人开发者、小型商户）。

### 后台配置入口

容器跑起来 + 登录后台后：

`后台 → 设置 → 支付设置 → 易支付 → 配置`

填写以下字段：

| 字段 | 说明 |
|---|---|
| **网关地址** | 易支付平台的 API 网关，例如 `https://pay.yoursite.com/`，**末尾要带斜杠** |
| **商户 PID** | 易支付平台为你分配的商户 ID |
| **商户密钥 KEY** | 易支付平台为你分配的商户密钥（务必保密） |
| **默认支付方式** | 留空让用户在收银台选；或填 `wxpay` / `alipay` 等强制单一类型 |
| **站点名称** | 显示在支付页面的网站名（可选） |
| **图标** | 上传一张支付图标（必填，否则无法启用） |
| **状态** | 启用 |

保存后，**前端商城下单收银台就会出现「易支付」选项**，用户点击后跳转到你的易支付网关页面完成付款，付款成功后异步回调商城更新订单状态。

### 适用场景

- ✅ H5、PC 端商城（推荐）
- ✅ APP 端商城（通过 H5 跳转）
- ⛔ 微信小程序 / 微信公众号场景下不可用（小程序内 webview 限制 + 微信侧域名审核）

### 涉及代码

| 文件 | 用途 |
|---|---|
| `server/application/common/server/EpayServer.php` | 易支付网关签名 + 通信 + 回调验签 |
| `server/application/admin/controller/PayConfig.php` | 后台 `editEpay` 配置入口 |
| `server/application/admin/logic/PayConfigLogic.php` | 配置逻辑 + `ensureEpayRow()`（自动补齐 `ls_dev_pay` 表数据） |
| `server/application/admin/view/pay_config/edit_epay.html` | 后台配置表单 |
| `server/application/api/controller/Payment.php` | EPAY 跳转 + 异步回调入口 |
| `server/application/api/logic/PaymentLogic.php` | 支付路由分发，新增 EPAY 分支 |

完整对接文档：[`doc/epay-install.md`](doc/epay-install.md)

---


## likeshop单商户标准版商城演示
### 移动端商城
![移动端演示.png](/server/public/readme/gitee/yszx.png)

### PC管理后台
PC管理后台演示： [https://php-b2c-demo.likeshop.cn/admin](https://php-b2c-demo.likeshop.cn/admin)
账号：admin 密码：123456

### PC商城
PC端访问链接：[https://php-b2c.likeshop.cn/pc](https://php-b2c.likeshop.cn/pc)


更多产品介绍，欢迎访问likeshop官方网站:[www.likeshop.cn](https://www.likeshop.cn)
<br><br><br>






## likeshop「开源精神」
![gitee头图 –0817.png](/server/public/readme/gitee/toutu.png)<br>

### LikeShop 开源商城系统介绍
<br>

LikeShop 开源团队专注于电商交易领域，致力于打造易部署、易运营、易扩展的新一代开源商城系统。我们重点解决以下核心问题：

 <br>

### 一、降低部署门槛，提升上线效率
<br>

系统提供标准化安装流程与清晰的引导界面，支持多种运行环境（Linux / Windows），并提供完整的部署文档与一键安装方案，帮助用户快速完成环境搭建与系统上线。

 <br>

### 二、简化运营配置，快速进入业务状态


后台设计以“开箱即用”为原则，核心配置路径清晰，包括：

+ 微信公众号 / 小程序配置
+ 微信支付 / 支付宝支付接入
+ 短信服务（阿里云 / 腾讯云）
+ 对象存储（七牛云 / 阿里云 OSS / 腾讯云 COS）

同时提供系统化运营文档，帮助用户快速完成从配置到实际运营的转化。

 <br>

### 三、强调代码可读性与可扩展性


LikeShop 坚持开源初心，注重代码结构清晰与开发友好：

+ 代码逻辑清晰，降低理解成本
+ 避免过度封装，提升二次开发效率
+ 提供完整开发文档（目录结构 / 数据字典 / API / 开发规范）

适用于二开项目、定制开发及长期维护场景。

 <br>

### 四、提供高效沟通与技术支持机制


相比传统社区问答模式，LikeShop 提供更直接的技术沟通渠道，提升问题反馈与解决效率，帮助开发者与团队更快推进项目。

 <br>

### 五、合理开源与可持续发展模式


LikeShop 采用“开源 + 商业支持”模式：

+ 免费版本满足完整基础业务需求
+ 付费版本提供持续更新与增强能力
+ 收费策略透明，确保项目长期稳定迭代

目标是在保障开源生态的同时，实现产品持续发展。

 <br>

### 免费版与付费版区别说明
#### 免费企业版（当前仓库版本）

+ 版本：v3.0.3
+ 完整开源，支持商用（需保留版权信息）
+ 功能完整：分销 / 拼团 / 砍价 / 抽奖 / 支付等均可使用
+ 包含端：H5、小程序、APP、后台管理
+ 不包含：PC 商城端
+ 不支持：在线升级与自动更新

 <br>

### 付费企业版


+ 持续更新版本（领先免费版）
+ 支持在线升级与更新服务
+ 包含完整端口：H5、小程序、APP、PC商城、后台
+ 可移除版权标识
+ 提供更完善的技术支持与服务体系

 <br>

### 为什么开放完整企业版能力？


LikeShop 选择开放完整能力的核心原因是：

让更多用户能够真正落地电商项目，而不是被工具限制。

相比功能限制型开源策略，我们更倾向于：

+ 提供完整能力 → 提升用户成功率
+ 降低试错成本 → 提升实际转化价值
+ 通过服务与增值能力实现商业闭环

 <br>

### 使用建议


LikeShop 适用于以下用户群体：

+ 电商企业：搭建私域商城，实现用户沉淀与转化
+ 软件公司：作为稳定的电商解决方案基础进行交付
+ 开发者：学习与实践企业级电商系统开发
+ 教学与学生：用于课程实践或毕业设计项目

 <br>

### 核心总结


+ LikeShop 是一个完整开源、支持商用、易二开、低门槛部署的电商商城系统
+ 提供完整业务功能 + 清晰开发文档 + 可持续更新机制
+ 适用于电商落地、系统开发等多种场景

<br>

### likeshop 「多沟通交流」
我们喜欢直接的沟通交流，请加群或者客服吧。

<br>

### 联系微信客服（专业解答、获取功能清单）
![联系微信客服.png](/server/public/readme/gitee/lxwm.png)

小提示：当你预算购买付费企业版时，联系微信客服是有优惠的，请添加她们吧。

<br>

### 加入微信群 | QQ群
![qun.jpg](/server/public/readme/gitee/qun.jpg)

QQ群：192683602



## 项目技术栈
### 📡服务端
<a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-7.2-8892bf"></a> <a href="https://www.thinkphp.cn/"><img src="https://img.shields.io/badge/ThinkPHP-5.1-6fb737"></a><a href="https://www.mysql.com/"> <img src="https://img.shields.io/badge/Mysql-5.7-315a80"></a><a href="https://redis.io/"> <img src="https://img.shields.io/badge/Redis-6-d12222"></a><a href="https://redis.io/"> <img src="https://img.shields.io/badge/Docker--139cff"></a>

<br>

### 💻PC端管理后台
<a href="https://www.mysql.com/"> <img src="https://img.shields.io/badge/Layui-2.7-118675"></a>
服务端渲染

<br>

### 📱移动端前台
<a href="https://uniapp.dcloud.io/"><img src="https://img.shields.io/badge/uniapp--d85806"></a> <a href="https://cn.vuejs.org/"><img src="https://img.shields.io/badge/Vue.js-2-4eb883"></a>
前后端分离、已适配微信小程序、手机h5页面、安卓app、苹果app。

<br>

### 💻PC端前台
<a href="https://cn.vuejs.org/"><img src="https://img.shields.io/badge/Vue.js-2-4eb883"></a> <a href="https://www.nuxtjs.cn/"><img src="https://img.shields.io/badge/Nuxt.js--18bc78"></a>
前后端分离

<br>

### 功能特性
    代码逻辑注释清晰，非常方便二次开发。
    支持PHP7，执行效率翻倍，遵循PSR-4开发规范。
    支持composer，内置优秀php开发sdk，方便二次开发。
    支持docker部署，内置docker-compose容器编排，一句命令自动配置项目运行环境。
    支持管理后台配置定时任务，并记录定时任务运行日志。
    支持七牛云、阿里云、腾讯云多种OSS对象存储，提升项目访问速度，降低服务器成本。
    支持CDN配置，加快各地方访问速度。
    支持商城多种场景的足迹气泡，让商城跟用户有更强的互动性。
    支持商城首页、商城分类页、用户个人中心页、底部导航装修。
    支持广告位，可在商城多个地方编辑添加广告。
    支持5种佣金提现方式，更有微信零钱到账，零钱可直接到用户微信钱包。
    💳【本仓库新增】支持易支付（彩虹易支付 / 自建免签支付）通道，适合个人开发者、小型商户接入第三方支付。
    下载体验更多功能！

<br>
    
### 开发文档
https://www.likeshop.cn/doc/2
运营文档、开发文档、API文档一应俱全。

<br>

### 官方网站
https://www.likeshop.cn/

<br>


## likeshop单商户商城系统「产品说明」
### 产品定位
likeshop单商户商城系统，产品定位为B2C模式，类似京东自营商城。免费企业版和付费企业版功能基本相同，不再赘述之间的区别。

<br>

### 产品终端
![产品终端.png](/server/public/readme/gitee/cpzd.png?v=2)
### 产品功能
likeshop单商户商城系统具备PC商城、H5商城、微信小程序商城、APP商城，各商城终端数据打通，使用PC管理后台进行统一的数据管理。

<br>

likeshop单商户商城系统包含分销裂变，限时秒杀，拼团活动，砍价活动，优惠券，大转盘抽奖，每日签到，小票打印，积分商城，会员价，微信零钱到账，系统通知/短信通知/APP推送/微信模板消息/小程序消息提醒等常用丰富的营销模块。


<br>

联系客服获取完整PDF、Excel版本产品功能对照表。

<br>


## likeshop单商户 「界面预览」

### 《移动端商城界面》
![step01.png](/server/public/readme/gitee/m1.png?v=2)
![step02.png](/server/public/readme/gitee/m2.png)
![step03.png](/server/public/readme/gitee/m3.png)
![step04.png](/server/public/readme/gitee/m4.png)
![step05.png](/server/public/readme/gitee/m5.png)
![step06.png](/server/public/readme/gitee/m6.png)
![step07png](/server/public/readme/gitee/m7.png)
![step08png](/server/public/readme/gitee/m8.png)
![step09.png](/server/public/readme/gitee/m9.png)
![step10.png](/server/public/readme/gitee/m10.png)
![step11.png](/server/public/readme/gitee/m11.png)
### 《PC端商城界面》
![pc_step01.png](/server/public/readme/gitee/pc1.png)
![pc_step03.png](/server/public/readme/gitee/pc2.png)
![pc_step04.png](/server/public/readme/gitee/pc3.png)
### 《PC端管理后台》
![ht_step01.png](/server/public/readme/gitee/admin1.png)
![ht_step02.png](/server/public/readme/gitee/admin2.png)
![ht_step03.png](/server/public/readme/gitee/admin3.png)
![ht_step04.png](/server/public/readme/gitee/admin4.png)
![ht_step05.png](/server/public/readme/gitee/admin5.png)
![ht_step07.png](/server/public/readme/gitee/admin6.png)

<br>

### **LikeShop 单商户版｜特别说明**
付费企业版在版本迭代上持续领先免费企业版，主要体现在功能优化、稳定性提升以及新特性的优先发布。

免费企业版提供完整功能能力，支持商用，但需保留官方版权标识，用于维护开源项目的品牌传播与生态发展。

---

LikeShop 单商户商城系统经过长期持续研发与迭代，投入了稳定的技术资源与团队支持。为了保障项目能够持续更新与提供可靠服务，我们采用“开源 + 商业支持”的模式：

+ 免费版本用于降低使用门槛，支持项目快速落地
+ 付费版本用于提供持续更新、升级能力与增值服务

版权标识与授权机制是开源项目健康发展的重要组成部分。若有去除版权或使用更高版本的需求，建议通过官方授权方式获取对应服务与支持。

---

我们将持续专注于产品研发与体验优化，同时通过规范化机制保护项目成果，确保 LikeShop 能够长期稳定发展。

<br>

## likeshop单商户 「版权证书」
![版权.png](/server/public/readme/gitee/csbq.png)

<br>

本项目包含的第三方源码和二进制文件之版权信息另行标注。

likeshop系列产品版权归likeshop团队所有且原创研发。本文档最终解释权归likeshop团队所有。


<br>
<br>




