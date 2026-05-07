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


namespace app\common\server;


use app\common\logic\PayNotifyLogic;
use app\common\model\Client_;
use app\common\model\Pay;
use think\Db;
use think\facade\Log;

/**
 * 易支付（彩虹易支付 / 自建免签支付）对接
 * 接入文档：商户网关地址 + pid + key 即可，签名 MD5(ksort(params) . key)
 */
class EpayServer
{
    protected $error = '未知错误';

    public function getError()
    {
        return $this->error;
    }

    /**
     * 读取易支付商户配置
     */
    public function getOptions()
    {
        $row = (new Pay())->where(['code' => 'epay'])->find();
        if (empty($row) || empty($row['config'])) {
            throw new \Exception('请先在后台配置易支付参数');
        }
        $config = $row['config'];
        if (empty($config['gateway']) || empty($config['pid']) || empty($config['key'])) {
            throw new \Exception('易支付网关地址 / 商户ID / 商户密钥不能为空');
        }
        $config['gateway'] = rtrim($config['gateway'], '/');
        return $config;
    }

    /**
     * 拼接待签名串：ksort 后 k=v&k=v，跳过 sign / sign_type / 空值
     */
    private static function getSignContent(array $data)
    {
        ksort($data);
        $parts = [];
        foreach ($data as $k => $v) {
            if (is_array($v) || $k === 'sign' || $k === 'sign_type') continue;
            if ($v === '' || $v === null) continue;
            $parts[] = $k . '=' . $v;
        }
        return implode('&', $parts);
    }

    /**
     * 生成 MD5 签名
     */
    public static function makeSign(array $data, $key)
    {
        return md5(self::getSignContent($data) . $key);
    }

    /**
     * 校验回调签名
     */
    public static function verifySign(array $data, $key)
    {
        if (empty($data['sign'])) return false;
        return hash_equals($data['sign'], self::makeSign($data, $key));
    }

    /**
     * 发起支付：返回一段自动提交到易支付网关的 HTML 表单
     * 与 AliPay h5/page 的返回格式一致，前端可直接 innerHTML 渲染并自动跳转
     */
    public function pay($from, $order, $order_source)
    {
        try {
            // 小程序 / 公众号场景下不允许（易支付需要跳出微信外部浏览器）
            if (in_array($order_source, [Client_::mnp, Client_::oa])) {
                throw new \Exception('当前场景不支持易支付，请选择其他支付方式');
            }

            $config = $this->getOptions();

            $params = [
                'pid'          => (string)$config['pid'],
                'type'         => $this->resolveType($config, $order_source),
                'out_trade_no' => $order['order_sn'],
                'notify_url'   => url('payment/epayNotify', '', '', true),
                'return_url'   => $this->buildReturnUrl($from, $order, $order_source),
                'name'         => '订单' . $order['order_sn'],
                'money'        => sprintf('%.2f', $order['order_amount']),
                'param'        => $from, // 透传：order / recharge
            ];
            if (!empty($config['site_name'])) {
                $params['sitename'] = $config['site_name'];
            }
            // 去除空值
            $params = array_filter($params, function ($v) {
                return $v !== '' && $v !== null;
            });

            $params['sign']      = self::makeSign($params, $config['key']);
            $params['sign_type'] = 'MD5';

            return $this->buildAutoSubmitForm($config['gateway'] . '/submit.php', $params);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 根据客户端选择支付通道；为空则跳到易支付收银台让用户选
     */
    private function resolveType(array $config, $order_source)
    {
        $default = $config['default_type'] ?? '';
        if ($default !== '' && $default !== 'cashier') {
            return $default;
        }
        return ''; // 空 type → 易支付 cashier.php 让用户挑
    }

    /**
     * 构造同步跳回地址（仅 H5 / PC 有意义；APP 走 webview 也能正常返回）
     */
    private function buildReturnUrl($from, $order, $order_source)
    {
        $domain = request()->domain();
        if ($from === 'recharge') {
            return $domain . '/mobile/pages/user_wallet/user_wallet';
        }
        // 默认回到订单详情
        return $domain . '/mobile/pages/order_details/order_details?id=' . $order['id'];
    }

    /**
     * 把签名好的参数包成自动提交表单返回给前端
     */
    private function buildAutoSubmitForm($action, array $params)
    {
        $inputs = '';
        foreach ($params as $k => $v) {
            $inputs .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES) . '" value="' . htmlspecialchars($v, ENT_QUOTES) . '" />';
        }
        return '<form id="likeshop_epay_form" action="' . htmlspecialchars($action, ENT_QUOTES)
            . '" method="post" accept-charset="utf-8">' . $inputs . '</form>'
            . '<script type="text/javascript">document.forms["likeshop_epay_form"].submit();</script>';
    }

    /**
     * 异步通知验签 + 触发业务回调
     * 易支付 notify_url 为 GET，不再回传整单详情，按 out_trade_no 查询本地订单
     */
    public function verifyNotify(array $data)
    {
        try {
            $config = $this->getOptions();

            if (!self::verifySign($data, $config['key'])) {
                throw new \Exception('易支付异步通知签名错误');
            }
            if (!isset($data['trade_status']) || $data['trade_status'] !== 'TRADE_SUCCESS') {
                return true; // 非成功状态，直接 ack
            }

            $extra = ['transaction_id' => $data['trade_no'] ?? ''];
            $passback = $data['param'] ?? 'order';

            switch ($passback) {
                case 'order':
                    $order = Db::name('order')->where(['order_sn' => $data['out_trade_no']])->find();
                    if (!$order || $order['pay_status'] >= Pay::ISPAID) {
                        return true;
                    }
                    PayNotifyLogic::handle('order', $data['out_trade_no'], $extra);
                    break;

                case 'recharge':
                    $order = Db::name('recharge_order')->where(['order_sn' => $data['out_trade_no']])->find();
                    if (!$order || $order['pay_status'] >= Pay::ISPAID) {
                        return true;
                    }
                    PayNotifyLogic::handle('recharge', $data['out_trade_no'], $extra);
                    break;
            }
            return true;
        } catch (\Exception $e) {
            $record = [
                __CLASS__, __FUNCTION__, $e->getFile(), $e->getLine(), $e->getMessage()
            ];
            Log::record(implode('-', $record));
            return false;
        }
    }
}
