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
     * 预设主题包.每个 preset 一组色板 + 行业布局特征.
     * layout_traits 控制 H5 商城的视觉/结构表现:
     *   radius   - 圆角大小(small/medium/large/none)
     *   density  - 信息密度(loose/normal/compact)
     *   hide     - 隐藏的板块(promo=砍价拼团抽奖, news=新品推荐, hots=热销, signin=签到, distribution=分销)
     *   emphasis - 突出的板块(category/banner/products)
     */
    const PRESETS = [
        'default' => [
            'name' => '默认 (LikeShop 红)',
            'primary' => '#FF2C3C',
            'secondary' => '#FF6B35',
            'desc' => '原版 LikeShop 配色,适合服饰/数码/百货/综合电商',
            'layout_traits' => [
                'radius' => 'medium',
                'density' => 'normal',
                'hide' => [],
                'emphasis' => 'banner',
            ],
        ],
        'pharmacy' => [
            'name' => '药品零售 (医疗绿)',
            'primary' => '#10B981',
            'secondary' => '#0EA5E9',
            'desc' => '医疗专业感:更小圆角、隐藏砍价/抽奖、紧凑分类网格,适合药品/医疗器械/保健品',
            'layout_traits' => [
                'radius' => 'small',
                'density' => 'compact',
                'hide' => ['promo', 'signin'],
                'emphasis' => 'category',
            ],
        ],
        'food' => [
            'name' => '食品餐饮 (温暖橙)',
            'primary' => '#F97316',
            'secondary' => '#FBBF24',
            'desc' => '亲和食欲感:大圆角、大 Banner、突出价格,适合餐饮/食品/生鲜/外卖',
            'layout_traits' => [
                'radius' => 'large',
                'density' => 'normal',
                'hide' => ['distribution'],
                'emphasis' => 'banner',
            ],
        ],
        'minimal' => [
            'name' => '极简灰',
            'primary' => '#374151',
            'secondary' => '#6B7280',
            'desc' => '高端低调:零圆角、隐藏所有营销板块、大留白,适合家居/办公/工业品/B2B',
            'layout_traits' => [
                'radius' => 'none',
                'density' => 'loose',
                'hide' => ['promo', 'news', 'signin', 'distribution'],
                'emphasis' => 'products',
            ],
        ],
        'fashion' => [
            'name' => '时尚紫',
            'primary' => '#8B5CF6',
            'secondary' => '#EC4899',
            'desc' => '潮流时尚感:大产品卡、突出新品、紫粉配色,适合女装/美妆/饰品/潮品',
            'layout_traits' => [
                'radius' => 'large',
                'density' => 'normal',
                'hide' => ['signin'],
                'emphasis' => 'products',
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
     * 根据 preset 拿到 layout traits.custom 用 default 的.
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
        // 用预设就拿预设的颜色; 选 custom 就用用户填的
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

        // 联动 likeshop 自带的 decoration 开关:根据 layout traits 自动调整营销板块显示
        if ($preset !== 'custom') {
            self::applyDecorationToggles(self::getTraits($preset));
        }
        return true;
    }

    /**
     * 根据 layout traits 调整 decoration 板块开关
     * 关闭的板块: H5 商城首页自动不显示对应板块
     */
    private static function applyDecorationToggles($traits)
    {
        $hide = $traits['hide'] ?? [];
        // hots: 热销榜
        ConfigServer::set('decoration', 'index_setting_hots', in_array('hots', $hide) ? 0 : 1);
        // news: 新品推荐
        ConfigServer::set('decoration', 'index_setting_news', in_array('news', $hide) ? 0 : 1);
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
