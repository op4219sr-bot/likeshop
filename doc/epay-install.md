# 易支付（彩虹易支付）接入说明

本商城已接入彩虹易支付（自建免签支付），后台 → 支付设置 → 易支付 即可配置。

## 一、前提

- 已自行部署 epay 系统并能正常访问（`https://你的网关域名/`）
- 在 epay 商户后台创建商户，记下 **商户ID (PID)** 和 **商户密钥 (KEY)**
- KEY 类型选择 **MD5**（本系统使用 MD5 签名）

## 二、商城后台配置

1. 登录商城管理后台
2. 进入 **设置 → 支付设置**
3. 找到「易支付」一行点击「配置」
4. 填写：
   - **支付简称**：用户在收银台看见的名字（如：聚合支付）
   - **支付图标**：上传一张 100×100 的图标
   - **网关地址**：`https://你的网关域名`（不带尾部 `/`，也无需 `/submit.php`）
   - **商户ID (PID)**
   - **商户密钥 (KEY)**
   - **默认支付类型**：留空 = 让用户在易支付收银台选；填 `alipay` / `wxpay` / `qqpay` / `usdt` 等可锁定单一渠道
   - **站点名称**：可选，显示在易支付收银台
5. **状态** 选「启用」并保存

## 三、易支付商户后台配置

- 异步通知地址：`https://商城域名/api/payment/epayNotify`（无需手动填写，下单时已携带）
- 同步跳回地址：`https://商城域名/api/payment/epayReturn`（无需手动填写，下单时已携带）
- 如果开启了「域名白名单 / 强制授权域名」，请把商城域名加进去

## 四、支持的端

| 端 | 是否支持 |
|---|---|
| H5（手机网页） | ✅ |
| PC 商城 | ✅ |
| Android / iOS APP | ✅（webview 内跳出） |
| 微信小程序 | ❌（小程序内禁止跳外部 URL） |
| 微信公众号网页 | ❌（微信内置浏览器限制 alipay；也不允许跳出） |

后端在 `payway` 接口已自动隐藏小程序 / 公众号场景下的「易支付」选项，无需前端额外处理。

## 五、技术细节

- 驱动文件：`server/application/common/server/EpayServer.php`
- 支付分发：`server/application/common/logic/PaymentLogic.php`（case `Pay::EPAY`）
- 异步回调：`POST/GET /api/payment/epayNotify`
- 同步跳回：`GET /api/payment/epayReturn`
- 签名算法：剔除 `sign/sign_type/空值` → `ksort` → `k=v&k=v` → 末尾追加 KEY → `md5()`
- 返回格式：与支付宝 H5 一致 — 一段 HTML 自动提交表单，前端 `alipay()` 工具函数无需修改即可工作
- 数据库：首次保存配置时，会自动在 `dev_pay` 表插入 `code='epay'` 一行，无需手动建表数据

## 六、手动种子 SQL（可选）

如希望在初始化时就显示「易支付」一行（便于第一次进入「支付设置」就看到入口），可执行：

```sql
INSERT INTO `dev_pay` (`code`, `name`, `short_name`, `icon`, `sort`, `status`, `config`)
VALUES ('epay', '易支付', '易支付', '', 99, 0, '{"gateway":"","pid":"","key":"","default_type":"","site_name":""}');
```

> 不执行也没问题：首次保存时会自动插入。

## 七、常见问题

**Q：用户付款后订单没改成已支付？**
- 检查 epay 是否能访问商城域名（域名是否解析到商城服务器）
- 查看 `server/runtime/log` 下的日志是否有 `EpayServer-verifyNotify-...` 错误
- 在 epay 后台找到该笔订单，看 notify_url 推送是否成功（失败可手动重推）

**Q：签名错误？**
- 确认商户后台的密钥类型是 **MD5** 而不是 RSA
- 确认网关地址、PID、KEY 没有多余空格
