<?php

/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

namespace Pay\Controller;

use Think\Log;


/**
 * 官网地址：http://www.kuaikuaifu.cc/
 * @package Pay\Controller
 */
class KuaikuaifuController extends PayController
{
    private $payway = [
        '903' => 'alipay',
        '904' => 'aliwap',
    ];

    private $exchange = 1;
    private $gateway  = 'https://paykuaikuaifu.com/pay.php';

    public function Pay($channel)
    {
        $exchange = $this->exchange;
        $return = $this->getParameter('快快付', $channel, KuaikuaifuController::class, $exchange);

        if(!isset($this->payway[I('request.pay_bankcode')]))
        {
            throw_exception('无效的支付方式');
        }

        $return['callbackurl']  = str_replace(
            'Pay_Kuaikuaifu_callbackurl.html',
            sprintf('Pay_Kuaikuaifu_callbackurl_%s_%s.html','payorderid',$return['orderid']),
            $return['callbackurl']
        );

        $native = [
            'notify_url'   => $return['notifyurl'],
            'return_url'   => $return['callbackurl'],
            'user_account' => $return['mch_id'],// 商户ID
            'out_trade_no' => $return['orderid'],// 订单号
            'payment_type' => $this->payway[I('request.pay_bankcode')],
            'total_fee'    => $return['amount'],// 交易金额
            'trade_time'   => date('Y-m-d H:i:s'),// 交易时间
            'body'         => '',
        ];

        $md5key = $return['signkey'];


        $native["sign"] = $this->_make_sign($native,$md5key);

        $this->setHtml($this->gateway, $native);
    }

    //异步通知地址
    public function notifyurl()
    {
        $post = file_get_contents("php://input");
        $data = json_decode($post, true);

        $returnArray = [
            "memberid"       => $data["user_account"], // 商户ID
            "orderid"        => $data["out_trade_no"], // 订单号
            "amount"         => $data["total_fee"], // 交易金额
            "datetime"       => $data["notify_time"], // 交易时间
            "transaction_id" => $data["trade_no"], // 支付流水号
            "returncode"     => $data["status"],
        ];
        $order_info = M('Order')->where(['pay_orderid' => $returnArray['orderid']])->find();
        $md5key = $order_info['key'];  //商户秘钥
        if (!$this->_validate_sign($data, $md5key)) {
            echo "签名校验错误";
            Log::record("快快付异步通知：签名校验错误:\n" . json_encode($data), Log::ERR);
            return false;
        }

        if ($returnArray["returncode"] == "SUCCESS") {
            //修改订单信息
            $this->EditMoney($returnArray['orderid'], '', 0);
            Log::record("快快付异步通知：" . "交易成功！订单号：" . $returnArray["orderid"], Log::INFO);
            exit("SUCCESS");
        } else {
            Log::record("快快付异步通知：" . "交易失败！订单号：" . $returnArray["orderid"] . "，参数：". json_encode($data), Log::ERR);
            exit("FAIL");
        }

    }

    //同步回调地址
    public function callbackurl()
    {
        $pay_orderid = $_REQUEST['payorderid'];

        $Order      = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $pay_orderid])->getField("pay_status");
        if($pay_status == 0)
        {
            sleep(3);//等待3秒
            $pay_status = M('Order')->where(['pay_orderid' => $pay_orderid])->getField("pay_status");
        }
        if($pay_status <> 0)
        {
            $this->EditMoney($pay_orderid, '', 1);
        }
        else
        {
            exit('页面已过期请刷新');
        }
    }

    /**
     * @param $data
     * @param $key
     *
     * @return string
     */
    private function _make_sign($data, $key)
    {

        //签名步骤一：按字典序排序参数
        ksort($data);
        //签名步骤二：使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串
        $string = $this->_to_url_params($data);
        //签名步骤三：在string后加入KEY
        $string = $string . "&key=".$key;
        //签名步骤四：MD5加密
        $string = md5($string);
        //签名步骤五：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    /**
     * @param $data
     *
     * @return string
     */
    private function _to_url_params($data)
    {
        $buff = "";
        foreach ($data as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * @param $data
     * @param $key
     *
     * @return bool
     */
    private function _validate_sign($data, $key)
    {

        $sign = $data['sign'];
        unset($data['sign']);

        //签名步骤一：按字典序排序参数
        ksort($data);
        //签名步骤二：使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串
        $string = $this->_to_url_params($data);
        //签名步骤三：在string后加入KEY
        $string = $string . "&key=".$key;
        //签名步骤四：MD5加密
        $string = md5($string);
        //签名步骤五：所有字符转为大写
        $result = strtoupper($string);

        if($result == $sign)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}