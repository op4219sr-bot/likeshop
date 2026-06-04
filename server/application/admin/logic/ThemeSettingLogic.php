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
     * 预设主题包.每个 preset 一组色板 + 适用场景描述.
     * 想加更多预设直接在这里加,前后端无需改动.
     */
    const PRESETS = [
        'default' => [
            'name' => '默认 (LikeShop 红)',
            'primary' => '#FF2C3C',
            'secondary' => '#FF6B35',
            'desc' => '原版 LikeShop 配色,适合服饰/数码/百货/综合电商',
        ],
        'pharmacy' => [
            'name' => '药品零售 (医疗绿)',
            'primary' => '#10B981',
            'secondary' => '#0EA5E9',
            'desc' => '清新干净的医疗绿+蓝,适合药品/医疗器械/保健品/健康食品',
        ],
        'food' => [
            'name' => '食品餐饮 (温暖橙)',
            'primary' => '#F97316',
            'secondary' => '#FBBF24',
            'desc' => '温暖明亮的橙色,适合餐饮/食品/生鲜/外卖电商',
        ],
        'minimal' => [
            'name' => '极简灰',
            'primary' => '#374151',
            'secondary' => '#6B7280',
            'desc' => '稳重低调,适合家居/办公/工业品/B2B',
        ],
        'fashion' => [
            'name' => '时尚紫',
            'primary' => '#8B5CF6',
            'secondary' => '#EC4899',
            'desc' => '潮流时尚紫粉,适合女装/美妆/饰品/潮品',
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
        return true;
    }

    /**
     * 老库自愈:给 ls_dev_auth 加上「主题设置」菜单(若缺失).
     * 同样的自愈模式之前用于 epay 行,既保证新装/老装都能用.
     */
    public static function ensureMenu()
    {
        $exists = Db::name('dev_auth')
            ->where(['uri' => 'themeSetting/index', 'type' => 1])
            ->find();
        if ($exists) {
            return;
        }
        // pid=56 是「商城设置」,菜单 id 在 280 之后(seed 表里的 AUTO_INCREMENT 起点)
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
     * 容错: 缺 #、3 位短写、空白等都能识别. 非法值回退到 #FF2C3C.
     */
    private static function normalizeHex($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '#FF2C3C';
        if ($v[0] !== '#') $v = '#' . $v;
        // #RGB → #RRGGBB
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $v, $m)) {
            return '#' . $m[1].$m[1] . $m[2].$m[2] . $m[3].$m[3];
        }
        if (preg_match('/^#[0-9a-f]{6}$/i', $v)) {
            return strtoupper($v);
        }
        return '#FF2C3C';
    }
}
