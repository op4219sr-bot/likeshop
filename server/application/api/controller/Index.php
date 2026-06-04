<?php
// +----------------------------------------------------------------------
// | likeshop开源商城系统
// +----------------------------------------------------------------------
// | 欢迎阅读学习系统程序代码，建议反馈是我们前进的动力
// | gitee下载：https://gitee.com/likeshop_gitee
// | github下载：https://github.com/likeshop-github
// | 访问官网：https://www.likeshop.cn
// | 访问社区：https://home.likeshop.cn
// | 访问手册：http://doc.likeshop.cn
// | 微信公众号：likeshop技术社区
// | likeshop系列产品在gitee、github等公开渠道开源版本可免费商用，未经许可不能去除前后端官方版权标识
// |  likeshop系列产品收费版本务必购买商业授权，购买去版权授权后，方可去除前后端官方版权标识
// | 禁止对系统程序代码以任何目的，任何形式的再发布
// | likeshop团队版权所有并拥有最终解释权
// +----------------------------------------------------------------------
// | author: likeshop.cn.team
// +----------------------------------------------------------------------

namespace app\api\controller;
use app\admin\logic\ThemeSettingLogic;
use app\api\logic\IndexLogic;
use app\common\model\Client_;
use app\common\model\MessageScene_;
use app\common\server\ConfigServer;
use app\common\server\UrlServer;
use think\facade\Hook;
use think\Db;
class Index extends ApiBase
{
   public $like_not_need_login = ['test', 'lists', 'appInit', 'downLine', 'share', 'config','pcLists','copyright','themeCss'];
    /**
     * note 首页接口
     * create_time 2020/10/21 19:05
     */
   public function lists(){
        $lists = IndexLogic::lists($this->user_id);
        return $this->_success('',$lists);
   }

    /**
     * app下载链接  todo lr未完成
     */
    public function downLine()
    {
        $get = $this->request->get();
        $check = $this->validate($get, 'app\api\validate\App');
        if (true !== $check) {
            $this->_error($check);
        }
        if(isset($get['client']) && $get['client'] == Client_::ios){
            $this->_success('', ['line' => ConfigServer::get('app', 'line_ios', '')]);
        }else{
            $this->_success('', ['line' => ConfigServer::get('app', 'line_android', '')]);
        }
    }
    /**
     * app初始化接口
     * 苹果不允许单独只有微信第三方登录
     */
    public function appInit()
    {
        $data = [
            'wechat_login' =>  ConfigServer::get('app', 'wechat_login', '',0),//微信登录
            //弹出协议
            'agreement' => ConfigServer::get('app', 'agreement', '',1)
        ];
        $this->_success('', $data);
    }

    /**
     * Notes: 获取分享信息
     * @author 张无忌(2021/1/20 17:04)
     * @return array|mixed|string
     */
    public function share()
    {
        $client = $this->request->get('client', Client_::mnp, 'intval');
        $config = [];
        switch ($client) {
            case Client_::mnp:
                $config = ConfigServer::get('share', 'mnp', [
                    'mnp_share_title' => '',
                    'mnp_share_image' => ''
                ]);
                if (!empty($config['mnp_share_image']) and $config['mnp_share_image'] !== '') {
                    $config['mnp_share_image'] = UrlServer::getFileUrl($config['mnp_share_image']);
                }
                break;
            case Client_::oa:
                $config = ConfigServer::get('share', 'h5', [
                    'h5_share_title' => '',
                    'h5_share_intro' => '',
                    'h5_share_image' => ''
                ]);
                if (!empty($config['h5_share_image']) and $config['h5_share_image'] !== '') {
                    $config['h5_share_image'] = UrlServer::getFileUrl($config['h5_share_image']);
                }
                break;
            case Client_::android:
            case Client_::ios:
                $config = ConfigServer::get('share', 'app', [
                    'app_share_title' => '',
                    'app_share_intro' => '',
                    'app_share_image' => ''
                ]);
                if (!empty($config['app_share_image']) and $config['app_share_image'] !== '') {
                    $config['app_share_image'] = UrlServer::getFileUrl($config['app_share_image']);
                }
                break;
        }
        return $this->_success('获取成功', $config);
    }


