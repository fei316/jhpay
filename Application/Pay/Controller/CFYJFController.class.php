<?php

/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

namespace Pay\Controller;

use Think\Log;


/**
 * 畅付云计费
 * @package Pay\Controller
 */
class CFYJFController extends PayController
{

    const BANKID_WXSCAN     = '2001';
    const BANKID_ALIPAYSCAN = '2003';
    const BANKID_QQSCAN     = '2008';

    private $gateway = 'http://wx.yzch.net/Pay.aspx';

    public function Pay($channel)
    {
        $exchange = 1;
        $return = $this->getParameter('畅付云计费', $channel, CFYJFController::class, $exchange);

        //$bankid = $_REQUEST['bankId'];//银行ID（见文档）
        $bankid = $this->getBankId();//银行ID（见文档）

        $userid = $return['mch_id'];//用户ID（www.yzch.net获取）
        $orderid = $return['orderid'];//用户订单号（必须唯一）
        $money = $return["amount"];//订单金额
        $keyvalue = $return['signkey'];//用户key（www.yzch.net获取）
        $notify_url = $return['notifyurl'];//用户接收返回URL连接（异步通知）
        $return_url = $return['callbackurl'];//用户接收返回URL连接（异步通知）
        $ext = '';
        $sign = "userid=" . $userid . "&orderid=" . $orderid . "&bankid=" . $bankid . "&keyvalue=" . $keyvalue;
        $sign2 = "money=" . $money . "&userid=" . $userid . "&orderid=" . $orderid . "&bankid=" . $bankid . "&keyvalue=" . $keyvalue;
        $sign = md5($sign);//签名数据 32位小写的组合加密验证串
        $sign2 = md5($sign2);//签名数据2 32位小写的组合加密验证串

        $url = $this->gateway . "?userid=" . $userid . "&orderid=" . $orderid . "&money=" . $money . "&url=" . $notify_url
            . "&aurl=" . $return_url . "&bankid=" . $bankid . "&sign=" . $sign . "&ext=" . $ext . "&sign2=" . $sign2;

        //扫码类型的用自己的界面，其他类型的跳转
        if (in_array($bankid, [self::BANKID_ALIPAYSCAN, self::BANKID_WXSCAN, self::BANKID_QQSCAN])) {

            //$response: '<root><returncode>1</returncode><imgurl>https://cashier.buuyou.com/api/pay?partner=10707&amp;banktype=ALIPAYWAP&amp;paymoney=1&amp;ordernumber=Q1805110081931161301&amp;callbackurl=http://bcallback.yzch.net/BuYouCallback.aspx&amp;hrefbackurl=http://bcallback.yzch.net/BuYouCallback2.aspx?yzchorderno=Q1805110081931161301&amp;attach=&amp;showCashier=0&amp;sign=353a3163b110e1abc87b3f08eda06b77</imgurl><orderno>Q1805110081931161301</orderno></root>'
            $reponse = $this->request($url);
            $resArr = json_decode(json_encode(simplexml_load_string($reponse)), true);

            if ($resArr['returncode'] == 1) {
                $view = '';
                if ($bankid == self::BANKID_WXSCAN) {
                    $view = 'weixin';
                } elseif ($bankid == self::BANKID_QQSCAN) {
                    $view = 'qq';
                } else {
                    $view = 'alipay';
                }
                $this->showQRcode($resArr['imgurl'], $return, $view);
            }
        } else {
            header('Location:' . $url);
        }

    }

    //从参数中获取银行代码
    private function getBankId()
    {
        $bankcode = I('request.pay_bankcode');
        switch ($bankcode) {
            case '901':
                return self::BANKID_WXSCAN; //微信扫码
            case '902':
                return '2005'; //微信WAP
            case '903':
                return self::BANKID_ALIPAYSCAN; //支付宝扫码
            case '904':
                return '2007'; //支付宝WAP
            case '908':
                return self::BANKID_QQSCAN; //QQ扫码
            case '910':
                return '2010'; //京东钱包
            //case '':
            //    return '2011'; //京东钱包WAP
            case '905':
                return '2009'; //QQWAP
            case '911':
                return '2012'; //银联钱包

            default:
                return '2001';
        }
    }

    //异步通知
    public function notifyurl()
    {
        Log::record('畅付云计费异步通知：参数：' . var_export($_GET, true), Log::INFO);

        $returncode = I('request.returncode');

        $userid = I('request.userid'); //order.UserId.ToString();
        $orderid = I('request.orderid');//order.UserOrderNo;
        $money = I('request.money');//order.OrderMoney.ToString();

        //获取订单信息
        $order = M('Order')->where(['pay_orderid' => $orderid])->find();
        if (empty($order)) {
            Log::record('畅付云计费异步通知：订单不存在：单号：' . $orderid, Log::INFO);
            exit('订单不存在');
        }
        $keyvalue = $order['key'];

        $sign = I('request.sign');
        $sign2 = I('request.sign2');
        if (!isset($sign2) && empty($sign2)) {
            echo 'param error';
            exit;
        }

        $localsignSrc = "returncode=" . $returncode . "&userid=" . $userid . "&orderid=" . $orderid . "&keyvalue=" . $keyvalue;
        $localsign2Src = "money=" . $money . "&returncode=" . $returncode . "&userid=" . $userid . "&orderid=" . $orderid . "&keyvalue=" . $keyvalue;

        $localsign = md5($localsignSrc);
        $localsign2 = md5($localsign2Src);

        if ($sign != $localsign) {
            echo 'sign error';
            exit;            //加密错误
        }
        //注意这个带金额的加密 判断 一定要加上，否则非常危险 ！！
        if ($sign2 != $localsign2) {
            echo 'sign2 error';
            exit;            //加密错误
        }

        switch ($returncode) {
            case "1": //成功
                //成功逻辑处理，现阶段只发送成功的单据
                //判断金额是否相等
                if (floatval($order['pay_amount']) !== floatval($money)) {
                    Log::record('畅付云计费异步通知：返回成功但金额错误：单号：' . $orderid . '金额：' . $money, Log::INFO);
                    exit('金额错误');
                }

                //修改订单信息
                $this->EditMoney($orderid, '', 0);
                Log::record('畅付云计费异步通知：完成：订单号：' . $orderid, Log::INFO);
                echo 'ok';
                break;
            default:
                //失败
                break;
        }
    }

    //同步回调地址
    public function callbackurl()
    {
        $orderNumber = I('request.orderid');
        if (empty($orderNumber)) {
            Log::record("畅付云计费同步跳转：参数错误：" . var_export($_GET, true), Log::INFO);
            exit('参数错误');
        }

        $order_info = M('Order')->where(['pay_orderid' => $orderNumber])->find();
        if (!$order_info) {
            Log::record("畅付云计费同步跳转：订单不存在：参数：" . var_export($_GET, true), Log::INFO);
            exit("订单不存在");
        }

        if (!in_array($order_info["pay_status"], [1, 2])) {
            sleep(5);
            $order_info = M('Order')->where(['pay_orderid' => $orderNumber])->find();
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

        $userid = intval($order_info["pay_memberid"] - 10000); // 商户ID
        $member_info = M('Member')->where(['id' => $userid])->find();
        if (!$member_info) {
            Log::record("畅付云计费同步跳转：商户不存在：订单号：{$orderNumber}" . "，用户id：" . $userid, Log::INFO);
            exit("商户不存在");
        }
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