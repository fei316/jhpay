<?php
/**
 * Created by PhpStorm.
 * Date: 2018-12-26
 * Time: 15:06
 */

namespace Pay\Controller;
class JmalipayController extends PayController
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
        $notifyurl = $this->_site . 'Pay_Jmalipay_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Jmalipay_callbackurl.html'; //返回通知
        $parameter = array(
            'code' => 'Jmalipay', // 通道名称
            'title' => '金木支付宝扫码',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $url = "https://pay.huohuo8.com/index/pay/pay";   
        $goodsname="团购商品";
       // $istype="1";
        $return_url =  $this->_site . 'Pay_Jmalipay_callbackurl.html'; //返回通知
        $notify_url =  $this->_site . 'Pay_Jmalipay_notifyurl.html'; //异步通知
        $orderid= $return['orderid'];
        $orderuid="商品";
        //$price="100.00"; 
        $price= sprintf('%.2f', $return['amount']);
        $uid="45";
        $token="5d7684b98174c5542219f2e0bbf9b6dd";
        $key = md5($goodsname. $notify_url . $orderid . $orderuid . $price . $return_url . $token . $uid);
        $native = Array(
              "uid" => $uid,
	         "goodsname" => $goodsname,
            // "istype"=> $istype,
             "orderid"=> $orderid,
	         "orderuid"=> $orderuid,
             "price"=> $price,
             "notify_url"=> $notify_url,
             "return_url"=> $return_url,
             "key"=> $key,
);
//$rs = file_get_contents("php://input");
//$rs = json_decode($ret, true);
//file_put_contents("Jmalipayreturn.txt", date("Y-m-d H:i:s")." ".$ret."\r\n", FILE_APPEND );
echo $this->_createForm($url,$native);
return;

    }
  
 protected function _createForm($url, $native){
        $str = '<!doctype html>
                <html>
                    <head>
                        <meta charset="utf8">
                        <title>正在跳转付款页</title>
                    </head>
                    <body onLoad="document.pay.submit()">
                    <form method="post" action="' . $url . '" name="pay">';

                        foreach($native as $k => $vo){
                            $str .= '<input type="hidden" name="' . $k . '" value="' . $vo . '">';
                        }

                    $str .= '</form>
                    </body>
                </html>';
        return $str;
    }
  

    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_GET["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_GET["orderid"], 'Jmalipay', 1);
        }else{
            exit("交易失败");
        }

    }

    //异步通知
    public function notifyurl()
    {
       $log = file_get_contents('php://input');
       file_put_contents( dirname( __FILE__ ).'Jmalipay_post.txt', var_export($_POST, true), FILE_APPEND );
       $platform_trade_no = $_POST["platform_trade_no"];
       $orderid = $_POST["orderid"];
       $price = $_POST["price"];
       $realprice = $_POST["realprice"];
       $orderuid = $_POST["orderuid"];
       $key = $_POST["key"];
       $token = "5d7684b98174c5542219f2e0bbf9b6dd";
       $temps = md5($orderid . $orderuid . $platform_trade_no . $price . $realprice . $token);
       if($key== $temps)
       {$this->EditMoney($orderid, 'Jmalipay', 0);
        	exit("OK");
        }
      }

   }