<?php
// +----------------------------------------------------------------------
// | likeshop开源商城系统
// +----------------------------------------------------------------------
// | author: likeshop.cn.team
// +----------------------------------------------------------------------

namespace app\api\controller;


use app\api\model\Order;
use app\common\model\Order as CommonOrder;
use app\common\model\Client_;
use app\common\server\AliPayServer;
use app\common\server\ConfigServer;
use app\common\server\EpayServer;
use app\common\server\WeChatPayServer;
use app\common\server\WeChatServer;
use app\common\logic\PaymentLogic;
use app\common\model\Pay;
use think\Db;

/**
 * 支付逻辑
 * Class Payment
 * @package app\api\controller
 */
class Payment extends ApiBase
{

    public $like_not_need_login = ['aliNotify', 'notifyMnp', 'notifyOa', 'notifyApp', 'epayNotify', 'epayReturn'];


    public function prepay()
    {
        $post = $this->request->post();
        if(!isset($post['from']) || !isset($post['order_id']) || !isset($post['pay_way'])) {
            $this->_error('参数缺失');
        }
        switch ($post['from']) {
            case 'order':
                $order = Order::get($post['order_id']);
                if ($order['order_status'] == CommonOrder::STATUS_CLOSE || $order['del'] == 1) {
                    $this->_error('订单已关闭');
                }
                break;
            case 'recharge':
                $order = Db::name('recharge_order')->where(['id' => $post['order_id']])->find();
                break;
        }
        if (empty($order)) {
            $this->_error('订单不存在');
        }
        $order['pay_way'] = $post['pay_way'];
        if ($order['pay_status'] == Pay::ISPAID) {
            $this->_success('支付成功', ['order_id' => $order['id']], 10000);
        }

        $result = PaymentLogic::pay($post['from'], $order, $this->client);
        if (false === $result) {
            $this->_error(PaymentLogic::getError(), ['order_id' => $order['id']], PaymentLogic::getReturnCode());
        }

        if (PaymentLogic::getReturnCode() != 0) {
            $this->_success('', $result, PaymentLogic::getReturnCode());
        }

        $this->_success('', $result);
    }



    public function pcPrepay()
    {
        $post = $this->request->post();
        $order = Order::get($post['order_id']);
        $order['pay_way'] = $post['pay_way'];

        $return_msg = ['order_id' => $order['id'], 'order_amount' => $order['order_amount']];

        if (empty($order)) {
            $this->_error('订单不存在');
        }

        if ($order['order_status'] == CommonOrder::STATUS_CLOSE || $order['del'] == 1) {
            $this->_error('订单已关闭');
        }

        if ($order['pay_status'] == Pay::ISPAID) {
            $this->_success('支付成功', $return_msg, 10001);
        }

        $result = PaymentLogic::pcPay($order, $post['order_source']);

        if (false === $result) {
            $this->_error(PaymentLogic::getError(), $return_msg, PaymentLogic::getReturnCode());
        }

        if ($order['pay_way'] == Pay::BALANCE_PAY) {
            $this->_success('支付成功', $return_msg, PaymentLogic::getReturnCode());
        }

        $return_msg['data'] = $result;

        if (PaymentLogic::getReturnCode() != 0) {
            $this->_success('支付成功', $return_msg, PaymentLogic::getReturnCode());
        }

        $this->_success('支付成功', $return_msg);
    }


    public function notifyMnp()
    {
        $config = WeChatServer::getPayConfig(Client_::mnp);
        return WeChatPayServer::notify($config);
    }

    public function notifyOa()
    {
        $config = WeChatServer::getPayConfig(Client_::oa);
        return WeChatPayServer::notify($config);
    }

    public function notifyApp()
    {
        $config = WeChatServer::getPayConfig(Client_::ios);
        return WeChatPayServer::notify($config);
    }

    public function aliNotify()
    {
        $data = $this->request->post();
        $result = (new AliPayServer())->verifyNotify($data);
        if (true === $result) {
            echo 'success';
        } else {
            echo 'fail';
        }
    }


    /**
     * 易支付异步通知（GET 回调）
     */
    public function epayNotify()
    {
        $data = $this->request->get();
        $result = (new EpayServer())->verifyNotify($data);
        echo $result === true ? 'success' : 'fail';
    }


    /**
     * 易支付同步跳回
     */
    public function epayReturn()
    {
        $data = $this->request->get();
        $domain = request()->domain();
        try {
            $config = (new EpayServer())->getOptions();
            if (!EpayServer::verifySign($data, $config['key'])) {
                header('Location: ' . $domain);
                exit;
            }
        } catch (\Exception $e) {
            header('Location: ' . $domain);
            exit;
        }
        $passback = $data['param'] ?? 'order';
        if ($passback === 'recharge') {
            header('Location: ' . $domain . '/mobile/pages/user_wallet/user_wallet');
        } else {
            $order = Db::name('order')->where(['order_sn' => $data['out_trade_no']])->find();
            $orderId = $order['id'] ?? 0;
            header('Location: ' . $domain . '/mobile/pages/order_details/order_details?id=' . $orderId);
        }
        exit;
    }


    public function payway()
    {
        $params = $this->request->get();
        if(!isset($params['from']) || !isset($params['order_id'])) {
            return $this->_error('参数缺失');
        }
        if($params['from'] == 'order') {
            $order = Db::name('order')->where('id', $params['order_id'])->find();
        }else if($params['from'] == 'recharge') {
            $order = Db::name('recharge_order')->where('id', $params['order_id'])->find();
        }

        $payModel = new Pay();
        $pay = $payModel->where(['status' => 1])->order('sort')->hidden(['config'])->select()->toArray();

        foreach ($pay as $k => &$item) {
            if ($item['code'] == 'wechat') {
                $item['extra'] = '微信快捷支付';
                $item['pay_way'] = Pay::WECHAT_PAY;
            }

            if ($item['code'] == 'balance') {
                $user_money = Db::name('user')->where(['id' => $this->user_id])->value('user_money');
                $item['extra'] = '可用余额:'.$user_money;
                $item['pay_way'] = Pay::BALANCE_PAY;
            }

            if ($item['code'] == 'alipay') {
                $item['extra'] = '';
                $item['pay_way'] = Pay::ALI_PAY;
            }

            if ($item['code'] == 'epay') {
                $item['extra'] = '支持微信/支付宝/USDT等多渠道';
                $item['pay_way'] = Pay::EPAY;
            }

            if (in_array($this->client, [Client_::mnp, Client_::oa]) && $item['code'] == 'alipay') {
                unset($pay[$k]);
            }
            // 易支付在小程序/公众号内不可用（需跳出微信）
            if (in_array($this->client, [Client_::mnp, Client_::oa]) && $item['code'] == 'epay') {
                unset($pay[$k]);
            }
            if($params['from'] == 'recharge' && $item['code'] == 'balance') {
                unset($pay[$k]);
            }

        }
        $cancelTime = ConfigServer::get('trading', 'order_cancel');
        if(empty($cancelTime)) {
            $cancelTime = 0;
        }else{
            $cancelTime = $order['create_time'] + intval($cancelTime) * 60;
        }
        $data = [
            'pay' => array_values($pay),
            'order_amount' => $order['order_amount'],
            'cancel_time' => $cancelTime,
        ];
        $this->_success('', $data);
    }

}
