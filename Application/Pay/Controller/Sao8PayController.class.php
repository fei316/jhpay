<?php
namespace Pay\Controller;
use Think\Log;

/**
 * 扫呗支付渠道
 * 官网：http://sao8pay.com/
 * 测试：demo/sao8pay_1.php
 */
class Sao8PayController extends PayController
{

    /**
     * @var string 支付网关
     */
    private $gateway = 'http://api.sao8pay.com/online/gateway';

    /**
     * @param array $channel
     */
    public function Pay($channel)
    {
        $body = I('request.pay_productname', '');
        $outOrderId = I('request.pay_orderid', '');
        $bankType = I('request.pay_banktype', 'ALIPAYWAP'); //银行类型
        $payMoney = I('request.pay_amount', 0);

        $parameter = [
            'code'         => 'Sao8Pay', // 通道名称
            'title'        => '扫呗',
            'exchange'     => 1, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $outOrderId,
            'body'         => $body,
            'channel'      => $channel,
        ];
        $return = $this->orderadd($parameter);

        $version = "3.0";
        $method = 'YF.online.interface';
        $partner = $return["mch_id"]; // 商户ID
        $orderNumber = $return['orderid']; //商户订单号
        $callbackUrl = $this->_site . 'Pay_Sao8Pay_notifyurl.html'; //异步通知地址
        $hrefbackUrl = $this->_site . 'Pay_Sao8Pay_callbackurl.html'; //同步回调地址
        $key = $return['signkey']; //商户密钥
        $signSource = sprintf("version=%s&method=%s&partner=%s&banktype=%s&paymoney=%s&ordernumber=%s&callbackurl=%s%s",
            $version, $method, $partner, $bankType, $payMoney, $orderNumber, $callbackUrl, $key);
        $sign = md5($signSource);

        $postParams = [
            'version'     => $version,
            'method'      => $method,
            'partner'     => $partner,
            'banktype'    => $bankType,
            'paymoney'    => $payMoney,
            'ordernumber' => $orderNumber,
            'callbackurl' => $callbackUrl,
            'hrefbackurl' => $hrefbackUrl,
            'attach'      => '',
            'isshow'      => 1,
            'sign'        => $sign,
        ];

        $this->setHtml($this->gateway, $postParams);
    }

    //同步回调地址
    public function callbackurl()
    {
        Log::record('同步支付回调：开始：' . PHP_EOL . var_export(func_get_args(), true), Log::INFO);
        $partner = I('request.partner'); // 商户ID
        $orderNumber = I('request.ordernumber'); // 商户订单号
        $orderStatus = I('request.orderstatus'); // 1:支付成功，非1为支付失败
        $payMoney = I('request.paymoney'); // 单位元（人民币）
        $sysNumber = I('request.sysnumber'); // 此次交易中扫呗接口系统内的订单ID
        $attach = I('request.attach'); // 备注信息，上行中attach原样返回
        $sign = I('request.sign'); // 32位小写MD5签名值，GB2312编码
        $order = M('Order')->where(['pay_orderid' => $orderNumber])->find();
        $key = $order['key']; // 密钥
        $signSource = "partner={$partner}&ordernumber={$orderNumber}&orderstatus={$orderStatus}&paymoney={$payMoney}{$key}";
        $md5Sign = md5($signSource);
        if ($sign == $md5Sign) {
            Log::record('同步支付回调：参数验证成功', Log::INFO);
            if($order['pay_status'] == 0) {
                sleep(5);//等待5秒
                $order = M('Order')->where(['pay_orderid' => $orderNumber])->find();
            }
            if ($order['pay_status'] <> 0) {
                $this->EditMoney($orderNumber, '', 1); //第三个参数为1时，页面会跳转到订单信息里面的 pay_callbackurl
                Log::record('同步支付回调：订单修改成功', Log::INFO);
                exit("订单号 {$orderNumber} 成功支付 {$payMoney} 元");
            } else {
                exit('订单异常请联系客服，订单号：'.$order['out_trade_id']);
            }
        } else {
            Log::record("同步支付回调：参数验证失败：\nsignSource: " . $signSource . "\nsign:" . $sign, Log::ERR);
            echo "数据验证失败";
        }
    }

    //异步通知地址
    public function notifyurl()
    {
        Log::record('异步支付通知：开始：' . PHP_EOL . var_export(func_get_args(), true), Log::INFO);
        $partner = I('request.partner'); // 商户ID
        $orderNumber = I('request.ordernumber'); // 商户订单号
        $orderStatus = I('request.orderstatus'); // 1:支付成功，非1为支付失败
        $payMoney = I('request.paymoney'); // 单位元（人民币）
        $sysNumber = I('request.sysnumber'); // 此次交易中扫呗接口系统内的订单ID
        $attach = I('request.attach'); // 备注信息，上行中attach原样返回
        $sign = I('request.sign'); // 32位小写MD5签名值，GB2312编码
        $order = M('Order')->where(['pay_orderid' => $orderNumber])->find();
        $key = $order['key']; // 密钥
        $signSource = "partner={$partner}&ordernumber={$orderNumber}&orderstatus={$orderStatus}&paymoney={$payMoney}{$key}";
        $md5Sign = md5($signSource);
        if ($sign == $md5Sign) {
            Log::record('异步支付通知：参数验证成功', Log::INFO);
            if ($orderStatus != 1) {
                Log::record('异步支付通知：交易失败，单号：' . $orderNumber . "，参数：" . json_encode(I('request.')), Log::ERR);
                exit('交易失败 -1');
            }
            if ($payMoney != $order['pay_amount']) {
                Log::record('异步支付通知：交易失败，原因：金额不匹配，单号：' . $orderNumber . "，参数：" . json_encode(I('request.')), Log::ERR);
                exit('交易失败 -2');
            }
            $this->EditMoney($orderNumber, 'Sao8Pay', 0);
            Log::record('异步支付通知：订单修改成功', Log::INFO);
            exit("ok");
        } else {
            Log::record("异步支付通知：参数验证失败：\nsignSource: " . $signSource . "\nsign:" . $sign, Log::ERR);
            echo "数据验证失败";
        }
    }
}

?>
