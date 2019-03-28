<?php
namespace Pay\Controller;

class MqyAliSmController extends PayController
{

    public function Pay($array)
    {

        $orderid = I("request.pay_orderid", '');

        $body = I('request.pay_productname', '');

        $parameter = [
            'code'         => 'MqyWxSm',
            'title'        => '免签约（支付宝扫码）',
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
        $return["notifyurl"] || $return["notifyurl"] = $this->_site . 'Pay_MqyWxSm_notifyurl.html';

        $return['callbackurl'] || $return['callbackurl'] = $this->_site . 'Pay_MqyWxSm_callbackurl.html';

        $price      = sprintf('%.2f', $return['amount']);
        $order_no   = $return['orderid'];
        $trade_type = 'wxpay';

        $shop_name  = '金银通';    // 商家名称
        $goods_name = '购买商品'; // 购买商品
        $notify_url = $return["notifyurl"]; // 回调地址
        $return_url = $return['callbackurl'] ;  // 支付成功后的跳转地址
        $extend     = ''; // 附加信息，此信息将转发给回调地址，默认为空
        $trade_id = $return['mch_id'];  //商户ID
        $trade_key = $return['signkey'];//商户密钥
        $license = $return['appid'];    //授权码
        $pay_url = file_get_contents('http://gdn1.mianqianyue.com/pay_url'); // 动态获取支付页地址
        $sign = md5($trade_id . $trade_key . $license . $order_no . $price . $notify_url . $return_url . $extend); // 数据签名
        echo '<form style="display:none" name="order_form" method="post" action="' . $pay_url . '">
                  <input name="shop_name" type="text" value="' . $shop_name . '"/>
                  <input name="goods_name" type="text" value="' . $goods_name . '"/>
                  <input name="price" type="text" value="' . $price . '"/>
                  <input name="order_no" type="text" value="' . $order_no . '"/>
                  <input name="notify_url" type="text" value="' . $notify_url . '"/>
                  <input name="return_url" type="text" value="' . $return_url . '"/>
                  <input name="extend" type="text" value="' . $extend . '"/>
                  <input name="trade_id" type="text" value="' . $trade_id . '"/>
                  <input name="license" type="text" value="' . $license . '"/>
                  <input name="trade_type" type="text" value="' . $trade_type . '"/>
                  <input name="sign" type="text" value="' . $sign . '"/>
              </form>
              <script>document.order_form.submit();</script>';
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
        if(!isset($data['order_no']) && $data['order_no']) {
            exit('unknown');
        }
        $order = M('Order')->where(['pay_orderid' => $data['order_no']])->find();
        if(empty($order)) {
            exit('unknown');// 订单不存在
        }
        $localSign = md5($order['memberid'] . $order['key'] . $order['account'] . $data['trade_no'] . $data['real_price'] . $data['trade_account'] . $data['extend']);

        if (strtoupper($localSign) == $data['sign']) // 验证数据签名
        {
            if($order['pay_amount'] != $data['price']) {
                exit('fail');
            }
            $this->EditMoney($data['order_no'], '', 0);
            exit('success');
        } else {
            exit('fail'); // 数据签名验证失败
        }
    }
}
