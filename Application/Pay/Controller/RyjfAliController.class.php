<?php
/**
 * Created by PhpStorm.
 * User: mapeijian
 * Date: 2018-07-11
 * Time: 11:33
 */
namespace Pay\Controller;

use Think\Exception;

class RyjfAliController extends PayController
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
        $parameter = array(
            'code' => 'RyjfAli', // 通道名称
            'title' => '如意金服支付宝',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $return['subject'] = $body;
        $notifyurl = $this->_site . 'Pay_RyjfAli_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_RyjfAli_callbackurl_orderid_'.$return['orderid'].'.html'; //返回通知
        $parameter = array(
            "merchant_id" => $return['mch_id'],
            "order_id" =>  $return['orderid'],
            "amount" => $return['amount'],
            "pay_method" => 5,
            "return_url" => $callbackurl,
            "notify_url" => $notifyurl,
        );
        $parameter['sign'] = md5('merchant_id=' . $parameter['merchant_id'] . '&order_id=' . $parameter['order_id'] . '&amount=' . $parameter['amount'] . '&sign=' . $return['signkey']);
        $url = 'http://166ep.com/pay';
        $str = '<!doctype html>
            <html>
                <head>
                    <meta charset="utf8">
                    <title>正在跳转付款页</title>
                </head>
                <body onLoad="document.pay.submit()">
                <form method="get" action="' . $url . '" name="pay">';

        foreach ($parameter as $k => $vo) {
            $str .= '<input type="hidden" name="' . $k . '" value="' . $vo . '">';
        }

        $str .= '</form>
                <body>
            </html>';
        echo $str;
    }

    //同步通知
    public function callbackurl()
    {
        $orderId = $_REQUEST['orderid'];
        if(!$orderId) {
            exit;
        }
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $orderId])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($orderId, 'RyjfAli', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {
        file_put_contents('./Data/RyjfAli_notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $json_data = json_decode(file_get_contents("php://input"), true);
        if(!$json_data['order_id']) exit('fail');
        $order = M('order')->where(['pay_orderid' => $json_data['order_id']])->find();
        if(empty($order)) {
            exit('fail');
        }
        if($order['pay_amount'] != $json_data['amount']) {
            exit('money error');
        }
        //接受返回数据验证开始
        //md5验证
        $merchant_id = $order['memberid']; //如意金服分配的商户号
        $compKey = $order['key']; //如意金服分配的密钥

        $sign = md5('merchant_id=' . $merchant_id . '&order_id=' . $json_data['order_id'] . '&amount=' . $json_data['amount'] . '&sign=' . $compKey);

        if($sign == $json_data['sign']){
            // 验签成功
            //改变订单状态，及其他业务修改
            $this->EditMoney($json_data['order_id'], 'RyjfAli', 0);
            echo "success";
            //接收通知后必须输出”success“代表接收成功。
        } else {
            exit('check sign fail');
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