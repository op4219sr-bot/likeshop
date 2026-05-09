<?php
/**
 * Likeshop 自动安装脚本（容器首次启动时执行）
 * 复用 server/public/install/model.php 的 installModel 逻辑
 */

// install.php 里的常量
define('install', true);
define('INSTALL_ROOT', '/server/public/install');
define('TESTING_TABLE', 'config');

require '/server/public/install/model.php';
require '/server/public/install/YxEnv.php';

$post = [
    'host'           => getenv('DB_HOST') ?: 'mysql',
    'port'           => getenv('DB_PORT') ?: '3306',
    'user'           => getenv('DB_USER') ?: 'likeshop',
    'password'       => getenv('DB_PASSWORD') ?: 'likeshop123',
    'name'           => getenv('DB_NAME') ?: 'likeshop',
    'prefix'         => getenv('DB_PREFIX') ?: 'ls_',
    'admin_user'     => getenv('ADMIN_USER') ?: 'admin',
    'admin_password' => getenv('ADMIN_PASSWORD') ?: '123456',
    'clear_db'       => getenv('CLEAR_DB') ?: 'off',
    'import_test_data' => getenv('IMPORT_DEMO') === 'yes' ? 'on' : 'off',
];

echo "[auto-install] DB: {$post['user']}@{$post['host']}:{$post['port']}/{$post['name']} prefix={$post['prefix']}\n";
echo "[auto-install] Admin: {$post['admin_user']}\n";

$model = new installModel();

// 跑建表 + 写入管理员
$result = $model->checkConfig($post['name'], $post);
if ($result->result !== 'ok') {
    fwrite(STDERR, "[auto-install] FAIL: {$result->error}\n");
    exit(1);
}

if ($post['import_test_data'] === 'on') {
    if (!$model->importDemoData()) {
        fwrite(STDERR, "[auto-install] demo data import failed (continuing without)\n");
    }
}

// .env 已经由 entrypoint.sh 写好，这里只创建锁文件
if (!$model->mkLockFile()) {
    fwrite(STDERR, "[auto-install] failed to create install.lock\n");
    exit(1);
}

echo "[auto-install] done.\n";
