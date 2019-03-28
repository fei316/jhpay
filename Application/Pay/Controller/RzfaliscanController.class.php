<?php
/**
 * Created by PhpStorm.
 * User: mapeijian
 * Date: 2018-06-13
 * Time: 11:33
 */
namespace Pay\Controller;

use Think\Exception;

class RzfaliscanController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Rzfaliscan_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Rzfaliscan_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'Rzfaliscan', // 通道名称
            'title' => '睿支付支付宝扫码',
            'exchange' => 100, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

       /* $this->EditMoney($return['orderid'], 'Rzfaliscan', 0);
        die;*/
        $return['subject'] = $body;
        //支付接口字符串-待签名参数数组
        $parameter = array(
            "subNo" => $return['mch_id'],//子商户号，由睿支付系统提供
            "productId" => "0119", //产品类型- 0119-支付宝扫码支付 0120-支付宝刷卡（反扫）0108-微信扫码支付 0113-微信刷卡（反扫）0112-公众号支付  0130 - 微信APP内支付  0131 - 支付宝APP内支付
            "orderNo" => $return['orderid'],//订单编号
            "transAmt" => $return['amount'], //交易金额，单位为分 100 = 1 元
            "notifyUrl" => urlencode($notifyurl), //异步通知地址，由睿支付后台推送交易结果
            "returnUrl" => urlencode($callbackurl), //页面通知地址，由睿支付后台推送交易结果
            "commodityName" => urlencode($return['subject']), //自定义商品名称   没有则传商户编号或商户名称
        );
        //除去待签名参数数组中的空值和签名参数
        $para_sort = $this->paraFilter($parameter);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串-生成url参数
        $prestr = $this->createLinkstring($para_sort);
        //合并pubkey数据
        $para_sort=array_merge($para_sort,array('pubKey'=>$return['signkey']));
        //对待签名参数数组排序
        $para_sort = $this->argSort($para_sort);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr_md5 = $this->createLinkstring($para_sort);
        //准备生成签名
        $signature = md5($prestr_md5);
        $chan_url = 'http://www.jrpay.net/Jrpay/payReq/payReq_backTransReq?'.$prestr.'&signature='.$signature;
        $tourl=json_decode($this->http($chan_url),true);
        import("Vendor.phpqrcode.phpqrcode",'',".php");
        $url = urldecode($tourl["codeUrl"] );
        $QR = "Uploads/codepay/". $return["orderid"] . ".png";//已经生成的原始二维码图
        \QRcode::png($url, $QR, "L", 20);
        $this->assign("imgurl", $this->_site.$QR);
        $this->assign('params',$return);
        $this->assign('orderid',$return['orderid']);
        $this->assign('money',$return['amount']/100);
        $this->display("WeiXin/alipay");


    }


    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'Rzfaliscan', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {
        //file_put_contents('./Data/notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $parameter=$_POST;
        if(!empty($parameter)){
            if(!$parameter['orderNo']) {
                exit('FAIL');
            }
            $pkey = getKey($parameter['orderNo']);
            if(!$pkey) {
                exit('FAIL');
            }
            //除去待签名参数数组中的空值和签名参数
            $para_sort = $this->paraFilter($parameter);
            //合并pubkey数据
            $para_sort=array_merge($para_sort,array('pubKey'=>$pkey));
            //对待签名参数数组排序
            $para_sort = $this->argSort($para_sort);
            //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
            $prestr_md5 = $this->createLinkstring($para_sort);
            //准备生成签名
            $signature = md5($prestr_md5);
            if($signature==$parameter['signature'])
            {
                if ($parameter['respCode'] == '0006')
                {
                    $this->EditMoney($_POST['orderNo'], 'Rzfaliscan', 0);
                    //验证完成，
                    echo 'SUCCESS';
                }
                else
                {
                    echo 'FAIL';
                }
            }
            else
            {
                echo 'FAIL';
            }
        } else {
            echo 'FAIL';
        }
    }
    //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
    private function createLinkstring($para) {
        $arg  = "";
        while (list ($key, $val) = each ($para)) {
            $arg.=$key."=".$val."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg,0,count($arg)-2);

        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}

        return $arg;
    }
    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    private function paraFilter($para) {
        $para_filter = array();
        while (list ($key, $val) = each ($para)) {
            if($key == "signature" || $val == "")continue;
            else    $para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }
    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    private function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    /*
    * curl http访问
    * */
    private function http($url, $method = 'get', $postfields = '')
    {
        $ci = curl_init();
        $header[] = "Content-type: text/xml";//定义content-type为xml
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
        if ($method == 'POST')
        {
            curl_setopt($ci, CURLOPT_POST, TRUE);
            if ($postfields != '') curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
        }
        curl_setopt($ci, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ci, CURLOPT_URL, $url);
        $response = curl_exec($ci);
        curl_close($ci);
        return $response;
    }
}