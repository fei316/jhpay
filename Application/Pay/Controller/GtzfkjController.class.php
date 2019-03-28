<?php
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
namespace Pay\Controller;

use Think\Exception;

class GtzfkjController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Gtzfkj_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Gtzfkj_callbackurl.html'; //返回通知
        $bank_accno = I("request.bank_accno", '');
        $parameter = array(
            'code' => 'Gtzfkj', // 通道名称
            'title' => '国通支付快捷',
            'exchange' => 100, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $return['subject'] = $body;
        if($bank_accno) {
            $parameter = array(
                "version" => '1.0',
                "spid" =>  $return['mch_id'],
                "spbillno" => $return['orderid'],
                "userId" => $bank_accno,
                "tran_amt" => $return['amount'],
                "backUrl" => $callbackurl,
                "notifyUrl" => $notifyurl,
            );
            $parameter['sign'] = $this->createSign($return['signkey'], $parameter);
            $url = 'http://api.ayc168.cn:8089/pay/gatewaypaybykj';
            $data['req_data'] = $this->arrayToXml($parameter);
            echo createForm($url, $data);
        } else {
            $url = $this->_site . 'Pay_Gtzfkj_topay_orderid_'.$return["orderid"].'.html';
            echo '<script type="text/javascript">window.location.href="'.$url.'"</script>';
            exit;
        }
    }

    public function topay()
    {
        if(IS_POST) {
            $orderid = I("post.orderid");
            if(!$orderid) {
                $this->showmessage("参数错误");
            }
            $order = M('order')->where(array('pay_orderid'=>$orderid))->find();
            if(empty($order)) {
                $this->showmessage("订单不存在");
            }
            if($order['pay_status'] != 0) {
                $this->showmessage("订单已支付");
            }
            $card_type = I("post.card_type", 1);
            if(!$card_type) {
                $this->showmessage("银行卡类型不能为空");
            }
            $bank_accno = I("post.bank_accno");
            if(!$bank_accno) {
                $this->showmessage("银行卡号不能为空");
            }
            $notifyurl = $this->_site . 'Pay_Gtzfkj_notifyurl.html'; //异步通知
            $callbackurl = $this->_site . 'Pay_Gtzfkj_callbackurl.html'; //返回通知
            $parameter = array(
                "version" => '1.0',
                "spid" =>  $order['memberid'],
                "spbillno" => $orderid,
                "userId" => $bank_accno,
                "tran_amt" => round($order['pay_amount'],4)*100,
                "backUrl" => $callbackurl,
                "notifyUrl" => $notifyurl,
            );
            $parameter['sign'] = $this->createSign($order['key'], $parameter);
            $url = 'http://api.ayc168.cn:8089/pay/gatewaypaybykj';
            $data['req_data'] = $this->arrayToXml($parameter);
            echo createForm($url, $data);
        } else {
            $orderid = I("orderid");
            if(!$orderid) {
                $this->showmessage("参数错误");
            }
            $order = M('order')->where(array('pay_orderid'=>$orderid))->find();
            if(empty($order)) {
                $this->showmessage("订单不存在");
            }
            if($order['pay_status'] != 0) {
                $this->showmessage("订单已支付");
            }
            $rpay_url = $this->_site . 'Pay_Gtzfkj_topay.html';
            $this->assign('orderid', $orderid);
            $this->assign('rpay_url', $rpay_url);
            $this->assign('money', round($order['pay_amount'],4));
            $this->display('Gtzfkj/kj');
        }
    }

    //同步通知
    public function callbackurl()
    {
        $orderId = $_REQUEST['spbillno'];
        if(!$orderId) {
            exit;
        }
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $orderId])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($orderId, 'Gtzfkj', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {
        file_put_contents('./Data/Gtzfkj_notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $parameter = $_POST;
        if($parameter['retcode'] == '0') {
            $sign = $parameter['sign'];
            unset($parameter['sign']);
            $key = getKey($parameter['spbillno']);
            $newsign = $this->createSign($key, $parameter);
            if($sign == $newsign) {
                if($parameter['result'] == 2) {
                    $this->EditMoney($parameter['spbillno'], 'Gtzfkj', 0);
                }
                echo 'success';
            }
        } else {
            exit($parameter['retmsg']);
        }
    }

    public function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val){
            if(is_array($val)){
                $xml.="<".$key.">".arrayToXml($val)."</".$key.">";
            }else{
                $xml.="<".$key.">".$val."</".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }
}