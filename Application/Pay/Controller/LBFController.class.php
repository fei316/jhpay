<?php


namespace Pay\Controller;

/**
 * 乐百付
 * 官网：http://pay.lebaifupay.com/
 * 备注：上游用的是我们自己的产品
 */
class LBFController extends PayController
{

    private $gateway = 'http://pay.lebaifupay.com/Pay_Index.html';

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site ."Pay_LBF_notifyurl.html"; //异步通知
        $callbackurl = $this->_site . 'Pay_LBF_callbackurl.html'; //跳转通知
        $payBankCode = I('request.pay_bankcode', 'ICBC');

        $parameter = array(
            'code' => 'LBF',
            'title' => '乐百付',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid'=>'',
            'out_trade_id' => $orderid, //外部订单号
            'channel'=>$array,
            'body'=>$body
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

        $params  = [
            "pay_memberid"    => $return['mch_id'],
            "pay_orderid"     => $return['orderid'],
            "pay_amount"      => $return['amount'],
            "pay_applydate"   => date('Y-m-d H:i:s'),
            "pay_bankcode"    => $payBankCode,
            "pay_notifyurl"   => $notifyurl,
            "pay_callbackurl" => $callbackurl,
        ];
        $params['pay_md5sign'] = strtoupper(md5Sign($params, $return['signkey'], '&key='));
        echo createForm($this->gateway, $params);
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $Order      = M("Order");
        $orderid    = I('post.orderid', '');
        $pay_status = $Order->where(['pay_orderid' => $orderid])->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($orderid, '', 1);
        } else {
            exit("error");
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {
        $data = I('request.', '');
        if ($data['returncode'] == '00') {

            $key = getKey($data['orderid']);

            $signitem = [ // 返回字段
                "memberid"       => $data["memberid"], // 商户ID
                "orderid"        => $data["orderid"], // 订单号
                "amount"         => $data["amount"], // 交易金额
                "datetime"       => $data["datetime"], // 交易时间
                "transaction_id" => $data["transaction_id"], // 支付流水号
                "returncode"     => $data["returncode"],
            ];
            $newSign = strtoupper(md5Sign($signitem, $key, '&key='));
            if ($newSign == $data['sign']) {
                $this->EditMoney($data['orderid'], '', 0);
                exit('ok');
            }
        }
    }

}