    /**
     * Notes: 设置
     * @author 段誉(2021/2/25 15:39)
     */
    public function config()
    {
        $navigation = Db::name('dev_navigation')
          ->field('name,selected_icon,un_selected_icon')
          ->where('del', 0)
          ->order('id', 'desc')
          ->select();
        foreach($navigation as &$item) {
          $item['selected_icon'] =  empty($item['selected_icon']) ? '' : UrlServer::getFileUrl($item['selected_icon']);
          $item['un_selected_icon'] =  empty($item['un_selected_icon']) ? '' : UrlServer::getFileUrl($item['un_selected_icon']);
        }
        $config = [
            'register_setting' => ConfigServer::get('register_setting', 'open', 0),
            'app_wechat_login' => ConfigServer::get('app', 'wechat_login', 0),
            'shop_login_logo'  => UrlServer::getFileUrl(ConfigServer::get('website', 'shop_login_logo')),
            'web_favicon'      => UrlServer::getFileUrl(ConfigServer::get('website', 'web_favicon')),
            'name'             => ConfigServer::get('website', 'name'),
            'copyright_info'   => ConfigServer::get('copyright', 'company_name'),
            'icp_number'       => ConfigServer::get('copyright', 'number'),
            'icp_link'         => ConfigServer::get('copyright', 'link'),
            'app_agreement'    => ConfigServer::get('app', 'agreement', 0),
            'ios_download'     => ConfigServer::get('app', 'line_ios', ''),
            'android_download' => ConfigServer::get('app', 'line_android', ''),
            'download_doc'     => ConfigServer::get('app', 'download_doc', ''),
            'cate_style'       => ConfigServer::get('decoration', 'layout_no', 1),
            'index_setting' => [
              'logo' => ConfigServer::get('decoration', 'index_setting_logo', 1),
              'hots' => ConfigServer::get('decoration', 'index_setting_hots', 1),
              'news' => ConfigServer::get('decoration', 'index_setting_news', 1),
              'top_bg_image' => UrlServer::getFileUrl(ConfigServer::get('decoration', 'index_setting_top_bg_image', ''))
            ],
            'center_setting' => [
              'top_bg_image' => UrlServer::getFileUrl(ConfigServer::get('decoration', 'center_setting_top_bg_image', ''))
            ],
            'navigation_setting' => [
              'ust_color' => ConfigServer::get('decoration', 'navigation_setting_ust_color', '#000000'),
              'st_color' => ConfigServer::get('decoration', 'navigation_setting_st_color', '#000000'),
            ],
            'navigation_menu' => $navigation,
            'website_name' => ConfigServer::get('website', 'name'),
            'theme' => [
                'preset'          => ConfigServer::get('theme', 'preset', 'default'),
                'primary_color'   => ConfigServer::get('theme', 'primary_color', '#FF2C3C'),
                'secondary_color' => ConfigServer::get('theme', 'secondary_color', '#FF6B35'),
                'apply_h5'        => intval(ConfigServer::get('theme', 'apply_h5', 1)),
            ]
        ];
        $this->_success('', $config);
    }


    /**
     * 主题色 + 行业布局 CSS 注入 - H5 商城在 index.html 启动前通过 <link> 加载.
     * 支持 ?preview=<preset> 临时预览任意主题(不修改后台保存的配置), 后台 iframe 预览用.
     * @notes 本仓库新增
     */
    public function themeCss()
    {
        $preview = $this->request->get('preview', '');
        $applyH5 = intval(ConfigServer::get('theme', 'apply_h5', 1));

        // 预览模式: 从 PRESETS 拿配置; 正常模式: 从 DB 拿配置
        if ($preview && isset(ThemeSettingLogic::PRESETS[$preview])) {
            $cfg = ThemeSettingLogic::PRESETS[$preview];
            $preset = $preview;
            $primary = $cfg['primary'];
            $secondary = $cfg['secondary'];
            $traits = $cfg['layout_traits'];
            $applyH5 = 1; // 预览强制启用
        } else {
            $preset = ConfigServer::get('theme', 'preset', 'default');
            $primary = ConfigServer::get('theme', 'primary_color', '#FF2C3C');
            $secondary = ConfigServer::get('theme', 'secondary_color', '#FF6B35');
            $traits = ThemeSettingLogic::getTraits($preset);
        }

        // 主题未启用 或 默认主题 -> 输出空 CSS, 对未动主题的安装零影响
        if (!$applyH5 || ($preset === 'default' && $preview === '')) {
            $css = "/* theme disabled or default */";
        } else {
            $css = self::buildThemeCss($primary, $secondary, $traits, $preset);
        }

        $response = \think\Response::create($css, 'html', 200);
        $response->header([
            'Content-Type'  => 'text/css; charset=utf-8',
            // 预览不缓存,正常缓存 60 秒
            'Cache-Control' => $preview ? 'no-store' : 'public, max-age=60',
        ]);
        return $response;
    }

