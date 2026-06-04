<?php
// +----------------------------------------------------------------------
// | 主题设置 - 前端 H5 商城 + 后台主题色
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\logic\ThemeSettingLogic;

class ThemeSetting extends AdminBase
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
