<?php
namespace Pay\Controller;

use Org\Util\HttpClient;

class WxGzhController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function Pay($array)
    {
		$orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Aliscan_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Aliscan_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'WxGzh', // 通道名称
            'title' => '微信H5',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

        $data = array(
            'pid' => '858580',
            'type' => 'wxpay',
            'out_trade_no' => $return['orderid'],
            'notify_url' => $notifyurl,
            'return_url' => $callbackurl,
            'name' => 'pay',
            'money' => $return['amount'],
            'sitename' => '',
            'sign' => '',
            'sign_type' => 'MD5'
        );

        ksort($data);

        $signText = '';
        foreach($data as $k => $v){
            if($k == "sign" || $k == "sign_type" || $v == "") continue;

            $signText .= $k.'='.$v.'&';
        }

        $signText = rtrim($signText, '&');
        $signText .= 'UzBGGGyyGogGAGakMAxXADYyuMcAg7J7';

        $data['sign'] = md5($signText);

        $url = 'https://pay.pinyewang.com/submit.php';
        $parameters = '';
        foreach($data as $k => $v){
            $parameters .= $k.'='.$v.'&';
        }

        $parameters = rtrim($parameters, '&');

        header('Location: '.$url.'?'.$parameters);
    }

    public function jsapi()
    {
        $code = I('get.code');
        $orderid = I('get.state');
        $Order = M("Order");
        $return = $Order->where(["pay_orderid"=> $orderid])->find();
        $urlObj["appid"] = $return['account'];
        $urlObj["secret"] = M('channel_account')->where(['id'=>$return['account_id']])->getField('appsecret');
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $client = new HttpClient();
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token";
        $res = $client->get($url,$urlObj);
        $data = json_decode($res, true);
        $openid = $data['openid'];
        $Ip = new \Org\Net\IpLocation('UTFWry.dat'); // 实例化类 参数表示IP地址库文件
        $location = $Ip->getlocation(); // 获取某个IP地址所在的位置
        $ip = $location['ip'];
        $arraystr = array(
            "trade_type" => "JSAPI",
            'appid' => $return["account"],
            "mch_id" => $return["memberid"],
            "out_trade_no" => $return["pay_orderid"],
            "body" => "VIP会员服务",
            "total_fee" => $return["pay_amount"] * 100,
            "spbill_create_ip" => $ip,
            "notify_url" => $this->_site ."Pay_WxGzh_notifyurl.html",
            "nonce_str" => randpw(32, 'NUMBER'),
            "openid" => $openid,
        );
        ksort($arraystr);

        $buff = "";
        foreach ($arraystr as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");

        //签名步骤二：在string后加入KEY
        $string = $buff . "&key=" . $return["key"];
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $sign = strtoupper($string);
        $arraystr["sign"] = $sign;

        $xml = arrayToXml($arraystr);
        $result = $client->post('https://api.mch.weixin.qq.com/pay/unifiedorder',$xml);
        $arr = xmlToArray($result);
        if ($arr["result_code"] == "SUCCESS") {
            $values['appId'] = $arr["appid"];
            $timeStamp = time();
            $values['timeStamp'] = "$timeStamp";
            $values['nonceStr'] = $arr["nonce_str"];
            $values['package'] = "prepay_id=" . $arr["prepay_id"];
            $values['signType'] = "MD5";

            ksort($values);
            $buff = "";
            foreach ($values as $k => $v) {
                if ($k != "sign" && $v != "" && !is_array($v)) {
                    $buff .= $k . "=" . $v . "&";
                }
            }

            $buff = trim($buff, "&");
            $string = $buff . "&key=" . $return["key"];
            //签名步骤三：MD5加密
            $string = md5($string);
            //签名步骤四：所有字符转为大写
            $sign = strtoupper($string);
            $values['paySign'] = $sign;
            $parameters = json_encode($values);
            $jsApiParameters = $parameters;
            ?>
            <html>
            <head>
                <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <title>微信支付</title>
                <script type="text/javascript">
                    //调用微信JS api 支付
                    function jsApiCall() {
                        WeixinJSBridge.invoke(
                            'getBrandWCPayRequest',
                            <?php echo $jsApiParameters; ?>,
                            function (res) {
                                astr = res.err_msg;
                                if (astr.indexOf("ok") > 0) {
                                    window.location.href = "http://<?php echo C("DOMAIN")?>/Pay_WxGzh_success.html?orderid=<?php echo $orderid; ?>";
                                }

                            }
                        );
                    }
                    function callpay() {
                        if (typeof WeixinJSBridge == "undefined") {
                            if (document.addEventListener) {
                                document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
                            } else if (document.attachEvent) {
                                document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                                document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
                            }
                        } else {
                            jsApiCall();
                        }
                    }
                    callpay();
                </script>
            </head>
            <body>
            </body>
            </html>
            <?php
        } else {
            $this->showmessage($arr['return_msg']);
        }
    }

    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["out_trade_no"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($data['out_trade_no'], 'WxGzh', 1);

            exit('交易成功！');
        }else{
            exit("error");
        }
    }

    // 服务器点对点返回
    public function notifyurl()
    {
        $data = array(
            'pid' => !isset($_REQUEST['pid']) ? '' : trim($_REQUEST['pid']),
            'trade_no' => !isset($_REQUEST['trade_no']) ? '' : trim($_REQUEST['trade_no']),
            'out_trade_no' => !isset($_REQUEST['out_trade_no']) ? '' : trim($_REQUEST['out_trade_no']),
            'type' => !isset($_REQUEST['type']) ? '' : trim($_REQUEST['type']),
            'name' => !isset($_REQUEST['name']) ? '' : trim($_REQUEST['name']),
            'money' => !isset($_REQUEST['money']) ? '' : trim($_REQUEST['money']),
            'trade_status' => !isset($_REQUEST['trade_status']) ? '' : trim($_REQUEST['trade_status']),
            'sign' => !isset($_REQUEST['sign']) ? '' : trim($_REQUEST['sign']),
            'sign_type' => !isset($_REQUEST['sign_type']) ? '' : trim($_REQUEST['sign_type']),
        );

        if($data['trade_status'] != 'TRADE_SUCCESS') exit('trade fail');

        ksort($data);

        $signText = '';
        foreach($data as $k => $v){
            if($k == "sign" || $k == "sign_type" || $v == "") continue;

            $signText .= $k.'='.$v.'&';
        }

        $signText = rtrim($signText, '&');
        $signText .= 'UzBGGGyyGogGAGakMAxXADYyuMcAg7J7';
        $sign = md5($signText);

        if($sign != strtolower($data['sign'])) exit('sign err');

        $this->EditMoney($data['out_trade_no'], 'WxGzh', 0);

        exit("success");
    }

    function ext_json_decode($str, $mode = false)
    {
        $str = preg_replace('/([{,])(\s*)([A-Za-z0-9_\-]+?)\s*:/', '$1"$3":', $str);
        $str = str_replace('\'', '"', $str);
        $str = str_replace(" ", "", $str);
        $str = str_replace('\t', "", $str);
        $str = str_replace('\r', "", $str);
        $str = str_replace("\l", "", $str);
        $str = preg_replace('/s+/', '', $str);
        $str = trim($str, chr(239) . chr(187) . chr(191));

        return json_decode($str, $mode);
    }


    public function success()
    {
        $orderid = I("request.orderid", "");
        $Order = M("Order");
        $xx = $Order->where(["pay_orderid" => $orderid])->getField("xx");
        ?>
        <html>
        <head>
            <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1"/>
            <title>微信支付</title>
        </head>
        <body>
        <br/>
        <font color="#9ACD32"><br/><br/><br/><br/>
            <div align="center">
                <?php
                if ($xx == 0) {
                    ?>
                    <button style="width:210px; height:50px; border-radius: 15px;background-color:#FE6714; border:0px #FE6714 solid; cursor: pointer;  color:white;  font-size:16px;"
                            type="button"
                            onclick="javascript:window.location.href='/Pay_WxGzh_callbackurl.html?orderid=<?php echo $orderid; ?>'">
                        支付成功！
                    </button>
                    <script>
                        setTimeout("tz();", 100);
                        function tz() {
                            window.location.href = "Pay_WxGzh_callbackurl.html?orderid=<?php echo $orderid; ?>";
                        }
                    </script>
                <?php
                }else{
                ?>
                    <span style="color:#ff6c14; font-size:50px;font-weight:bold;">支付成功！</span>
                    <?php
                }
                ?>

            </div>
        </body>
        </html>
        <?php
    }
}

?>
