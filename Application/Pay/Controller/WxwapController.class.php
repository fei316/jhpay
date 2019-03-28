<?php
namespace Pay\Controller;

use Org\Util\WxH5Pay;

class WxwapController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function Pay($array)
    {
		$orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Aliscan_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Aliscan_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'Aliscan', // 通道名称
            'title' => '支付宝官方扫码',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

        $data = array(
            'pid' => '858580',
            'type' => 'wxpay',
            'out_trade_no' => $return['orderid'],
            'notify_url' => $notifyurl,
            'return_url' => $callbackurl,
            'name' => 'pay',
            'money' => $return['amount'],
            'sitename' => '',
            'sign' => '',
            'sign_type' => 'MD5'
        );

        ksort($data);

        $signText = '';
        foreach($data as $k => $v){
            if($k == "sign" || $k == "sign_type" || $v == "") continue;

            $signText .= $k.'='.$v.'&';
        }

        $signText = rtrim($signText, '&');
        $signText .= 'UzBGGGyyGogGAGakMAxXADYyuMcAg7J7';

        $data['sign'] = md5($signText);

        $url = 'https://pay.pinyewang.com/submit.php';
        $parameters = '';
        foreach($data as $k => $v){
            $parameters .= $k.'='.$v.'&';
        }

        $parameters = rtrim($parameters, '&');

        header('Location: '.$url.'?'.$parameters);
    }

	 public function topay() {
        $orderid = $_REQUEST["orderid"];
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
        $notifyurl = $this->_site . 'Pay_Wxwap_notifyurl.html'; //异步通知
        $redirect_uri = $this->_site . 'Pay_Wxwap_callbackurl.html?orderid='.$orderid;
        $wxwapPay = new WxH5Pay($order["account"], $order["memberid"], $notifyurl, $order["key"]);
        $params['body'] = "商城订单";                    //商品描述
        $params['out_trade_no'] = $orderid;    //自定义的订单号
        $params['total_fee'] = $order['pay_amount']*100;        //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'MWEB';                  //交易类型 JSAPI | NATIVE | APP | WAP
        $params['scene_info'] = '{"h5_info": {"type":"Wap","wap_url": "h'.$this->site.'","wap_name": "'."商城订单".'"}}';
        $result = $wxwapPay->unifiedOrder( $params );
        $url = $result['mweb_url'].'&redirect_url='.$redirect_uri;
        redirect($url);
        exit();
    }
	
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["out_trade_no"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($data['out_trade_no'], 'Wxwap', 1);

            exit('交易成功！');
        }else{
            exit("error");
        }
    }

    // 服务器点对点返回
    public function notifyurl()
    {
        $data = array(
            'pid' => !isset($_REQUEST['pid']) ? '' : trim($_REQUEST['pid']),
            'trade_no' => !isset($_REQUEST['trade_no']) ? '' : trim($_REQUEST['trade_no']),
            'out_trade_no' => !isset($_REQUEST['out_trade_no']) ? '' : trim($_REQUEST['out_trade_no']),
            'type' => !isset($_REQUEST['type']) ? '' : trim($_REQUEST['type']),
            'name' => !isset($_REQUEST['name']) ? '' : trim($_REQUEST['name']),
            'money' => !isset($_REQUEST['money']) ? '' : trim($_REQUEST['money']),
            'trade_status' => !isset($_REQUEST['trade_status']) ? '' : trim($_REQUEST['trade_status']),
            'sign' => !isset($_REQUEST['sign']) ? '' : trim($_REQUEST['sign']),
            'sign_type' => !isset($_REQUEST['sign_type']) ? '' : trim($_REQUEST['sign_type']),
        );

        if($data['trade_status'] != 'TRADE_SUCCESS') exit('trade fail');

        ksort($data);

        $signText = '';
        foreach($data as $k => $v){
            if($k == "sign" || $k == "sign_type" || $v == "") continue;

            $signText .= $k.'='.$v.'&';
        }

        $signText = rtrim($signText, '&');
        $signText .= 'UzBGGGyyGogGAGakMAxXADYyuMcAg7J7';
        $sign = md5($signText);

        if($sign != strtolower($data['sign'])) exit('sign err');

        $this->EditMoney($data['out_trade_no'], 'Wxwap', 0);

        exit("success");
    }
}

?>
