<?php

/**
 * @author zhangjianwei
 * @date   2018-06-01
 */

namespace Pay\Controller;

use Think\Log;


/**
 * 立刻支付渠道 （上游也是自己的系统）
 * 官网地址：http://www.hulizf521.com/
 * @package Pay\Controller
 */
class LIKEZHIFUController extends PayController
{

    private $exchange = 1;
    private $gateway  = 'https://api.likerpay.com/Pay_Index.html';

    public function Pay($channel)
    {
        $exchange = $this->exchange;
        $return = $this->getParameter('立刻支付', $channel, LIKEZHIFUController::class, $exchange);

        $native = [
            "pay_memberid"    => $return["mch_id"], // 商户ID
            "pay_orderid"     => $return["orderid"], // 订单号
            "pay_amount"      => $return["amount"], // 交易金额
            "pay_applydate"   => date("YmdHis"), // 交易时间
            "pay_bankcode"    => I('request.pay_bankcode'), // 银行编码
            "pay_notifyurl"   => $return['notifyurl'],
            "pay_callbackurl" => $return['callbackurl'],
        ];

        $md5key = $return['signkey'];

        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
        $native["pay_md5sign"] = $sign;

        //$native['pay_attach'] = "1234|456";
        //$native['pay_productname'] ='VIP基础服务';

        $this->setHtml($this->gateway, $native);
    }

    //异步通知地址
    public function notifyurl()
    {
        $returnArray = [
            "memberid"       => $_REQUEST["memberid"], // 商户ID
            "orderid"        => $_REQUEST["orderid"], // 订单号
            "amount"         => $_REQUEST["amount"], // 交易金额
            "datetime"       => $_REQUEST["datetime"], // 交易时间
            "transaction_id" => $_REQUEST["transaction_id"], // 支付流水号
            "returncode"     => $_REQUEST["returncode"],
        ];
        $order_info = M('Order')->where(['pay_orderid' => $returnArray['orderid']])->find();
        $md5key = $order_info['key'];  //商户秘钥
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
        if ($sign !== $_REQUEST["sign"]) {
            echo "签名校验错误";
            Log::record("立刻支付异步通知：签名校验错误:\n" . json_encode($returnArray), Log::ERR);
            return false;
        }

        if ($_REQUEST["returncode"] == "00") {
            //修改订单信息
            $this->EditMoney($returnArray['orderid'], '', 0);
            Log::record("立刻支付异步通知：" . "交易成功！订单号：" . $returnArray["orderid"], Log::INFO);
            exit("ok");
        } else {
            Log::record("立刻支付异步通知：" . "交易失败！订单号：" . $returnArray["orderid"] . "，参数：". json_encode($returnArray), Log::ERR);
        }

    }

    //同步回调地址
    public function callbackurl()
    {
        $returnArray = [
            "memberid"       => $_REQUEST["memberid"], // 商户ID
            "orderid"        => $_REQUEST["orderid"], // 订单号
            "amount"         => $_REQUEST["amount"], // 交易金额
            "datetime"       => $_REQUEST["datetime"], // 交易时间
            "transaction_id" => $_REQUEST["transaction_id"], // 支付流水号
            "returncode"     => $_REQUEST["returncode"],
        ];
        $order_info = M('Order')->where(['pay_orderid' => $returnArray['orderid']])->find();
        if (!$order_info) {
            echo "订单不存在";
            return false;
        }
        $userid = intval($order_info["pay_memberid"] - 10000); // 商户ID
        $member_info = M('Member')->where(['id' => $userid])->find();
        if (!$member_info) {
            echo "商户不存在";
            return false;
        }

        $md5key = $order_info['key'];  //商户秘钥
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
        if ($sign !== $_REQUEST["sign"]) {
            echo "签名校验错误";
            Log::record("立刻支付同步通知：签名校验错误:\n" . json_encode($returnArray), Log::ERR);
            return false;
        }

        if ($_REQUEST["returncode"] == "00") {
            Log::record("立刻支付同步通知：" . "交易成功！订单号：" . $returnArray["orderid"], Log::INFO);
        } else {
            Log::record("立刻支付同步通知：" . "交易失败！订单号：" . $returnArray["orderid"] . "，参数：". json_encode($returnArray), Log::ERR);
        }


        $return_array = [ // 返回字段
                          "memberid"       => $order_info["pay_memberid"], // 商户ID
                          "orderid"        => $order_info['out_trade_id'], // 订单号
                          'transaction_id' => $order_info["pay_orderid"], //支付流水号
                          "amount"         => $returnArray["amount"], // 交易金额
                          "datetime"       => date("YmdHis"), // 交易时间
                          "returncode"     => $returnArray['returncode'], // 交易状态
        ];
        $sign = $this->createSign($member_info['apikey'], $return_array);
        $return_array["sign"] = $sign;
        $return_array["attach"] = $order_info["attach"];

        $this->setHtml($order_info["pay_callbackurl"], $return_array);
    }

}