<?php
// +----------------------------------------------------------------------
// | 主题设置逻辑
// +----------------------------------------------------------------------

namespace app\admin\logic;

use app\common\server\ConfigServer;
use think\Db;

class ThemeSettingLogic
{
    /**
     * 预设主题包.每个 preset = 一组色板 + 行业布局特征(layout_traits).
     * layout_traits 对应 H5 首页 .main 容器下的真实板块:
     *   radius   - 卡片/图片圆角: none / small / medium / large
     *   density  - 板块间距密度: compact / normal / loose
     *   nav_cols - 导航宫格每行列数(4 或 5)
     *   hide     - 隐藏的板块, 取值用首页真实板块名:
     *              seckill(秒杀) special(促销专区) hot(热销榜) newgoods(新品) newsbar(资讯条) nav(导航宫格)
     */
    const PRESETS = [
        'default' => [
            'name' => '默认 (LikeShop 红)',
            'primary' => '#FF2C3C',
            'secondary' => '#FF6B35',
            'desc' => '原版 LikeShop 配色与布局,适合服饰/数码/百货/综合电商',
            'layout_traits' => [
                'radius' => 'medium', 'density' => 'normal', 'nav_cols' => 5, 'hide' => [],
            ],
        ],
        'pharmacy' => [
            'name' => '药品零售 (医疗绿)',
            'primary' => '#10B981',
            'secondary' => '#0EA5E9',
            'desc' => '医疗专业风:医疗绿配色、方正小圆角、4列分类宫格、隐藏秒杀和促销专区,适合药品/医疗器械/保健品',
            'layout_traits' => [
                'radius' => 'small', 'density' => 'compact', 'nav_cols' => 4,
                'hide' => ['seckill', 'special'],
            ],
        ],
        'food' => [
            'name' => '食品餐饮 (温暖橙)',
            'primary' => '#F97316',
            'secondary' => '#FBBF24',
            'desc' => '亲和食欲风:温暖橙配色、大圆角、保留秒杀促销,适合餐饮/食品/生鲜/外卖',
            'layout_traits' => [
                'radius' => 'large', 'density' => 'normal', 'nav_cols' => 5, 'hide' => [],
            ],
        ],
        'minimal' => [
            'name' => '极简灰',
            'primary' => '#374151',
            'secondary' => '#6B7280',
            'desc' => '高端极简风:灰黑配色、零圆角、大留白、隐藏几乎所有营销板块只留商品,适合家居/办公/工业品/B2B',
            'layout_traits' => [
                'radius' => 'none', 'density' => 'loose', 'nav_cols' => 4,
                'hide' => ['seckill', 'special', 'hot', 'newgoods', 'newsbar'],
            ],
        ],
        'fashion' => [
            'name' => '时尚紫',
            'primary' => '#8B5CF6',
            'secondary' => '#EC4899',
            'desc' => '潮流时尚风:紫粉配色、大圆角大卡片、隐藏秒杀突出商品,适合女装/美妆/饰品/潮品',
            'layout_traits' => [
                'radius' => 'large', 'density' => 'normal', 'nav_cols' => 5,
                'hide' => ['seckill'],
            ],
        ],
    ];

    /**
     * 读取当前已保存的主题配置(给后台页 + API 用)
     */
    public static function getConfig()
    {
        return [
            'preset' => ConfigServer::get('theme', 'preset', 'default'),
            'primary_color' => ConfigServer::get('theme', 'primary_color', '#FF2C3C'),
            'secondary_color' => ConfigServer::get('theme', 'secondary_color', '#FF6B35'),
            'apply_h5' => intval(ConfigServer::get('theme', 'apply_h5', 1)),
            'apply_admin' => intval(ConfigServer::get('theme', 'apply_admin', 0)),
        ];
    }

    /**
     * 根据 preset 拿到 layout traits.custom / 未知 用 default 的.
     */
    public static function getTraits($preset)
    {
        if (!isset(self::PRESETS[$preset])) {
            $preset = 'default';
        }
        return self::PRESETS[$preset]['layout_traits'] ?? self::PRESETS['default']['layout_traits'];
    }

