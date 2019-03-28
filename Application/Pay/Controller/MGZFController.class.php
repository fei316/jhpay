<?php

/**
 * @author zhangjianwei
 * @date   2018-05-08
 */

namespace Pay\Controller;

use Think\Log;


/**
 * 蘑菇支付渠道
 * @package Pay\Controller
 */
class MGZFController extends PayController
{

    private $exchange = 100;
    private $gateway = 'http://www.rjxyq.cn/payapi/index.php';

    public function Pay($channel)
    {
        $exchange = $this->exchange;
        $return = $this->getParameter('蘑菇支付', $channel, MGZFController::class, $exchange);

        $payBankcode = I('request.pay_bankcode', '908');
        if (!in_array($payBankcode, ['908', '910'])) {
            exit('支付方式错误：' . $payBankcode);
        }
        $payWay = ($payBankcode == '908' ? 'qq_qr' : 'jd_qr');

        //签名实例
        $req = [
            "merchant_no"  => $return['mch_id'],
            "method"       => 'pay',
            "out_trade_no" => $return['orderid'],  //商户订单号
            'timestamp'    => (int)(microtime(true) * 1000),
            "amount"       => $return['amount'],  //提交金额(单位：分)
            "body"         => "商品",  //商品详情
            'notify_url'   => $return['notifyurl'],
            'way'          => $payWay,
        ];

        ksort($req);
        $req["sign"] = "";
        foreach($req as $k => $v) { //官方提供的加密demo，sign字段因为是空值，在这里面会被过滤掉
            if(!empty($v)) {
                if(!empty($req["sign"])) {
                    $req["sign"] .= "&";
                }
                $req["sign"] .= $k . "=" . $v;
            }
        }
        $key = $return['signkey']; //商户密钥
        Log::record("蘑菇支付Pay：signSource：" . $req["sign"] . $key, Log::INFO);
        $req["sign"] = md5($req["sign"] . $key);
        Log::record("蘑菇支付Pay：req：\n" . var_export($req, true), Log::INFO);

        $body = $this->request($this->gateway, $req);
        $body_arr = json_decode($body, true);

        if ($body_arr['code'] != '0000') {
            Log::record("蘑菇支付Pay：上游返回错误：\n" . var_export($body_arr, true), Log::INFO);
            exit('[错误-1] ' . $body_arr['msg']);
        }

        //验证签名
        $signSrc = "";
        //排序
        ksort($body_arr);
        foreach ($body_arr as $k => $v) {
            if ($k != 'sign' && !empty($v)) { //过滤掉签名字段和空值
                $signSrc .= $k . "=" . $v . "&";
            }
        }
        $signSrc = rtrim($signSrc, '&');
        $order_info = M('Order')->where(['pay_orderid' => $body_arr['out_trade_no']])->find();
        $key = $order_info['key'];  //商户秘钥
        $signSrc .= "$key";
        $verifySign = md5($signSrc);  //MD5加密
        if ($verifySign !== $body_arr['sign']) {
            Log::record("蘑菇支付Pay：签名校验错误:\n" . var_export($body_arr, true), Log::INFO);
            exit('[错误-2] ' . $body_arr['msg']);
        }

        if ($body_arr['code'] == '0000') {
            if (!empty($exchange)) {
                $return['amount'] = $return['amount'] / $exchange; //转回以元为单位
            }
            $view = $payWay == 'qq_qr' ? 'qq' : 'jd';
            $this->showQRcode($body_arr['code_url'], $return, $view);
        } else {
            exit('[错误-3] ' . $body_arr['msg']);
        }
        die;  //返回json数组
    }

    //异步通知地址
    public function notifyurl()
    {
        Log::record('蘑菇支付异步通知：开始', Log::INFO);
        $input = file_get_contents('php://input');
        Log::record('蘑菇支付异步通知：参数input：' . $input, Log::INFO);
        parse_str($input, $data);

        if (empty($data['out_trade_no']) || $data['amount'] <= 0) {
            Log::record("蘑菇支付异步通知：参数错误:\n" . var_export($data, true), Log::INFO);
            return false;
        }
        if ($data['status'] != '1') {
            Log::record("蘑菇支付异步通知：结果失败:\n" . var_export($data, true), Log::INFO);
            return false;
        }

        //验证签名
        $signSrc = "";
        //排序
        ksort($data);
        foreach ($data as $k => $v) {
            if ($k != 'sign' && !empty($v)) {
                $signSrc .= $k . "=" . $v . "&";
            }
        }
        $signSrc = rtrim($signSrc, '&');
        $order_info = M('Order')->where(['pay_orderid' => $data['out_trade_no']])->find();
        $key = $order_info['key'];  //商户秘钥
        $signSrc .= "$key";
        $verifySign = md5($signSrc);  //MD5加密
        if ($verifySign !== $data['sign']) {
            echo "签名校验错误";
            Log::record("蘑菇支付异步通知：签名校验错误:\n" . var_export($data, true), Log::ERR);
            return false;
        }

        if (intval($data['amount']) != intval($order_info['pay_amount'] * $this->exchange)) {
            Log::record("蘑菇支付异步通知：金额错误:\n" . var_export($data, true), Log::ERR);
            //return false;
        }

        //修改订单信息
        $this->EditMoney($data['out_trade_no'], '', 0);
        Log::record('蘑菇支付异步通知：完成：订单号：' . $data['out_trade_no'], Log::INFO);
        //返回上游
        echo 'SUCCESS';
    }

    //同步回调地址
    public function callbackurl()
    {
        //orderid=20180508132407554910&pay_memberid=10007&bankcode=902
        $orderNumber = I('request.orderid');
        if (empty($orderNumber)) {
            echo '参数错误';
            Log::record("蘑菇支付同步跳转：参数错误：{$orderNumber}", Log::INFO);
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

        $return_code = ($order_info["pay_status"] == 1 || $order_info["pay_status"] == 2) ? "00" : ""; // 交易状态
        $return_array = [ // 返回字段
                          "memberid"       => $order_info["pay_memberid"], // 商户ID
                          "orderid"        => $order_info['out_trade_id'], // 订单号
                          'transaction_id' => $order_info["pay_orderid"], //支付流水号
                          "amount"         => $order_info["pay_amount"], // 交易金额
                          "datetime"       => date("YmdHis"), // 交易时间
                          "returncode"     => $return_code, // 交易状态
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