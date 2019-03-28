<?php
namespace Pay\Controller;
use Think\Log;
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
/**
 * 科软云支付渠道
 * 官网：
 * 测试：demo/KRY1.php
 */
class KRYController extends PayController
{

    /**
     * @var string 支付网关
     */
    private $gateway = 'http://www.payshell.com.cn/eapi/pay?format=json';

    /**
     * @param array $channel
     */
    public function Pay($channel)
    {

        $body = I('request.pay_productname', '');
        $outOrderId = I('request.pay_orderid', '');
        $payType = I('request.pay_paytype', 'alipay'); //支付类型

        $parameter = [
            'code'         => 'KRY', // 通道名称
            'title'        => '科软云支付',
            'exchange'     => 1, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $outOrderId,
            'body'         => $body,
            'channel'      => $channel,
        ];
        $return = $this->orderadd($parameter);

        $version = '1.0';
        $customerid = $return['mch_id']; //商户ID
        $sdorderno = $return['orderid'];
        $total_fee = $return['amount']; //支付金额
        $paytype = $payType; //支付方式
        $notifyurl = $return['notifyurl'];
        $returnurl = $return['callbackurl'];
        $remark = '';
        $key = $return['signkey'];

        $sign = md5('version=' . $version . '&customerid=' . $customerid . '&total_fee=' . $total_fee . '&sdorderno=' .
            $sdorderno . '&notifyurl=' . $notifyurl . '&returnurl=' . $returnurl . '&key=' . $key);


        $postParams = [
            'version'    => $version,
            'customerid' => $customerid,
            'sdorderno'  => $sdorderno,
            'total_fee'  => $total_fee,
            'paytype'    => $paytype,
            'notifyurl'  => $notifyurl,
            'returnurl'  => $returnurl,
            'remark'     => $remark,
            'sign'       => $sign,
        ];

        //// 扫码方式用自己的页面展示二维码
        //if (in_array($paytype, ['alipay', 'qq', 'weixin'])) {
        //    //{"code":0,"msg":success","data":{url:””}}
        //    $jsonstr = $this->request($this->gateway, $postParams);
        //    $jsonarr = json_decode($jsonstr, true);
        //    if (!empty($jsonarr) && $jsonarr['code'] != 0) {
        //        Log::record('科软云支付Pay：网关错误，返回内容：' . $jsonstr, Log::ERR);
        //        exit('网关错误(KRY-1): ' . $jsonarr['msg']);
        //    }
        //    Log::record('科软云支付Pay：返回内容：' . $jsonstr, Log::INFO);
        //    $this->showQRcode($jsonarr['data']['url'], $return, $paytype);
        //
        //} else { //其他方式用页面跳转
        //    $this->setHtml($this->gateway, $postParams);
        //}

        $this->setHtml($this->gateway, $postParams);
    }

    //同步回调地址
    public function callbackurl()
    {
        $status=$_POST['status'];
        $customerid=$_POST['customerid'];
        $sdorderno=$_POST['sdorderno'];
        $total_fee=$_POST['total_fee'];
        $sdpayno=$_POST['sdpayno'];
        $sign=$_POST['sign'];

        $order = M('Order')->where(['pay_orderid' => $sdorderno])->find();
        $key = $order['key'];

        $mysign=md5("customerid={$customerid}&status=1&sdorderno={$sdorderno}&total_fee={$total_fee}&sdpayno={$sdpayno}&key={$key}");
        if($sign==$mysign){
            if ($status != 1) {
                Log::record('科软云支付同步通知：交易失败，单号：' . $sdorderno . "，参数：" . json_encode(I('request.')), Log::ERR);
                exit('交易失败(KRY-5)');
            }
            if ($order['pay_status'] == 0) {
                sleep(5);//等待5秒
                $order = M('Order')->where(['pay_orderid' => $sdorderno])->find();
            }
            if ($order['pay_status'] <> 0) {
                $this->EditMoney($sdorderno, '', 1); //第三个参数为1时，页面会跳转到订单信息里面的 pay_callbackurl
                Log::record('科软云支付同步回调：订单修改成功', Log::INFO);
                exit("订单号 {$sdorderno} 成功支付 {$total_fee} 元");
            } else {
                exit('订单异常请联系客服，订单号：' . $order['out_trade_id']);
            }
        } else {
            Log::record("科软云支付异步通知：签名验证失败，返回内容：\n" . json_encode(I('request.')), Log::ERR);
            echo "数据验证失败(KRY-4)";
        }
    }

    //异步通知地址
    public function notifyurl()
    {
        Log::record('科软云支付异步通知：开始：' . PHP_EOL . json_encode($_POST), Log::INFO);
        $status=$_POST['status'];
        $customerid=$_POST['customerid'];
        $sdorderno=$_POST['sdorderno'];
        $total_fee=$_POST['total_fee'];
        $sdpayno=$_POST['sdpayno'];
        $sign=$_POST['sign'];

        $order = M('Order')->where(['pay_orderid' => $sdorderno])->find();
        $key = $order['key'];
        $orderNumber = $sdorderno;

        $mysign=md5("customerid={$customerid}&status=1&sdorderno={$sdorderno}&total_fee={$total_fee}&sdpayno={$sdpayno}&key={$key}");

        if ($sign == $mysign) {
            if ($status != 1) {
                Log::record('科软云支付异步通知：交易失败，单号：' . $orderNumber . "，参数：" . json_encode(I('request.')), Log::ERR);
                exit('交易失败(KRY-1)');
            }
            if ($total_fee != $order['pay_amount']) {
                Log::record('科软云支付异步通知：交易失败，原因：金额不匹配，单号：' . $orderNumber . "，参数：" . json_encode(I('request.')), Log::ERR);
                exit('交易失败(KRY-2)');
            }
            $this->EditMoney($orderNumber, 'KRY', 0);
            Log::record('科软云支付异步通知：订单修改成功', Log::INFO);
            exit("ok");
        } else {
            Log::record("科软云支付异步通知：签名验证失败，返回内容：\n" . json_encode(I('request.')), Log::ERR);
            echo "数据验证失败(KRY-3)";
        }
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

?>
