<?php
/**
免签API对接DEMO
 */

namespace Pay\Controller;
class WjaliwapController extends PayController
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
        $notifyurl = $this->_site . 'Pay_Wjaliwap_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Wjaliwap_callbackurl.html'; //返回通知
        $parameter = array(
            'code' => 'Wjaliwap', // 通道名称
            'title' => '万家支付宝H5',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $url = $return['gateway']; 
        $goodsname="团购商品";
        $istype="1";
        $return_url =  $this->_site . 'Pay_Wjaliwap_callbackurl.html'; //返回通知
        $notify_url =  $this->_site . 'Pay_Wjaliwap_notifyurl.html'; //异步通知
        $orderid= $return['orderid'];
        $orderuid="商品";
        //$price="100.00"; 
        $price= sprintf('%.2f', $return['amount']);
        $uid= $return['mch_id'];
        $token= $return['signkey'];
        $key = md5($goodsname. '+' .$istype . '+' .$notify_url . '+' .$orderid . '+' .$orderuid . '+' .$price . '+' .$return_url . '+' .$token . '+' .$uid);
        $data = Array(
              "uid" => $uid,
	         "goodsname" => $goodsname,
             "istype"=> $istype,
             "orderid"=> $orderid,
	         "orderuid"=> $orderuid,
             "price"=> $price,
             "notify_url"=> $notify_url,
             "return_url"=> $return_url,
             "key"=> $key,
);
//$sendJson = json_encode($data);

$curl = curl_init(); // 启动一个CURL会话
curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在		
curl_setopt($curl, CURLOPT_POST, true); // 发送一个常规的Post请求
curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
curl_setopt($curl, CURLOPT_TIMEOUT, 60); // 设置超时限制防止死循环返回
curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
$ret = curl_exec($curl);	
var_dump($ret);
      
      
      

$rs = file_get_contents("php://input");
$rs = json_decode($ret, true);
file_put_contents("Wjaliwapreturn.txt", date("Y-m-d H:i:s")." ".$ret."\r\n", FILE_APPEND );

$rspArray = json_decode($ret, true);
$qrcode=$rspArray['data']['qrcode'];
$msg = $rspArray["msg"];
header("Location: $qrcode");
    }

    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'Wjaliwap', 1);
        }else{
            exit("交易失败！");
        }

    }

    //异步通知
    public function notifyurl()
    {
       $log = file_get_contents('php://input');
       file_put_contents( dirname( __FILE__ ).'Wjaliwap_post.txt', var_export($_POST, true), FILE_APPEND );
       $platform_trade_no = $_REQUEST["platform_trade_no"];
       $orderid = $_REQUEST["orderid"];
       $price = $_REQUEST["price"];
       $realprice = $_REQUEST["realprice"];
       $orderuid = $_REQUEST["orderuid"];
       $key = $_REQUEST["key"];
        $info=M('ChannelAccount')->where(array('channel_id'=>216))->find();
	 	$channel=M('Channel')->where(array('id'=>216))->find();
        $token =$info['signkey'];
       $temps = md5($orderid . '+' .$orderuid . '+' .$platform_trade_no . '+' .$price . '+' .$realprice . '+' .$token);
       if($key== $temps)
       {$this->EditMoney($orderid, 'Wjaliwap', 0);
        	exit("OK");
        }
      }

   }