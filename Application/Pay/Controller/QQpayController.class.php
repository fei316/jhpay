<?php
namespace Pay\Controller;

use Think\Log;
use Pay\Lib\QQPay\QpayMchAPI;
use Pay\Lib\QQPay\QpayMchUtil;

/**
 * QQ官方支付渠道
 * 文档地址：https://qpay.qq.com/qpaywiki.shtml
 */
class QQpayController extends PayController
{

    private $exchange = 100; //金额比例
    private $gateway = 'https://qpay.qq.com/cgi-bin/pay/qpay_unified_order.cgi';

    public function Pay($channel)
    {

        $orderid = I("request.pay_orderid", '');

        $body = I('request.pay_productname', '');

        $parameter = [
            'code'         => 'QQpay',
            'title'        => 'QQpay（QQ扫码）',
            'exchange'     => $this->exchange, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $orderid, //外部订单号
            'channel'      => $channel,
            'body'         => $body,
        ];

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);

        //入参
        $params = [];
        $params["out_trade_no"] = $return['orderid'];
        $params["mch_id"] = $return['mch_id'];
        //$params["nonce_str"] = random_str(32); //在API里有生成了
        $params["body"] = "商品购买";
        //$params["device_info"] = "WP00000001"; //optional
        $params["fee_type"] = "CNY";
        $params["notify_url"] = $return['notifyurl'];
        $params["spbill_create_ip"] = get_client_ip();
        $params["total_fee"] = $return['amount'];
        $params["trade_type"] = "NATIVE"; //Native原生支付即前文说的扫码支付

        //api调用
        $qpayApi = new QpayMchAPI($this->gateway, null, 10);
        $ret = $qpayApi->reqQpay($params, $return['signkey']);

        $arr = QpayMchUtil::xmlToArray($ret);

        if ($arr['return_code'] != 'SUCCESS') {
            Log::record('QQ扫码网关错误，错误信息：' . $arr['return_msg'] . '，完整返回：' . json_encode($arr));
            exit('QQ支付网关错误(-1)：' . $arr['return_msg']);
        }

        if ($arr['result_code'] != 'SUCCESS') {
            Log::record('QQ扫码网关错误，错误信息：' . $arr['return_msg'] . '，完整返回：' . json_encode($arr));
            exit('QQ支付网关错误(-2)：' . $arr['err_code_des']);
        }

        $return['amount'] = floatval($return['amount']) / $this->exchange; //转回以元为单位
        $this->showQRcode($arr['code_url'], $return, 'qq');
    }

    public function notifyurl()
    {
        $xml = isset($GLOBALS['HTTP_RAW_POST_DATA'])? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        if(empty($xml)){
            $xml = file_get_contents('php://input');
        }
        $arr = QpayMchUtil::xmlToArray($xml);
        if ($arr['trade_state'] != 'SUCCESS') {
            Log::record("QQ扫码异步通知错误，通知内容：\nxml: " . $xml  . "\n"
                . 'arr: ' . json_encode($arr), Log::ERR);
            exit('ERROR');
        }

        $order = M('Order')->where(['pay_orderid' => $arr['out_trade_no']])->find();
        $serverSign = $arr['sign'];
        unset($arr['sign']);
        $mySign = QpayMchUtil::getSign($arr, $order['key']);

        if ($serverSign != $mySign) {
            Log::record("QQ扫码异步通知错误，签名错误，通知内容：\narr: " . json_encode($arr)  . "\n", Log::ERR);
            exit('ERROR');
        } elseif (floatval($arr['total_fee']) != floatval($order['pay_amount'] * $this->exchange)) {
            Log::record("QQ扫码异步通知错误，金额错误，通知内容：\narr: " . json_encode($arr)  . "\n", Log::ERR);
            exit('ERROR');
        }
        $this->EditMoney($arr['out_trade_no'], '', 0);
        $retXml = QpayMchUtil::arrayToXml(['return_code' => 'SUCCESS']);
        exit($retXml);
    }

    public function callbackurl()
    {
        $orderid    = I('request.orderid', '');
        $pay_status = M("Order")->where(['pay_orderid' => $orderid])->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($orderid, '', 1);
        } else {
            exit("error");
        }
    }

}