    /**
     * 保存主题配置
     */
    public static function save($post)
    {
        $preset = $post['preset'] ?? 'default';
        if ($preset === 'custom') {
            $primary = self::normalizeHex($post['primary_color'] ?? '#FF2C3C');
            $secondary = self::normalizeHex($post['secondary_color'] ?? '#FF6B35');
        } else {
            $cfg = self::PRESETS[$preset] ?? self::PRESETS['default'];
            $primary = $cfg['primary'];
            $secondary = $cfg['secondary'];
        }
        ConfigServer::set('theme', 'preset', $preset);
        ConfigServer::set('theme', 'primary_color', $primary);
        ConfigServer::set('theme', 'secondary_color', $secondary);
        ConfigServer::set('theme', 'apply_h5', intval($post['apply_h5'] ?? 1));
        ConfigServer::set('theme', 'apply_admin', intval($post['apply_admin'] ?? 0));

        // 联动 likeshop 自带 decoration 开关(服务端直接不渲染对应板块)
        if ($preset !== 'custom') {
            self::applyDecorationToggles(self::getTraits($preset));
        }

        // 关键: 直接改 H5 编译包里的品牌色, 实现全站(按钮/导航/tabbar/秒杀条/价格)彻底换色
        $applyH5 = intval($post['apply_h5'] ?? 1);
        self::applyH5Color($applyH5 ? $primary : '#FF2C3C');

        return true;
    }

    /**
     * 按 layout traits 同步 likeshop decoration 开关(热销/新品 服务端渲染开关)
     */
    private static function applyDecorationToggles($traits)
    {
        $hide = $traits['hide'] ?? [];
        ConfigServer::set('decoration', 'index_setting_hots', in_array('hot', $hide) ? 0 : 1);
        ConfigServer::set('decoration', 'index_setting_news', in_array('newgoods', $hide) ? 0 : 1);
    }

    /**
     * 直接改写 H5 编译产物里的品牌色.
     * H5 是 uniapp 编译包, 品牌红 #FF2C3C 被编进 JS(primaryColor 数据属性 + 注入式 CSS 类
     * + tabBar selectedColor)和少量 CSS. 仅靠运行时 CSS 覆盖无法命中内联 style 绑定,
     * 所以这里直接把静态包里的 #FF2C3C 全部替换成主题色 —— 全站一次性变色.
     *
     * 始终从 .orig 原始备份生成, 可反复切换/还原; 选默认主题则写回原始色.
     */
    public static function applyH5Color($primary)
    {
        $base = app()->getRootPath() . 'public/mobile';
        if (!is_dir($base)) {
            return;
        }
        $isDefault = (empty($primary) || strtoupper($primary) === '#FF2C3C');

        $files = array_merge(
            (array) glob($base . '/static/*.css'),
            (array) glob($base . '/static/js/*.js')
        );
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $orig = $file . '.orig';
            if (!is_file($orig)) {
                @copy($file, $orig); // 首次备份原始文件
            }
            $src = is_file($orig) ? $orig : $file;
            $content = file_get_contents($src);
            if ($content === false) {
                continue;
            }
            if (!$isDefault) {
                $content = str_ireplace('#ff2c3c', $primary, $content);
            }
            @file_put_contents($file, $content);
        }

        // index.html 静态引用加 ?v= 时间戳, 强制浏览器重新拉取(否则 7 天缓存看不到变化)
        self::bumpH5CacheVersion($base . '/index.html');
    }

    /**
     * 给 mobile/index.html 的 js/css 引用加/更新 ?v=<时间戳>, 破缓存.
     */
    private static function bumpH5CacheVersion($indexFile)
    {
        if (!is_file($indexFile)) {
            return;
        }
        $html = file_get_contents($indexFile);
        if ($html === false) {
            return;
        }
        $v = time();
        $html = preg_replace_callback(
            '#(/mobile/static/[^"\'\s?]+\.(?:js|css))(\?v=\d+)?#',
            function ($m) use ($v) {
                return $m[1] . '?v=' . $v;
            },
            $html
        );
        @file_put_contents($indexFile, $html);
    }

    /**
     * 老库自愈:给 ls_dev_auth 加上「主题设置」菜单(若缺失).
     */
    public static function ensureMenu()
    {
        $exists = Db::name('dev_auth')
            ->where(['uri' => 'themeSetting/index', 'type' => 1])
            ->find();
        if ($exists) {
            return;
        }
        Db::name('dev_auth')->insert([
            'type' => 1,
            'system' => 0,
            'pid' => 56,
            'name' => '主题设置',
            'icon' => '',
            'uri' => 'themeSetting/index',
            'sort' => 90,
            'disable' => 0,
            'create_time' => time(),
            'del' => 0,
            'partner_id' => 0,
        ]);
    }

    /**
     * 把任意用户输入的颜色字符串规范化为 #RRGGBB.
     */
    private static function normalizeHex($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '#FF2C3C';
        if ($v[0] !== '#') $v = '#' . $v;
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $v, $m)) {
            return '#' . $m[1].$m[1] . $m[2].$m[2] . $m[3].$m[3];
        }
        if (preg_match('/^#[0-9a-f]{6}$/i', $v)) {
            return strtoupper($v);
        }
        return '#FF2C3C';
    }
}
