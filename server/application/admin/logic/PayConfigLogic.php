<?php
// +----------------------------------------------------------------------
// | likeshop开源商城系统
// +----------------------------------------------------------------------
// | author: likeshop.cn.team
// +----------------------------------------------------------------------

namespace app\admin\logic;

use app\common\model\Pay;
use think\Db;

class PayConfigLogic
{

    /**
     * 列表页用的支付方式列表.
     * 每次进入时确保易支付那一行存在(给老库自动补齐,新装库由 like.sql 已经种好).
     */
    public static function lists()
    {
        self::ensureEpayRow();
        $payModel = new Pay();
        $count = $payModel->count();
        $lists = $payModel->order('sort')->select();
        $lists->append(['status_text']);
        return ['list' => $lists, 'count' => $count];
    }

    /**
     * 老库升级:若 ls_dev_pay 没有 epay 行就插一条占位行.
     */
    private static function ensureEpayRow()
    {
        $exists = Db::name('dev_pay')->where(['code' => 'epay'])->find();
        if ($exists) {
            return;
        }
        Db::name('dev_pay')->insert([
            'code'       => 'epay',
            'name'       => '易支付',
            'short_name' => '易支付',
            'icon'       => '',
            'sort'       => 4,
            'status'     => 0,
            'config'     => '{}',
        ]);
    }


    public static function info($pay_code)
    {
        if ($pay_code === 'epay') {
            self::ensureEpayRow();
        }
        $payModel = new Pay();
        $result = $payModel->where(['code' => $pay_code])->append(['status_text'])->find();
        return $result;
    }


    public static function editBalance($post)
    {
        $payModel = new Pay();
        return $payModel->allowField(true)->save($post, ['code' => 'balance']);
    }


    public static function editWechat($post)
    {
        $config = [
            'pay_sign_key' => $post['pay_sign_key'],
            'mch_id' => $post['mch_id'],
            'apiclient_cert' => $post['apiclient_cert'],
            'apiclient_key' => $post['apiclient_key']
        ];
        $post['config'] = json_encode($config, JSON_UNESCAPED_UNICODE);

        $payModel = new Pay();
        return $payModel->allowField(true)->save($post, ['code' => 'wechat']);
    }


    public static function editAlipay($post)
    {
        $config = [
            'app_id' => $post['app_id'],
            'private_key' => $post['private_key'],
            'ali_public_key' => $post['ali_public_key']
        ];
        $post['config'] = json_encode($config, JSON_UNESCAPED_UNICODE);
        $payModel = new Pay();
        return $payModel->allowField(true)->save($post, ['code' => 'alipay']);
    }


    /**
     * 易支付配置(首次编辑时若 pay 表无对应行则自动写入)
     */
    public static function editEpay($post)
    {
        $config = [
            'gateway'      => trim($post['gateway'] ?? ''),
            'pid'          => trim($post['pid'] ?? ''),
            'key'          => trim($post['key'] ?? ''),
            'default_type' => trim($post['default_type'] ?? ''),
            'site_name'    => trim($post['site_name'] ?? ''),
        ];
        $post['config'] = json_encode($config, JSON_UNESCAPED_UNICODE);

        self::ensureEpayRow();
        $payModel = new Pay();
        return $payModel->allowField(true)->save($post, ['code' => 'epay']);
    }

}
