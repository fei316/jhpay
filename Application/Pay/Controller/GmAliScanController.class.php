<?php
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
namespace Pay\Controller;

class GmAliScanController extends PayController
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
            'code' => 'GmAliScan', // 通道名称
            'title' => '固码支付宝扫码',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $this->showQRcode($return['signkey'], $return, 'alipay');
      
    }


    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'GmAliScan', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {

    }

}