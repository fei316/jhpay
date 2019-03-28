<?php
/**
 * Created by PhpStorm.
 * User: mapeijian
 * Date: 2018-06-13
 * Time: 11:33
 */
namespace Pay\Controller;

use Think\Exception;

class RzfkjController extends PayController
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
        $notifyurl = $this->_site . 'Pay_Rzfkj_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Rzfkj_callbackurl.html'; //返回通知
        $card_type = I("request.card_type", 1);
        $bank_accno = I("request.bank_accno", '');
        $parameter = array(
            'code' => 'Rzfkj', // 通道名称
            'title' => '睿支付银联快捷',
            'exchange' => 100, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $return['subject'] = $body;
        if($bank_accno) {
            //支付接口字符串-待签名参数数组
            $parameter = array(
                "spbillno" => $return['orderid'],
                "sp_userid" =>  $return['mch_id'],
                "money" => $return['amount'],
                "memo" => $body,
                "productId" => 'wapApply',
                "card_type"=>$card_type == 1 ? 1 : 2,
                "user_type" => 1,
                "channel" => 2,
                "return_url" => $callbackurl,
                "notify_url" => $notifyurl,
                "bank_accno" => $bank_accno
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
            //生成最终url
            //生产地址
            $chan_url = 'https://www.jrpay.net/Jrpay/tfb8Req/tfb8Req_doCardpayApplyApi?'.$prestr.'&signature='.$signature; // localhost:8088  www.jrpay.net
            header('Location: '.$chan_url);
        } else {
            $url = $this->_site . 'Pay_Rzfkj_topay_orderid_'.$return["orderid"].'.html';
            echo '<script type="text/javascript">window.location.href="'.$url.'"</script>';
            exit;
        }
    }

    public function topay()
    {
        if(IS_POST) {
            $orderid = I("post.orderid");
            if(!$orderid) {
                $this->showmessage("参数错误");
            }
            $order = M('order')->where(array('pay_orderid'=>$orderid))->find();
            if(empty($order)) {
                $this->showmessage("订单不存在");
            }
            if($order['pay_status'] != 0) {
                $this->showmessage("订单已支付");
            }
            $card_type = I("post.card_type", 1);
            if(!$card_type) {
                $this->showmessage("银行卡类型不能为空");
            }
            $bank_accno = I("post.bank_accno");
            if(!$bank_accno) {
                $this->showmessage("银行卡号不能为空");
            }
            $notifyurl = $this->_site . 'Pay_Rzfkj_notifyurl.html'; //异步通知
            $callbackurl = $this->_site . 'Pay_Rzfkj_callbackurl.html'; //返回通知
            //支付接口字符串-待签名参数数组
            $parameter = array(
                "spbillno" => $orderid,
                "sp_userid" =>  $order['memberid'],
                "money" => round($order['pay_amount'],4)*100,
                "memo" => $orderid,
                "productId" => 'wapApply',
                "card_type"=>$card_type,
                "user_type" => 1,
                "channel" => 2,
                "return_url" => $callbackurl,
                "notify_url" => $notifyurl,
                "bank_accno" => $bank_accno,
            );
            //除去待签名参数数组中的空值和签名参数
            $para_sort = $this->paraFilter($parameter);
            //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串-生成url参数
            $prestr = $this->createLinkstring($para_sort);
            //合并pubkey数据
            $para_sort=array_merge($para_sort,array('pubKey'=>$order['key']));
            //对待签名参数数组排序
            $para_sort = $this->argSort($para_sort);
            //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
            $prestr_md5 = $this->createLinkstring($para_sort);
            //准备生成签名
            $signature = md5($prestr_md5);
            //生成最终url
            //生产地址
            $chan_url = 'https://www.jrpay.net/Jrpay/tfb8Req/tfb8Req_doCardpayApplyApi?'.$prestr.'&signature='.$signature;
            echo '<script type="text/javascript">window.location.href="'.$chan_url.'"</script>';
            exit;
        } else {
            $orderid = I("orderid");
            if(!$orderid) {
                $this->showmessage("参数错误");
            }
            $order = M('order')->where(array('pay_orderid'=>$orderid))->find();
            if(empty($order)) {
                $this->showmessage("订单不存在");
            }
            if($order['pay_status'] != 0) {
                $this->showmessage("订单已支付");
            }
            $rpay_url = $this->_site . 'Pay_Rzfkj_topay.html';
            $this->assign('orderid', $orderid);
            $this->assign('rpay_url', $rpay_url);
            $this->assign('money', round($order['pay_amount'],4));
            $this->display('Rzfbank/kj');
        }
    }

    //同步通知
    public function callbackurl()
    {
        $str = array_keys($_GET,"")[0];
        parse_str($str);
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $spbillno])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($spbillno, 'Rzfkj', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {
        file_put_contents('./Data/notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $parameter=$_POST;
        if(!empty($parameter)){
            if(!$parameter['spbillno']) {
                exit('FAIL');
            }
            $pkey = getKey($parameter['spbillno']);
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
                if ($parameter['result'] == 1)
                {
                    $this->EditMoney($parameter['spbillno'], 'Rzfkj', 0);
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
                echo 'FAIL2';
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