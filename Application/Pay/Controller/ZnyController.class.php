<?php

namespace Pay\Controller;

/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

class ZnyController extends PayController
{
    public function __construct()
    {
		
		
        parent::__construct();
    }
	//测试
	public function  test33(){
		
		$param = array(
		'amount'=>'0.01',
		'mchid'=>'180772223',
		'bankcode'=>'913',
		
		);

		$html = "<form action='http://pays.weixiangyun.cn/pay_charges_checkout' method='post' id='fm'>";

		foreach($param as $k => $v){
			$html .= "<input type='hidden' name='$k' value='$v'/>";
		}

		$html .= "</form><script>document.getElementById('fm').submit();</script>";
		echo $html;
	}
   
    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
		$amount=I("request.pay_amount");
		$bankcode=I("request.pay_bankcode");
		
        $notifyurl = $this->_site . 'Pay_Zny_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Zny_callbackurl.html'; //跳转通知
        $parameter = array(
            'code' => 'Zny',       // 通道代码
            'title' => '智能云',      //通道名称
            'exchange' => 100,          // 金额比例
            'gateway' => '',            //网关地址
            'orderid' => '',            //平台订单号（有特殊需求的订单号接口使用）
            'out_trade_id'=>$orderid,   //外部商家订单号
            'body'=>$body,              //商品名称
            'channel'=>$array,          //通道信息
        );
        //生成系统订单，并返回三方请求所需要参数
        $return = $this->orderadd($parameter);
		
		//return 1;
        //var_dump($return);
        /**
         *  return 参数说名：
         *  memberid 商户编号 平台分配
         *  mch_id   商户号（三方分配）
         *  signkey  签文密钥或证书
         *  appid    微信APPID 或者 商家账号
         *  appsecret 微信密钥 或者 商家密钥
         *  gateway   三方网关
         *  amount   订单金额
         *  orderid  系统订单号
         *  subject  商品标题
         *  datetime 订单创建时间
         *  notifyurl 三方异步通知平台地址
         *  callbackyurl 三方跳转通知平台地址
         *  out_trade_id 外部订单号（商家）
         *  bankcode 支付产品ID
         *  code     支付产品英文代码
         *  status    success 订单创建成功
         */
        //组装请求参数、并发起请求
        
	if($bankcode=='913'){
		$istype='10001';
	}elseif($bankcode=='914'){
			$istype='20001';
	}
   
   
     $price = $amount;
     $orderuid = I("request.memberid");
     $goodsname = "cz";
     $orderid = $orderid;
	 $info=M('ChannelAccount')->where(array('channel_id'=>207))->find();
	 	$channel=M('Channel')->where(array('id'=>207))->find();
     $uid =$info['mch_id'];
     $token = $info['signkey'];
    
    $return_url =  $callbackurl;
    $notify_url =  $notifyurl;

    $key = md5($goodsname . $istype . $notify_url . $orderid . $orderuid . $price . $return_url . $token . $uid);
    $aa = 'web';
    $url = $channel['gateway'];

    echo "<form style='display:none;'  id='form1' name='form1' method='post' action=" . $url . ">
              <input name='goodsname' type='text' value=" . $goodsname . " >
              <input name='istype' type='text' value=" . $istype . ">
              <input name='format' type='text' value=" . $aa . " >
              <input name='notify_url' type='text' value=" . $notify_url . " >
              <input name='orderid' type='text' value=" . $orderid . ">
              <input name='orderuid' type='text' value=" . $orderuid . ">
              <input name='return_url' type='text' value=" . $return_url . ">
              <input name='price' type='text' value=" . $price . ">
              
              <input name='key' type='text' value=" . $key . ">
              <input name='uid' type='text' value=" . $uid . ">
              
            </form> <script type='text/javascript'>function load_submit(){document.form1.submit()}load_submit();</script>";
      // 
      //display:none;
  

    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $Order = M("Order");
	    $order=$Order->where(['out_trade_id' => $_REQUEST["orderid"]])->find();
        $pay_status = $Order->where(['out_trade_id' => $_REQUEST["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($order['pay_orderid'], 'Zny', 1);
        }else{
            exit("error");
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {
	//file_put_contents('pay2.txt',$orderid.'--'.$price.date('Y-m-d H:i:s'),FILE_APPEND);	
	$ordno = $_REQUEST["ordno"];
	$orderid = $_REQUEST["orderid"];
	$price = $_REQUEST["price"];
	$realprice = $_REQUEST["realprice"];
	$orderuid = $_REQUEST["orderuid"];
	$key = $_REQUEST["key"];

	 $info=M('ChannelAccount')->where(array('channel_id'=>207))->find();
     $uid =$info['mch_id'];
     $token = $info['signkey'];
	$check = md5($orderid . $orderuid . $ordno . $price . $realprice . $token);
	$map['out_trade_id']=$orderid;
    $order = M('order')->where($map)->find();

	//file_put_contents('pay.txt',$orderid.'--'.$price.date('Y-m-d H:i:s'),FILE_APPEND);
	if ($order['pay_status'] == 2) {
	 echo 'success';exit;
	}
	 if ($key == $check && $order  && $order['pay_status'] != 2) {
		
		
            $this->EditMoney($order['pay_orderid'], 'Zny', 0);
            //回写消息
			//file_put_contents('pay3.txt',$orderid.'--'.$price.date('Y-m-d H:i:s'),FILE_APPEND);
           echo 'success'; exit;
    }else{
		echo  'error';exit;
	}
}
}