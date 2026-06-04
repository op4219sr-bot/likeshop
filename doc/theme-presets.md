# 主题预设说明

本仓库相对原版 LikeShop **新增了商城主题切换功能**,通过后台一键切换 H5 商城的整体配色,适配不同行业场景。

## 内置预设

| Key | 名称 | 主色 | 辅助色 | 适用行业 |
|---|---|---|---|---|
| `default` | 默认 LikeShop 红 | `#FF2C3C` | `#FF6B35` | 服饰/数码/百货/综合电商 |
| `pharmacy` | 药品零售 (医疗绿) | `#10B981` | `#0EA5E9` | 药品/医疗器械/保健品/健康食品 |
| `food` | 食品餐饮 (温暖橙) | `#F97316` | `#FBBF24` | 餐饮/食品/生鲜/外卖电商 |
| `minimal` | 极简灰 | `#374151` | `#6B7280` | 家居/办公/工业品/B2B |
| `fashion` | 时尚紫 | `#8B5CF6` | `#EC4899` | 女装/美妆/饰品/潮品 |
| `custom` | 自定义 | 用户挑 | 用户挑 | 品牌已有 VI 配色规范 |

## 后台入口

登录后台 → 设置 → 商城设置 → **主题设置**

或直接访问: `http://你的域名/admin/themeSetting/index`

## 工作原理

1. 后台保存到 `ls_dev_config` 表(type=`theme`,name=`preset`/`primary_color`/`secondary_color` 等)
2. API `/api/index/config` 在响应里附带 `theme` 字段
3. H5 商城在 `mobile/index.html` 启动时拉这个 API,把主色注入 CSS 变量 `--theme-primary` / `--theme-secondary`
4. 全局 CSS 规则覆盖常见的 `#FF2C3C` 用法

## 加新预设

编辑 `server/application/admin/logic/ThemeSettingLogic.php`,在 `PRESETS` 常量里加一项:

```php
'your_key' => [
    'name' => '你的主题名',
    'primary' => '#XXXXXX',
    'secondary' => '#YYYYYY',
    'desc' => '适合 xxx 行业 / xxx 场景',
],
```

保存后访问后台主题设置页,新预设会自动出现在卡片网格里。无需改前端、改 SQL。

## 已知限制(下个版本会解决)

- 当前只切换主色,不切换布局/组件结构。如果要做「不同行业的不同首页布局」,需要多套 uniapp 模板编译,工作量较大。
- 部分组件可能用了硬编码颜色(没用 CSS 变量),覆盖率约 85%。商品价格、CTA 按钮、底部导航、徽章等关键元素都能切。
- 后台 UI 主题颜色目前是 Layui 的 6 个固定主题色(`layui-bg-blue/red/green/orange/cyan/black`),将来可以支持任意自定义色。
