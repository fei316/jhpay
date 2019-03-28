<?php
namespace Pay\Controller;

use Think\Log;

class QpayWxSmController extends PayController
{

    public function Pay($array)
    {

        $orderid = I("request.pay_orderid", '');

        $body = I('request.pay_productname', '');

        $parameter = [
            'code'         => 'QpayWxSm',
            'title'        => 'Qpay（微信扫码）',
            'exchange'     => 1, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $orderid, //外部订单号
            'channel'      => $array,
            'body'         => $body,
        ];

        //支付金额
        $pay_amount = I("request.pay_amount", 0);

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);

        //跳转页面，优先取数据库中的跳转页面
        $return["notifyurl"] || $return["notifyurl"] = $this->_site . 'Pay_QpayWxSm_notifyurl.html';

        $return['callbackurl'] || $return['callbackurl'] = $this->_site . 'Pay_QpayWxSm_callbackurl.html';

        $arraystr = [
            'uid'        => $return['mch_id'],
            'price'      => sprintf('%.2f', $return['amount']),
            'istype'     => '2',
            'notify_url' => $return['notifyurl'],
            'return_url' => $return['callbackurl'],
            'orderid'    => $return['orderid'],
        ];

        $arraystr['key'] = $this->_createSign($arraystr, $return['signkey']);

        $body = $this->request($return['gateway'], $arraystr);
        $arr = json_decode($body, true);
        if ($arr['code'] != 1) {
            Log::record('QPay微信扫码网关错误，参数：' . $body);
            exit('网关错误');
        }
        //快捷支付的实际付款金额可能会上下浮动，所以这里要重新赋值一下
        $return['amount'] = $arr['data']['realprice'];
        $this->showQRcode($arr['data']['qrcode'], $return, 'weixin');
    }

    protected function _createForm($url, $data)
    {
        $str = '<!doctype html>
                <html>
                    <head>
                        <meta charset="utf8">
                        <title>正在跳转付款页</title>
                    </head>
                    <body onLoad="document.pay.submit()">
                    <form method="post" action="' . $url . '" name="pay">';

        foreach ($data as $k => $vo) {
            $str .= '<input type="hidden" name="' . $k . '" value="' . $vo . '">';
        }

        $str .= '</form>
                    <body>
                </html>';
        return $str;
    }

    protected function _createSign($data, $key)
    {
        $sign          = '';
        $data['token'] = $key;
        ksort($data);
        foreach ($data as $k => $vo) {
            $sign .= $vo;
        }

        return md5($sign);
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

    public function notifyurl()
    {
        $data      = I('post.', '');
        $sign      = $data['key'];
        $orderList = M('Order')->where(['pay_orderid' => $data['orderid']])->find();
        unset($data['key']);
        $md5Sign = $this->_createSign($data, $orderList['key']);
        $diff    = $orderList['pay_amount'] * 100 - $data['price'] * 100;
        if ($md5Sign == $sign && ($diff == 0 || abs($diff) <= 5)) {
            $this->EditMoney($data['orderid'], '', 0);
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
