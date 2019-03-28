<?php

/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

namespace Pay\Controller;

use Think\Exception;
use Think\Log;
use Think\Think;


/**
 * 易宝-银联H5
 * 官网地址：http://www.tfb8.com/
 * @package Pay\Controller
 */
class YibaoYlwapController extends PayController
{

    //支付方式code
    private $code = '';

    private $desc = '易宝-银联H5';

    private $exchange = 1;

    public function __construct()
    {
        parent::__construct();

        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }

    /**
     * @param $channel
     *
     * @throws Exception
     */
    public function Pay($channel)
    {
        $exchange = $this->exchange;
        $return   = $this->getParameter($this->desc, $channel, __CLASS__, $exchange);
        $this->topay($return);
    }

    public function topay($order = null)
    {
        if(is_array($order) && !empty($order) && isset($order['orderid']))
        {
            $this->view = Think::instance('Think\View');
            $this->assign('orderid', $order['orderid']);
            $this->assign('gateway', $order['gateway']);
            $this->assign('rpay_url', $this->_site . "Pay_{$this->code}_topay.html");
            $this->assign('money', round($order['amount'], 4));
            $this->display('YibaoKj/kj');
        }
        else
        {
            $gateway = I("post.gateway");
            $orderid = I("post.orderid");
            $phoneno = I("post.phoneno");
            if(!$orderid)
            {
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
            $order = M('order')->where(array('pay_orderid' => $orderid))->find();
            if(empty($order))
            {
                $this->ajaxReturn(['status' => 0, 'msg' => '订单不存在']);
            }
            if($order['pay_status'] != 0)
            {
                $this->ajaxReturn(['status' => 0, 'msg' => '订单已支付']);
            }
            if(!preg_match("/^1[34578]{1}\d{9}$/", $phoneno))
            {
                $this->ajaxReturn(['status' => 0, 'msg' => '无效的手机号']);
            }
            $gateway = $gateway . '/pay/create';

            $notifyurl   = $this->_site . "Pay_{$this->code}_notifyurl.html"; //异步通知
            $callbackurl = $this->_site . "Pay_{$this->code}_callbackurl.html"; //返回通知

            $parameter = [
                'merchantId' => $order['memberid'],
                'timestamp'  => time() . '000',
                'body'       => [
                    'payTypeId'   => 'cp_h5_d0_v1',
                    'amount'      => $order['pay_amount'],
                    'orderId'     => $order['pay_orderid'],
                    'details'     => $order['pay_product_name'] ?: 'trade',
                    'userId'      => $phoneno,
                    'notifyUrl'   => $notifyurl,
                    'redirectUrl' => $callbackurl,
                ],
            ];


            $sign = $this->_createSign($parameter, $order['key']);

            $response = curlPost(
                $gateway,
                json_encode($parameter),
                [
                    'Content-Type: application/json',
                    "Api-Sign: {$sign}",
                ]
            );

            $res = json_decode($response, true);
            if(!isset($res['status']))
            {
                $this->ajaxReturn(['status' => 0, 'msg' => '支付服务不可用']);
            }
            else if($res['status'] == 0)
            {
                $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'url'=>$res['body']['content']]);
            }
            else
            {
                $this->ajaxReturn(['status' => 0, 'msg' => es['message']]);
            }
        }
    }


    //异步通知地址
    public function notifyurl()
    {
        $body        = json_decode(file_get_contents("php://input"), true);
        $sign        =
            isset($_SERVER['HTTP_API_SIGN']) ? $_SERVER['HTTP_API_SIGN'] : '';
        $pay_orderid = $body['body']['orderId'];

        $Order = M("Order");
        $order = $Order->where(['pay_orderid' => $pay_orderid])->find();

        if(empty($order))
        {
            exit('订单不存在');
        }

        if ($this->_createSign($body, $order['key']) != $sign) {
            echo "签名校验错误";
            Log::record("易宝银联H5异步通知：签名校验错误:\n" . json_encode($body), Log::ERR);
            return false;
        }

        if ($body["body"]['status'] == "1") {
            //修改订单信息
            $this->EditMoney($pay_orderid, '', 0);
            Log::record("易宝银联H5异步通知：" . "交易成功！订单号：" . $pay_orderid, Log::INFO);
            exit("SUCCESS");
        } else {
            Log::record("易宝银联H5异步通知：" . "交易失败！订单号：" . $pay_orderid . "，参数：". json_encode($body), Log::ERR);
            exit("FAIL");
        }
    }

    //同步回调地址
    public function callbackurl()
    {
        $body        = json_decode($_GET['body'], true);
        $sign        = isset($_GET['Api-Sign']) ? $_GET['Api-Sign'] : $_GET['amp;Api-Sign'];
        $pay_orderid = $body['body']['orderId'];

        $Order = M("Order");
        $order = $Order->where(['pay_orderid' => $pay_orderid])->find();

        if(empty($order))
        {
            exit('订单不存在');
        }

        if($this->_createSign($body, $order['key']) != $sign)
        {
            exit('验签失败');
        }

        $pay_status = $order['pay_status'];
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
     * 规则是:按参数名称a-z排序,遇到空值的参数不参加签名。
     */
    private function _createSign($data, $key)
    {
        $jsonData = json_encode($data);

        return md5($jsonData.'|'.$key);
    }

    public function log()
    {
        file_put_contents("./Data/{$this->code}_notify.txt",
            "【" . date('Y-m-d H:i:s') . "】\r\n" . file_get_contents("php://input") . "\r\n\r\n", FILE_APPEND);
        file_put_contents("./Data/{$this->code}_notify.txt",
            "【" . date('Y-m-d H:i:s') . "】\r\n" . $_SERVER["QUERY_STRING"] . "\r\n\r\n", FILE_APPEND);
        file_put_contents("./Data/{$this->code}_notify.txt",
            "【" . date('Y-m-d H:i:s') . "】\r\n" . json_encode($_SERVER['HTTP_API_SIGN']) . "\r\n\r\n", FILE_APPEND);
//        file_put_contents("./Data/{$this->code}_notify.txt",
//            "【" . date('Y-m-d H:i:s') . "】\r\n" . json_encode(getallheaders ()) . "\r\n\r\n", FILE_APPEND);
//        file_put_contents("./Data/{$this->code}_notify.txt",
//            "【" . date('Y-m-d H:i:s') . "】\r\n" . json_encode($_REQUEST) . "\r\n\r\n", FILE_APPEND);
    }
}