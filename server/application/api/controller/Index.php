<?php
// +----------------------------------------------------------------------
// | likeshop开源商城系统
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

   public function lists(){
        $lists = IndexLogic::lists($this->user_id);
        return $this->_success('',$lists);
   }

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

    public function appInit()
    {
        $data = [
            'wechat_login' =>  ConfigServer::get('app', 'wechat_login', '',0),
            'agreement' => ConfigServer::get('app', 'agreement', '',1)
        ];
        $this->_success('', $data);
    }

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
     * 主题色 + 行业布局 CSS 注入 - H5 商城 index.html 通过 <link> 加载.
     * 支持 ?preview=<preset> 临时预览(不改 DB), 后台 iframe 预览用.
     * 颜色部分: 保存时已直接改 JS 包(全站彻底变色), 这里的颜色规则主要服务于"预览"近似展示.
     * 布局部分: 用首页真实 class 隐藏板块 / 改宫格列数 / 改圆角 / 改密度.
     * @notes 本仓库新增
     */
    public function themeCss()
    {
        $preview = $this->request->get('preview', '');
        $applyH5 = intval(ConfigServer::get('theme', 'apply_h5', 1));

        if ($preview && isset(ThemeSettingLogic::PRESETS[$preview])) {
            $cfg = ThemeSettingLogic::PRESETS[$preview];
            $preset = $preview;
            $primary = $cfg['primary'];
            $secondary = $cfg['secondary'];
            $traits = $cfg['layout_traits'];
            $applyH5 = 1;
        } else {
            $preset = ConfigServer::get('theme', 'preset', 'default');
            $primary = ConfigServer::get('theme', 'primary_color', '#FF2C3C');
            $secondary = ConfigServer::get('theme', 'secondary_color', '#FF6B35');
            $traits = ThemeSettingLogic::getTraits($preset);
        }

        if (!$applyH5 || ($preset === 'default' && $preview === '')) {
            $css = "/* theme disabled or default */";
        } else {
            $css = self::buildThemeCss($primary, $secondary, $traits, $preset);
        }

        $response = \think\Response::create($css, 'html', 200);
        $response->header([
            'Content-Type'  => 'text/css; charset=utf-8',
            'Cache-Control' => $preview ? 'no-store' : 'public, max-age=60',
        ]);
        return $response;
    }

    /**
     * 根据主题色 + layout traits 生成 H5 首页 CSS, 用的是 pages/index/index.vue 里的真实 class.
     */
    private static function buildThemeCss($primary, $secondary, $traits, $preset)
    {
        $p = htmlspecialchars($primary, ENT_QUOTES);
        $s = htmlspecialchars($secondary, ENT_QUOTES);

        // 圆角(用 px, 因为注入 CSS 不经过 uniapp 的 rpx->px 转换)
        $radiusMap = [
            'none'   => ['card' => '0',    'btn' => '0'   ],
            'small'  => ['card' => '4px',  'btn' => '4px' ],
            'medium' => ['card' => '10px', 'btn' => '10px'],
            'large'  => ['card' => '18px', 'btn' => '40px'],
        ];
        $r = $radiusMap[$traits['radius'] ?? 'medium'];

        // 密度: 板块间距
        $gapMap = ['compact' => '6px', 'normal' => '14px', 'loose' => '30px'];
        $gap = $gapMap[$traits['density'] ?? 'normal'];

        // 导航宫格列数 -> 每个 nav-item 宽度
        $navCols = intval($traits['nav_cols'] ?? 5);
        $navCols = $navCols >= 4 ? $navCols : 5;
        $navW = round(100 / $navCols, 4) . '%';

        // 隐藏板块: 首页真实 class
        $hideMap = [
            'seckill'  => '.seckill',
            'special'  => '.special-area',
            'hot'      => '.hot',
            'newgoods' => '.new-goods',
            'newsbar'  => '.information',
            'nav'      => '.nav',
        ];
        $hideSel = [];
        foreach (($traits['hide'] ?? []) as $key) {
            if (isset($hideMap[$key])) {
                $hideSel[] = $hideMap[$key];
            }
        }
        $hideRule = $hideSel ? (implode(',', $hideSel) . "{display:none !important;}\n") : '';

        return <<<CSS
/* ============ 主题: {$preset}  主色: {$p}  辅助色: {$s} ============ */
:root{ --theme-primary: {$p}; --theme-secondary: {$s}; }

/* ---- 颜色覆盖(预览近似; 保存后 JS 包已全站变色) ---- */
.primary,.u-type-primary,.price,.text-price,.cart-num,.red,.text-red,.activity-header .title{
  color: {$p} !important;
}
.bg-primary,.u-type-primary-bg,.bg-red{ background-color: {$p} !important; }
uni-button[type=primary],button[type=primary],.u-btn--primary,.buy-btn,.cart-btn,.confirm-btn,.dec{
  background-color: {$p} !important; border-color: {$p} !important;
}
[style*="color: #FF2C3C"],[style*="color:#FF2C3C"]{ color: {$p} !important; }
[style*="background-color: #FF2C3C"],[style*="background-color:#FF2C3C"],
[style*="background: #FF2C3C"],[style*="background:#FF2C3C"]{ background-color: {$p} !important; }

/* ---- 圆角(行业风格核心区分) ---- */
.seckill uni-image,.hot uni-image,.new-goods uni-image,.special-area uni-image,.goods uni-image,
.special-area .item,.nav-icon,.goods .goods-item,[class*="goods-item"]{
  border-radius: {$r['card']} !important; overflow: hidden;
}
uni-button,.buy-btn,.cart-btn,.confirm-btn,.dec{ border-radius: {$r['btn']} !important; }

/* ---- 导航宫格列数 ---- */
.nav-list .nav-item{ width: {$navW} !important; }

/* ---- 板块间距(密度) ---- */
.nav,.information,.special-area,.seckill,.hot,.new-goods,.goods{ margin-top: {$gap} !important; }

/* ---- 按行业隐藏板块 ---- */
{$hideRule}
CSS;
    }

    public function copyright()
    {
        $result = IndexLogic::copyright();
        $this->_success('', $result);
    }
}
