<?php
/**
 * Created by PhpStorm.
 * User: mapeijian
 * Date: 2018-06-13
 * Time: 11:33
 */
namespace Pay\Controller;

class SwzfbankController extends PayController
{
    /**
     * @var string 支付网关
     */
    private $gateway = 'http://pay.dns55.cn/apisubmit';

    public function __construct()
    {
        parent::__construct();
    }

    //直连参数
    protected $_bank_code = array(
        "ICBC"=>'中国工商银行',
        "ABC"=>'中国农业银行',
        "CCB"=>'中国建设银行',
        "PSBC"=>'中国邮政储蓄银行',
        "CEB"=>'光大银行',
        "CMBC"=>'民生银行',
        "BCCB"=>'北京银行',
        "BOS"=>'上海银行'
    );

    //支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Swzfbank_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Swzfbank_callbackurl.html'; //返回通知
        $bank_code = I("request.bank_code",'');
        if($bank_code) {
            if (!array_key_exists($bank_code, $this->_bank_code)) {
                $bank_code = '';
            }
        }
        $parameter = array(
            'code' => 'Swzfbank', // 通道名称
            'title' => '思维支付网银支付',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body'=>$body,
            'channel'=>$array
        );
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $return['subject'] = $body;
        if($bank_code) {
            //支付接口字符串-待签名参数数组
            $parameter = array(
                "version" => '1.0',
                "customerid" => $return['mch_id'],
                "sdorderno" => $return['orderid'],
                "total_fee" => number_format($return['amount'],2,'.',''),
                "paytype" => 'bank',
                "bankcode" => $bank_code,
                "notifyurl" => $notifyurl,
                "returnurl" => $callbackurl,
                "remark" => ''
            );
            $parameter['sign']=md5('version='.$parameter['version'].'&customerid='.$parameter['customerid'].'&total_fee='.$parameter['total_fee'].'&sdorderno='.$parameter['sdorderno'].'&notifyurl='.$parameter['notifyurl'].'&returnurl='.$parameter['returnurl'].'&'.$return['signkey']);
            $this->setHtml($this->gateway, $parameter);
        } else {
            $url = $this->_site . 'Pay_Swzfbank_topay_orderid_'.$return["orderid"].'.html';
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
            $bank_code = I("post.bank_code");
            if(!$bank_code) {
                $this->showmessage("银行编号不能为空");
            }
            if (!array_key_exists($bank_code, $this->_bank_code)) {
                $this->showmessage("银行编码错误");
            }
            $notifyurl = $this->_site . 'Pay_Swzfbank_notifyurl.html'; //异步通知
            $callbackurl = $this->_site . 'Pay_Swzfbank_callbackurl.html'; //返回通知
            //支付接口字符串-待签名参数数组
            $parameter = array(
                "version" => '1.0',
                "customerid" => $order['memberid'],
                "sdorderno" => $orderid,
                "total_fee" => number_format($order['pay_amount'],2,'.',''),
                "paytype" => 'bank',
                "bankcode" => $bank_code,
                "notifyurl" => $notifyurl,
                "returnurl" => $callbackurl,
                "remark" => ''
            );
            $parameter['sign']=md5('version='.$parameter['version'].'&customerid='.$parameter['customerid'].'&total_fee='.$parameter['total_fee'].'&sdorderno='.$parameter['sdorderno'].'&notifyurl='.$parameter['notifyurl'].'&returnurl='.$parameter['returnurl'].'&'.$order['key']);
            $this->setHtml($this->gateway, $parameter);
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
            $bank_array = [
                "ICBC"=>'102',
                "ABC"=>'103',
                "CCB"=>'105',
                "PSBC"=>'403',
                "CEB"=>'303',
                "CMBC"=>'3006',
                "BCCB"=>'3032',
                "BOS"=>'SHB'
            ];
            $rpay_url = $this->_site . 'Pay_Swzfbank_topay.html';
            $this->assign('order', $order);
            $this->assign('orderid', $orderid);
            $this->assign('rpay_url', $rpay_url);
            $this->assign('bank_array', $bank_array);
            $this->assign('money', round($order['pay_amount'],4));
            $this->display('Swzfbank/bank');
        }
    }

    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'Swzfbank', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {
        file_put_contents('./Data/notify.txt', "【".date('Y-m-d H:i:s')."】\r\nSwzfbank\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $status=$_POST['status'];
        $customerid=$_POST['customerid'];
        $sdorderno=$_POST['sdorderno'];
        $total_fee=$_POST['total_fee'];
        $paytype=$_POST['paytype'];
        $sdpayno=$_POST['sdpayno'];
        $remark=$_POST['remark'];
        $sign=$_POST['sign'];
        if(!$sdorderno) {
            exit('FAIL');
        }
        $userkey = getKey($sdorderno);
        if(!$userkey) {
            exit('FAIL');
        }
        $mysign = md5('customerid='.$customerid.'&status='.$status.'&sdpayno='.$sdpayno.'&sdorderno='.$sdorderno.'&total_fee='.$total_fee.'&paytype='.$paytype.'&'.$userkey);
        if($sign == $mysign){
            if($status=='1'){
                $this->EditMoney($sdorderno, 'Swzfbank', 0);
                echo 'success';
            } else {
                echo 'fail';
            }
        } else {
            echo 'signerr';
        }
    }
}