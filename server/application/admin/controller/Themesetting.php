<?php
// +----------------------------------------------------------------------
// | 主题设置 - 前端 H5 商城 + 后台主题色
// +----------------------------------------------------------------------
//
// 文件名注意:
// ThinkPHP 5.1 配置了 url_convert=true,会把 URL 控制器名先 strtolower
// 再 ucfirst,所以访问 `/admin/themeSetting/index` 实际查找的类名是
// `app\admin\controller\Themesetting`(中间的 S 是小写).
//
// likeshop 仓库原有的 ShopSetting/PayConfig 等控制器之所以能工作,是
// 因为 composer 在镜像构建时已经把它们注册进了 optimize 后的 classmap.
// 后期通过热更新或新写的控制器**没有这个 classmap 兜底**,只能走严格
// PSR-4 文件名匹配,Linux 区分大小写,文件名必须是 Themesetting.php.
//
// 因此这里采用 ThinkPHP 期望的 PascalCase 命名(首字母大写,其余小写).
// 同时在文件底部用 class_alias 暴露驼峰版别名,保持向后兼容.
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\logic\ThemeSettingLogic;

class Themesetting extends AdminBase
{
    /**
     * 主题设置入口
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            ThemeSettingLogic::save($post);
            $this->_success('保存成功,刷新页面即可看到效果');
        }
        // 进页面时顺便自愈菜单 + 加载现有配置
        ThemeSettingLogic::ensureMenu();
        $this->assign('config', ThemeSettingLogic::getConfig());
        $this->assign('presets', ThemeSettingLogic::PRESETS);
        $this->assign('presets_json', json_encode(ThemeSettingLogic::PRESETS, JSON_UNESCAPED_UNICODE));
        return $this->fetch();
    }
}

// 兼容别名: 如果有外部代码用驼峰版 ThemeSetting 引用,也能找到
class_alias('app\admin\controller\Themesetting', 'app\admin\controller\ThemeSetting');
