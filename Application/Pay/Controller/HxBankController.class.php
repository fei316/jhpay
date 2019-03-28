<?php
/**
 * Created by PhpStorm.
 * User: mapeijian
 * Date: 2018-04-16
 * Time: 17:37
 */
namespace Pay\Controller;
use Org\Util\Hx\IpsPaySubmit;

class HxBankController extends PayController
{

    private $gateway = 'https://newpay.ips.com.cn/psfp-entry/gateway/payment.do';

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $selPayType=I("request.selPayType",'');//支付方式 01:借记卡 02：信用卡
        $bankCode=I("request.bankCode",'');//银行号
        $return  = $this->getParameter('环迅网银支付', $array, __CLASS__, 1);
        $return['selPayType'] = $selPayType;
        $return['bankCode'] = $bankCode;
        $encryp  = encryptDecrypt(serialize($return), 'HxBank');
        if($return['unlockdomain']) {
            if($selPayType && $bankCode) {
                echo createForm($return['unlockdomain'].'/Pay_HxBank_Rpay', ['encryp' => $encryp, 'selPayType' => $selPayType, 'bankCode' => $bankCode]);
            } else {
                echo createForm($return['unlockdomain'].'/Pay_HxBank_Gopay', ['encryp' => $encryp]);
            }

        } else {
            if($selPayType && $bankCode) {
                echo createForm($this->_site .'Pay_HxBank_Rpay', ['encryp' => $encryp, 'selPayType' => $selPayType, 'bankCode' => $bankCode]);
            } else {
                echo createForm($this->_site .'Pay_HxBank_Gopay', ['encryp' => $encryp]);
            }
        }
    }

    public function Gopay(){
        //接收传输的数据
        $postData = I('post.', '');
        $encryp = $postData['encryp'];
        //将数据解密并反序列化
        $data = unserialize(encryptDecrypt($encryp, 'HxBank', 1));
        if($data['unlockdomain']) {
            $rpay_url = $data['unlockdomain'].'/Pay_HxBank_Rpay';
        } else {
            $rpay_url = $this->_site .'Pay_HxBank_Rpay';
        }
        $this->assign([
            'rpay_url'   => $rpay_url,
            'encryp'     => $encryp,
        ]);
        $this->display('HxBank/bank');
        return;
    }

    public function Rpay() {
        //接收传输的数据
        $postData = I('post.', '');

        //将数据解密并反序列化
        $data = unserialize(encryptDecrypt($postData['encryp'], 'HxBank', 1));

        //检测数据是否正确
        $data || $this->error('传输数据不正确！');
        $selPayType = I("request.selPayType");//支付方式 01:借记卡 02：信用卡
        $bankCode = I("request.bankCode");//银行号
        if(!$selPayType) {
            $this->showmessage("请选择支付方式");
        }
        if(!$bankCode) {
            $this->showmessage("银行号不能为空");
        }
        $notifyurl = $this->_site . 'Pay_HxBank_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_HxBank_callbackurl.html'; //返回通知
        $parameter  = [
            "Version"       => 'v1.0.0',
            "MerCode"       => $data['mch_id'],
            "Account"       => $data['appid'],
            "MerCert"       => $data['signkey'],
            "PostUrl"       => $this->gateway,
            "S2Snotify_url" => $notifyurl,
            "Return_url"  => $callbackurl,
            "CurrencyType"	=> "156",
            "Lang"	=> "GB",
            "OrderEncodeType"=>"5",
            "RetType"=>"1",
            "MerBillNo"	=> $data['orderid'],
            "MsgId"	=> $data['orderid'],
            "PayType"	=> $selPayType,
            "FailUrl"   => $callbackurl,
            "Date"	=> date('Ymd'),
            "ReqDate"	=> date("YmdHis"),
            "Amount"	=> $data['amount'],
            "Attach"	=> '',
            "RetEncodeType"	=> "17",
            "BillEXP"	=> 1,
            "GoodsName"	=> "商品",
            "BankCode"	=> $bankCode,
            "IsCredit"	=> 1,
            "ProductType"	=> 1
        ];
        $ipspay_config['PostUrl'] = $this->gateway;
        $ipspay_config['MerCert'] = $data['signkey'];
        $ipspaySubmit = new IpsPaySubmit($ipspay_config);
        $html_text = $ipspaySubmit->buildRequestForm($parameter);
        echo $html_text;
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        file_put_contents('./Data/callback.txt', "【" . date('Y-m-d H:i:s') . "】\r\n" . file_get_contents("php://input") . "\r\n\r\n", FILE_APPEND);
        $paymentResult = $_REQUEST['paymentResult'];
        $xmlResult = new \SimpleXMLElement($paymentResult);
        $strSignature = $xmlResult->GateWayRsp->head->Signature;
        $retEncodeType = $xmlResult->GateWayRsp->body->RetEncodeType;
        $strBody = $this->subStrXml("<body>", "</body>", $paymentResult);
        $rspCode = $xmlResult->GateWayRsp->head->RspCode;
        if ($rspCode == "000000") {
            libxml_disable_entity_loader(true);
            $arraystr = json_decode(json_encode(simplexml_load_string($strBody, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            $order = M('Order')->where(['pay_orderid' => $arraystr['MerBillNo']])->find();
            if ($order && $order['pay_amount'] == $arraystr['Amount']) {
                if ($this->md5Verify($strBody, $strSignature, $order["memberid"], $order['key'])) {
                    if($order['pay_status'] == 0) {
                        sleep(5);//等待5秒
                        $order = M('Order')->where(['pay_orderid' => $arraystr['MerBillNo']])->find();
                    }
                    if ($order['pay_status'] <> 0) {
                        $this->EditMoney($arraystr['MerBillNo'], '', 1);
                    } else {
                        exit('订单异常请联系客服，订单号：'.$order['out_trade_id']);
                    }
                }
            }
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {
        file_put_contents('./Data/notify.txt', "【" . date('Y-m-d H:i:s') . "】\r\n" . file_get_contents("php://input") . "\r\n\r\n", FILE_APPEND);
        $paymentResult = $_REQUEST['paymentResult'];
        $xmlResult = new \SimpleXMLElement($paymentResult);
        $strSignature = $xmlResult->GateWayRsp->head->Signature;

        $retEncodeType = $xmlResult->GateWayRsp->body->RetEncodeType;
        $strBody = $this->subStrXml("<body>", "</body>", $paymentResult);
        $rspCode = $xmlResult->GateWayRsp->head->RspCode;
        if ($rspCode == "000000") {
            libxml_disable_entity_loader(true);
            $arraystr = json_decode(json_encode(simplexml_load_string($strBody, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            $order = M('Order')->where(['pay_orderid' => $arraystr['MerBillNo']])->find();
            if ($order && $order['pay_amount'] == $arraystr['Amount']) {
                if ($this->md5Verify($strBody, $strSignature, $order["memberid"], $order['key'])) {
                    $this->EditMoney($arraystr['MerBillNo'], 'HxBank', 0);
                    exit('ok');
                }
            }

        }
    }

    private function subStrXml($begin,$end,$str){
        $b= (strpos($str,$begin));
        $c= (strpos($str,$end));

        return substr($str,$b,$c-$b + 7);
    }

    /**
     * 验证签名
     *
     * @param $prestr 需要签名的字符串
     * @param $sign 签名结果
     * @param $merCode 商戶號
     * @param $key 私钥
     *            return 签名结果
     */
    function md5Verify($prestr, $sign, $merCode, $key)
    {
        $prestr = $prestr . $merCode . $key;
        $mysgin = md5($prestr);

        if ($mysgin == $sign) {
            return true;
        } else {
            return false;
        }
    }
}
