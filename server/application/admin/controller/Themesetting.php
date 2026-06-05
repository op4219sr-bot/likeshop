<?php
// 大小写转发文件: ThinkPHP 在某些入口会按全小写中段解析为 Themesetting,
// Linux 区分文件名大小写,这里把请求转发到真正实现 ThemeSetting.php.
// PHP 类名大小写不敏感,加载后 Themesetting / ThemeSetting 都能命中同一个类.
require_once __DIR__ . '/ThemeSetting.php';