    /**
     * 根据主题色 + layout traits 生成完整 CSS
     * 主要做两件事:
     * 1. 用主题色覆盖所有用到默认红的位置(文字/背景/边框/常用类名)
     * 2. 根据 traits 调整圆角、密度、隐藏/突出板块,做出行业差异
     */
    private static function buildThemeCss($primary, $secondary, $traits, $preset)
    {
        $p = htmlspecialchars($primary, ENT_QUOTES);
        $s = htmlspecialchars($secondary, ENT_QUOTES);

        // 圆角等级映射
        $radius = [
            'none'   => ['card' => '0',    'btn' => '0',    'banner' => '0',    'badge' => '0'   ],
            'small'  => ['card' => '4px',  'btn' => '4px',  'banner' => '4px',  'badge' => '2px' ],
            'medium' => ['card' => '8px',  'btn' => '8px',  'banner' => '8px',  'badge' => '4px' ],
            'large'  => ['card' => '16px', 'btn' => '24px', 'banner' => '16px', 'badge' => '12px'],
        ];
        $r = $radius[$traits['radius'] ?? 'medium'];

        // 密度等级映射 (内边距)
        $density = [
            'loose'   => ['pad' => '24px', 'gap' => '20px'],
            'normal'  => ['pad' => '12px', 'gap' => '12px'],
            'compact' => ['pad' => '6px',  'gap' => '6px' ],
        ];
        $d = $density[$traits['density'] ?? 'normal'];

        // 要隐藏的板块: 关键词匹配, H5 不同版本类名可能不同, 多重命中
        $hideSelectors = [];
        $hideMap = [
            'promo' => [
                '.bargain', '.lottery', '.luck-draw', '.luckdraw',
                '[class*="bargain"]', '[class*="lottery"]', '[class*="luckdraw"]', '[class*="luck-draw"]',
                '.home-bargain', '.home-lottery', '.marketing-bargain',
            ],
            'news' => [
                '.new-recommend', '.news-recommend', '[class*="new-recommend"]', '[class*="news-recommend"]',
                '.home-news', '.recommend-news',
            ],
            'hots' => [
                '.hot-sale', '.hots', '[class*="hot-sale"]', '[class*="hots-list"]',
                '.home-hots', '.hot-rank',
            ],
            'signin' => [
                '.sign-in', '.signin', '[class*="sign-in"]', '[class*="signin"]', '.daily-sign',
            ],
            'distribution' => [
                '.distribution', '[class*="distribution"]', '.distributor', '.commission-entry',
            ],
        ];
        foreach (($traits['hide'] ?? []) as $key) {
            if (isset($hideMap[$key])) {
                $hideSelectors = array_merge($hideSelectors, $hideMap[$key]);
            }
        }
        $hideRule = '';
        if ($hideSelectors) {
            $hideRule = implode(',', $hideSelectors) . "{display:none !important;}\n";
        }

        // ===== 输出 CSS =====
        return <<<CSS
/* 主题: {$preset}  主色: {$p}  辅助色: {$s} */
:root{
  --theme-primary: $p;
  --theme-secondary: $s;
  --theme-card-radius: {$r['card']};
  --theme-btn-radius: {$r['btn']};
  --theme-banner-radius: {$r['banner']};
  --theme-badge-radius: {$r['badge']};
  --theme-section-pad: {$d['pad']};
  --theme-section-gap: {$d['gap']};
}

/* ===== 主题色覆盖 ===== */
[style*="color: #FF2C3C"],[style*="color:#FF2C3C"],
[style*="color: rgb(255, 44, 60)"],[style*="color:rgb(255,44,60)"]{
  color: var(--theme-primary) !important;
}
[style*="background: #FF2C3C"],[style*="background:#FF2C3C"],
[style*="background-color: #FF2C3C"],[style*="background-color:#FF2C3C"]{
  background-color: var(--theme-primary) !important;
}
[style*="border-color: #FF2C3C"],[style*="border-color:#FF2C3C"]{
  border-color: var(--theme-primary) !important;
}
.primary,.color-red,.red,.text-red,.cart-num,.price,.text-price{
  color: var(--theme-primary) !important;
}
.bg-red,.bg-primary,.btn-primary,.cart-btn,.buy-btn,.confirm-btn{
  background-color: var(--theme-primary) !important;
}

/* ===== 圆角统一 (行业风格的核心区分点) ===== */
.banner,.swiper,[class*="banner"]{
  border-radius: var(--theme-banner-radius) !important;
  overflow: hidden;
}
.product-card,.goods-card,.cart-item,[class*="product-card"],[class*="goods-card"]{
  border-radius: var(--theme-card-radius) !important;
  overflow: hidden;
}
button,.btn,.layui-btn,.u-btn,.confirm-btn,.buy-btn,.cart-btn{
  border-radius: var(--theme-btn-radius) !important;
}
.tag,.badge,.label,[class*="tag-"],[class*="badge-"]{
  border-radius: var(--theme-badge-radius) !important;
}

/* ===== 板块隐藏 (按 layout traits) ===== */
$hideRule

CSS;
    }


    /**
     * @notes 版权资质
     * @author ljj
     * @date 2022/2/22 3:09 下午
     */
    public function copyright()
    {
        $result = IndexLogic::copyright();
        $this->_success('', $result);
    }
}