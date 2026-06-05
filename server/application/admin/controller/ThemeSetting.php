<?php
// +----------------------------------------------------------------------
// | 主题设置 - 前端 H5 商城 + 后台主题色
// +----------------------------------------------------------------------
//
// 大小写双文件方案说明:
// ThinkPHP 5.1 在不同入口下解析出的控制器类名大小写不一致:
//   - 直接访问 /admin/themeSetting/index  → 解析为 Themesetting (中间 s 小写)
//   - 点击后台菜单 / url() 生成的链接      → 解析为 ThemeSetting (中间 S 大写)
// likeshop 自带控制器靠 composer 优化后的 classmap(大小写不敏感)兜底,
// 但本仓库后加的控制器不在那张 classmap 里,Linux 严格区分文件名大小写,
// 于是总有一种大小写找不到文件.
//
// 解决: 提供两个文件 —— 本文件是真实实现(class ThemeSetting),
// 同目录的 Themesetting.php 是一行 require_once 转发到本文件.
// PHP 类名大小写不敏感,任一文件被自动加载后,两种大小写都能命中该类,
// 且单次请求只会加载其中一个文件,不会重复声明.
//
// 视图用 __DIR__ 拼绝对路径加载,彻底绕开"控制器名转视图目录名"的大小写歧义.
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
            ThemeSettingLogic::save($this->request->post());
            $this->_success('保存成功,刷新页面即可看到效果');
        }
        // 进页面时顺便自愈菜单 + 加载现有配置
        ThemeSettingLogic::ensureMenu();
        $this->assign('config', ThemeSettingLogic::getConfig());
        $this->assign('presets', ThemeSettingLogic::PRESETS);
        // 用绝对路径加载视图,不依赖控制器名->视图目录名的大小写推断
        return $this->fetch(__DIR__ . '/../view/theme_setting/index.html');
    }
}
