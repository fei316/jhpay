<?php

namespace Pay\Controller;

/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

class KxBankController extends PayController
{
    /**
     * @var string 支付网关
     */
    private $gateway = 'http://b2c.1xiangpay.com/agent/b2cTransReq.action';

    public function __construct()
    {
        parent::__construct();
    }

    //直连参数
    protected $_bank_code = array(
        "BCCB"=>'北京银行',
        "BEA"=>'东亚银行',
        "ICBC"=>'工商银行',
        "CEB"=>'光大银行',
        "CGB"=>'广发银行',
        "CCB"=>'建设银行',
        "BOCOM"=>'交通银行',
        "CMBC"=>'民生银行',
        "NJCB"=>'南京银行',
        "NBCB"=>'宁波银行',
        "ABC"=>'农业银行',
        "PINGAN"=>'平安银行',
        "SPDB"=>'浦发银行',
        "SRCB"=>'上海农商行',
        "CIB"=>'兴业银行',
        "POST"=>'邮政储蓄银行',
        "CMB"=>'招商银行',
        "BOC"=>'中国银行',
        "CITIC"=>'中信银行',
    );

    //支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_KxBank_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_KxBank_callbackurl.html'; //返回通知
        //$card_type = I("request.card_type");//银行卡类型 1：借记卡 2：贷记卡
        $bank_code = I("request.bank_code",'');//支付银行码
        if($bank_code) {
            if (!array_key_exists($bank_code, $this->_bank_code)) {
                $bank_code = '';
            }
        }
        $return  = $this->getParameter('开心支付网银', $array, __CLASS__, 100);
        if($bank_code) {
            /*
            if(!$card_type) {
                $this->showmessage('银行卡类型不能为空！');
            }
            if(!in_array($card_type, [1,2])) {
                $this->showmessage('银行卡类型错误！');
            }
            */
            //支付接口字符串-待签名参数数组
            $parameter = array(
                "transCode" => "003",
                "service"   => "0012",
                "customerNo" => $return['mch_id'],
                "externalId" => $return['orderid'],
                "transAmount" => $return['amount'],
                "description" => $body,
                "reqDate" => date('Ymd'),
                "reqTime" => date('His'),
                "callbackUrl" => $callbackurl,
                "bgReturnUrl" => $notifyurl,
                "bankCode" => $bank_code,
                "cardType" => '1',
                "requestIp" => $return['appid'],
                "terminalType" => isMobile() ? '2' : '1'
            );
            $parameter['sign'] = strtoupper(md5Sign($parameter, $return['signkey'], '&key='));
            $resultJson = $this->request($this->gateway, json_encode($parameter));
            $result = json_decode($resultJson, true);
            $sign = $result['sign'];
            unset($result['sign']);
            $mysign = strtoupper(md5Sign($result, $return['signkey'], '&key='));

            if($result['code'] == '10') {
                if($sign == $mysign) {
                    echo $result['payUrl'];
                } else {
                    $this->showmessage('验签失败');
                }
            } else {
                $this->showmessage($result['message']);
            }
        } else {
            $encryp  = encryptDecrypt(serialize($return), 'KxBank');
            echo createForm($this->_site .'Pay_KxBank_Gopay', ['encryp' => $encryp]);
        }
    }

    public function Gopay(){
        //接收传输的数据
        $postData = I('post.', '');
        $encryp = $postData['encryp'];
        //将数据解密并反序列化
        $data = unserialize(encryptDecrypt($encryp, 'KxBank', 1));
        if($data['unlockdomain']) {
            $rpay_url = $data['unlockdomain'].'/Pay_KxBank_Rpay';
        } else {
            $rpay_url = $this->_site .'Pay_KxBank_Rpay';
        }
        $bank_array = [
            "ICBC"=>'102',
            "ABC"=>'103',
            "BOC"=>'104',
            "CCB"=>'105',
            "POST"=>'中国邮政储蓄',
            "CITIC"=>'302',
            "BCCB"=>'370',
            "BEA"=>'HKBEA',
            "CEB"=>'303',
            "CGB"=>'GDB',
            "BOCOM"=>'301',
            "CMBC"=>'3006',
            "NJCB"=>'NJCB',
            "NBCB"=>'NBCB',
            "PINGAN"=>'3035',
            "SPDB"=>'3004',
            "SRCB"=>'上海农村商业银行',
            "CIB"=>'兴业银行',
            "CMB"=>'3001',
        ];
        $this->assign([
            'bank_array' => $bank_array,
            'rpay_url'   => $rpay_url,
            'orderid'    => $data['orderid'],
            'body'       => $data['subject'],
            'money'      => $data['amount']/100,
            'encryp'     => $encryp,
        ]);
        $this->display('KxBank/bank');
    }

    public function Rpay()
    {
        if(IS_POST) {
            //接收传输的数据
            $postData = I('post.', '');

            //将数据解密并反序列化
            $data = unserialize(encryptDecrypt($postData['encryp'], 'KxBank', 1));

            //检测数据是否正确
            $data || $this->error('传输数据不正确！');
            $orderid = $data['orderid'];
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
            /*
            $card_type = I("post.card_type");//银行卡类型 1：借记卡 2：贷记卡
            if(!$card_type) {
                $this->showmessage('银行卡类型不能为空！');
            }
            if(!in_array($card_type, [1,2])) {
                $this->showmessage('银行卡类型错误！');
            }
            */
            $notifyurl = $this->_site . 'Pay_KxBank_notifyurl.html'; //异步通知
            $callbackurl = $this->_site . 'Pay_KxBank_callbackurl.html'; //返回通知
            //支付接口字符串-待签名参数数组
            $parameter = array(
                "transCode" => "003",
                "service"   => "0012",
                "customerNo" => $data['mch_id'],
                "externalId" => $orderid,
                "transAmount" => (string)$data['amount'],
                "description" => $data['subject'],
                "reqDate" => date('Ymd'),
                "reqTime" => date('His'),
                "callbackUrl" => $callbackurl,
                "bgReturnUrl" => $notifyurl,
                "bankCode" => $bank_code,
                "cardType" => '1',
                "requestIp" => $data['appid'],
                "terminalType" => isMobile() ? '2' : '1'
            );
            $parameter['sign']=strtoupper(md5Sign($parameter, $data['signkey'], '&key='));
            $resultJson = $this->request($this->gateway, json_encode($parameter));
            $result = json_decode($resultJson, true);

            if($result['code'] == '10') {
                $sign = $result['sign'];
                unset($result['sign']);
                $mysign = strtoupper(md5Sign($result, $data['signkey'], '&key='));
                if($sign == $mysign) {
                    echo $result['payUrl'];
                } else {
                    $this->showmessage('验签失败');
                }
            } else {
                $this->showmessage($result['message']);
            }

        }
    }

    //同步通知
    public function callbackurl()
    {
        $json = array_keys($_GET,"")[0];
        if(!$json) {
            exit('error');
        }
        $data = json_decode($json, true);

        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $data["externalId"]])->getField("pay_status");
        if($pay_status == 0) {
            sleep(3);//等待3秒
            $pay_status = M('Order')->where(['pay_orderid' => $data["externalId"]])->getField("pay_status");
        }
        if ($pay_status <> 0) {
            $this->EditMoney($data["externalId"], 'KxBank', 1);
        } else {
            exit('页面已过期请刷新');
        }
    }

    //异步通知
    public function notifyurl()
    {
        file_put_contents('./Data/notify.txt', "【".date('Y-m-d H:i:s')."】\r\nKxBank\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $json = file_get_contents("php://input");
        if(!$json) {
            exit('FAIL');
        }
        $data = json_decode($json, true);
        $key = getKey($data['externalId']);
        $sign = $data['sign'];
        unset($data['sign']);
        $mysign = strtoupper(md5Sign($data, $key, '&key='));
        if($sign == $mysign) {
            if ($data['code'] == '00') {
                $this->EditMoney($data['externalId'], 'KxBank', 0);
                echo 'SUCCESS';
            } else {
                exit('FAIL');
            }
        } else {
            exit('check sign fail');
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