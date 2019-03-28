<?php

/**
 * @author zhangjianwei
 * @date   2018-05-08
 */

namespace Pay\Controller;

use Think\Log;


/**
 * 领胜微信服务
 * @package Pay\Controller
 */
class LingShengController extends PayController
{

    private $gateway = 'http://www.lingshengnet.com/PayView/Index/native_final.html';

    public function Pay($channel)
    {
        $return = $this->getParameter('领胜', $channel, LingShengController::class, 1);

        //签名实例（测试数据）
        $req = [
            "uniqueId"    => $return['mch_id'],  //领胜商户唯一id（本次商户id为测试）
            "price"       => $return['amount'],  //提交金额(单位：元)
            "body"        => "商品",  //商品详情
            "orderNumber" => $return['orderid'],  //商户订单号
        ];
        $sign = "";

        //排序
        ksort($req);

        foreach ($req as $k => $v) {
            $sign .= $k . "=" . $v . "&";
        }
        $key = $return['signkey']; //商户密钥
        $sign .= "key=$key";  //商户秘钥
        $signature = md5($sign);  //MD5加密
        $sign .= "&" . "signature=$signature";  //拼接字符串
        //$sign .= "&" . "openid=SUCCESS";  //公众号支付必传

        $data = $sign;  //请求参数

        $url = $this->gateway . '?json=' . base64_encode(json_encode($data));

        //微信公众号支付发送GET请求 （支持GET或者POST请求,本次以GET请求作为参照）
        //header("Location: ".$url);
        //exit();//必要

        //微信扫码发送GET请求（支持GET或者POST请求,本次以GET请求作为参照）
        $body = $this->request($url);
        //dump($body); //string(342) "{"return_code":"SUCCESS","return_msg":"OK","appid":"wx79ca268a7d9fbd23","mch_id":"1501765011","sub_mch_id":"1503123561","nonce_str":"RmXjWNdFa2eEU1Fn","sign":"BABFE96854272AD9625B6E4154DC2834","result_code":"SUCCESS","prepay_id":"wx08121700633410b388ca71e61410098992","trade_type":"NATIVE","code_url":"weixin:\/\/wxpay\/bizpayurl?pr=8ueoMbF"}"
        $body_arr = json_decode($body, true);

        //验证签名
        $signSrc = "";
        //排序
        ksort($body_arr);
        foreach ($body_arr as $k => $v) {
            if ($k != 'sign') {
                $signSrc .= $k . "=" . $v . "&";
            }
        }
        $signSrc .= "key=$key";  //商户秘钥
        $verifySign = md5($signSrc);  //MD5加密
        if ($verifySign !== strtolower($body_arr['sign'])) { //服务端返回的是大写的签名，这里要转一下
            Log::record("领胜支付Pay：签名校验错误:\n" . var_export($body_arr, true), Log::INFO);
            exit("签名校验错误");
        }

        if ($body_arr['return_code'] == 'SUCCESS') {
            $this->showQRcode($body_arr['code_url'], $return);
        } else {
            echo $body_arr['return_code'] . ':' . $body_arr['return_msg'];
        }
        die;  //返回json数组
    }

    //异步通知地址
    public function notifyurl()
    {
        Log::record('领胜支付异步通知：开始：' . PHP_EOL . var_export(func_get_args(), true), Log::INFO);
        $xml = file_get_contents('php://input');
        Log::record('领胜支付异步通知：参数xml：' . $xml, Log::INFO);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if (empty($data['orderNumber']) || empty($data['mch_id']) || $data['price'] <= 0) {
            return false;
        }
        if ($data['return_code'] != 'SUCCESS') {
            Log::record("领胜支付异步通知：结果失败:\n" . var_export($data, true), Log::INFO);
            return false;
        }

        //验证签名
        $signSrc = "";
        //排序
        ksort($data);
        foreach ($data as $k => $v) {
            if ($k != 'sign') {
                $signSrc .= $k . "=" . $v . "&";
            }
        }
        $order_info = M('Order')->where(['pay_orderid' => $data['orderNumber']])->find();
        if (!$order_info) {
            Log::record("领胜支付异步通知：订单不存在：" . $data['orderNumber'], Log::INFO);
            exit("订单不存在");
        }
        $key = $order_info['key']; //商户密钥
        $signSrc .= "key=$key";  //商户秘钥
        $verifySign = md5($signSrc);  //MD5加密
        if ($verifySign !== $data['sign']) {
            Log::record("领胜支付异步通知：签名校验错误:\n" . var_export($data, true), Log::INFO);
            exit("签名校验错误");
        }

        //修改订单信息
        $this->EditMoney($data['orderNumber'], '', 0);
        Log::record('领胜支付异步通知：完成：订单号：' . $data['orderNumber'], Log::INFO);
        //返回上游
        echo $xml;
    }

    //同步回调地址
    public function callbackurl()
    {
        //orderid=20180508132407554910&pay_memberid=10007&bankcode=902
        $orderNumber = I('request.orderid');
        if (empty($orderNumber)) {
            echo '参数错误';
            Log::record("领胜支付同步跳转：参数错误：{$orderNumber}", Log::INFO);
            return false;
        }

        $order_info = M('Order')->where(['pay_orderid' => $orderNumber])->find();
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

        $return_array = [ // 返回字段
                          "memberid"       => $order_info["pay_memberid"], // 商户ID
                          "orderid"        => $order_info['out_trade_id'], // 订单号
                          'transaction_id' => $order_info["pay_orderid"], //支付流水号
                          "amount"         => $order_info["pay_amount"], // 交易金额
                          "datetime"       => date("YmdHis"), // 交易时间
                          "returncode"     => $order_info["pay_status"] == 2 ? "00" : "", // 交易状态
        ];
        $sign = $this->createSign($member_info['apikey'], $return_array);
        $return_array["sign"] = $sign;
        $return_array["attach"] = $order_info["attach"];

        $this->setHtml($order_info["pay_callbackurl"], $return_array);
    }

    // HTTP请求（支持HTTP/HTTPS，支持GET/POST）
    public function request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}