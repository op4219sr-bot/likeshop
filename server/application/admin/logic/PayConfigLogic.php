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

    public static function lists()
    {
        $payModel = new Pay();
        $count = $payModel->count();
        $lists = $payModel->order('sort')->select();
        $lists->append(['status_text']);
        return ['list' => $lists, 'count' => $count];
    }


    public static function info($pay_code)
    {
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
     * 易支付配置（首次编辑时若 pay 表无对应行则自动写入）
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

        $payModel = new Pay();
        $exists = Db::name('dev_pay')->where(['code' => 'epay'])->find();
        if ($exists) {
            return $payModel->allowField(true)->save($post, ['code' => 'epay']);
        }
        $insert = [
            'code'       => 'epay',
            'name'       => $post['name'] ?? '易支付',
            'short_name' => $post['short_name'] ?? '易支付',
            'icon'       => $post['icon'] ?? '',
            'sort'       => intval($post['sort'] ?? 0),
            'status'     => intval($post['status'] ?? 0),
            'config'     => $post['config'],
        ];
        return Db::name('dev_pay')->insert($insert);
    }

}
