<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */

namespace Pay\Controller;

class JmwxsmController extends PayController
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
        $notifyurl = $this->_site . 'Pay_Jmwxsm_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Jmwxsm_callbackurl.html'; //返回通知
        $parameter = array(
            'code' => 'Jmwxsm', // 通道名称
            'title' => 'paysapi微信扫码',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $url = "https://pay.bbbapi.com/?format=json";   
        $goodsname="团购商品";
        $istype="2";
        $return_url = $callbackurl; //返回通知
        $notify_url = $notifyurl; //异步通知
        $orderid= $return['orderid'];
        $orderuid="商品";
        //$price="100.00"; 
        $price= sprintf('%.2f', $return['amount']);
        $uid= $return['mch_id'];
        $token= $return['signkey'];
        $key = md5($goodsname. $istype . $notify_url . $orderid . $orderuid . $price . $return_url . $token . $uid);
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

      
      

$rs = file_get_contents("php://input");
$rs = json_decode($ret, true);
file_put_contents("Jmwxsmreturn.txt", date("Y-m-d H:i:s")." ".$ret."\r\n", FILE_APPEND );

$rspArray = json_decode($ret, true);
$qrcode=$rspArray['data']['qrcode'];
$msg = $rspArray["msg"];
//echo $msg; 
//echo $rspArray; 
//header("Location: $qrcode");
                import("Vendor.phpqrcode.phpqrcode",'',".php");
                $urls = $qrcode;
                $QR = "Uploads/codepay/". $return['orderid'] . ".png";//已经生成的原始二维码图
                \QRcode::png($urls, $QR, "L", 20);
               $this->assign("imgurl", '/'.$QR);
               $this->assign('params',$return);
               $this->assign('orderid',$return['orderid']);
               $this->assign('money',sprintf('%.2f',$return['amount']));
               $this->display("WeiXin/weixin");

    }
  

  

    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_GET["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_GET["orderid"], 'Jmwxsm', 1);

            exit('交易成功！如未到账请联系客服');
        }else{
            exit("交易成功！");
        }

    }

    //异步通知
    public function notifyurl()
    {
       $log = file_get_contents('php://input');
       file_put_contents( dirname( __FILE__ ).'Jmwxsm_post.txt', var_export($_POST, true), FILE_APPEND );
       $paysapi_id = $_POST["paysapi_id"];
       $orderid = $_POST["orderid"];
       $price = $_POST["price"];
       $realprice = $_POST["realprice"];
       $orderuid = $_POST["orderuid"];
       $key = $_POST["key"];
        $info=M('ChannelAccount')->where(array('channel_id'=>217))->find();
	 	$channel=M('Channel')->where(array('id'=>217))->find();
        $token =$info['signkey'];
       $temps = md5($orderid . $orderuid . $paysapi_id . $price . $realprice . $token);
       if($key== $temps)
       {$this->EditMoney($orderid, 'Jmwxsm', 0);
        	exit("OK");
        }
      }

   }