<?php
// +----------------------------------------------------------------------
// | likeshop开源商城系统
// +----------------------------------------------------------------------
// | author: likeshop.cn.team
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\logic\PayConfigLogic;
use app\common\server\ConfigServer;
use think\db;

class PayConfig extends AdminBase
{

    public function lists()
    {
        if ($this->request->isAjax()) {
            $this->_success('', PayConfigLogic::lists());
        }
        return $this->fetch();
    }


    public function editBalance()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            if (empty($post['icon']) && $post['status'] == 1) {
                $this->_error('请选择支付图标');
            }
            PayConfigLogic::editBalance($post);
            $this->_success('修改成功');
        }
        $this->assign('info', PayConfigLogic::info('balance'));
        return $this->fetch();
    }


    public function editWechat()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            if ($post['status'] == 1) {
                if (empty($post['icon'])) {
                    $this->_error('请选择支付图标');
                }
                if ($post['apiclient_cert'] == '' || $post['apiclient_key'] == '') {
                    $this->_error('apiclient_cert或apiclient_key不能为空');
                }
            }
            PayConfigLogic::editWechat($post);
            $this->_success('修改成功');
        }
        $domain_name = ConfigServer::get('website', 'domain_name', '');
        $domain_name = $domain_name ? $domain_name : request()->domain();
        $this->assign('domain', $domain_name);
        $this->assign('info', PayConfigLogic::info('wechat'));
        return $this->fetch();
    }


    public function editAlipay()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            if (empty($post['icon']) && $post['status'] == 1) {
                $this->_error('请选择支付图标');
            }
            PayConfigLogic::editAlipay($post);
            $this->_success('修改成功');
        }
        $this->assign('info', PayConfigLogic::info('alipay'));
        return $this->fetch();
    }


    /**
     * 编辑易支付（彩虹易支付 / 自建免签支付）
     */
    public function editEpay()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            if ($post['status'] == 1) {
                if (empty($post['icon'])) {
                    $this->_error('请选择支付图标');
                }
                if (empty($post['gateway']) || empty($post['pid']) || empty($post['key'])) {
                    $this->_error('网关地址 / 商户ID / 商户密钥不能为空');
                }
            }
            PayConfigLogic::editEpay($post);
            $this->_success('修改成功');
        }
        $domain_name = ConfigServer::get('website', 'domain_name', '');
        $domain_name = $domain_name ? $domain_name : request()->domain();
        $this->assign('domain', $domain_name);
        $this->assign('info', PayConfigLogic::info('epay'));
        return $this->fetch();
    }

}
