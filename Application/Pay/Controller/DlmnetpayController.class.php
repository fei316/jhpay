<?php
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

namespace Pay\Controller;


class DlmnetpayController extends PayController
{
    protected $bankItem = [
        'ABC'  => '1103',//农业银行
        '301' => '1301',//交通银行
        'ICBC' => '1102',//工商银行
        'CMB' => '1308',//招商银行
        'CCB'  => '1105',//建设银行
        '305'  => '1305',//民生银行
        '304' => '1304',//华夏银行
        '上海银行' => '1310',//上海银行
        '北京银行'  => '1313',//北京银行
        'yinlian'  => '1100',//银联支付
    ];

    public function Pay($array)
    {

        $orderid = I("request.pay_orderid", '');

        $body = I('request.pay_productname', '');

        $parameter = [
            'code'         => 'Dlmnetpay',
            'title'        => '多来米（网关支付）',
            'exchange'     => 100, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $orderid, //外部订单号
            'channel'      => $array,
            'body'         => $body,
        ];

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);

        //跳转页面，优先取数据库中的跳转页面
        $return["notifyurl"] || $return["notifyurl"] = $this->_site . 'Pay_Dlmnetpay_notifyurl.html';

        $return['callbackurl'] || $return['callbackurl'] = $this->_site . 'Pay_Dlmnetpay_callbackurl.html';

        $arraystr = [
            'mch_appid'        => $return['mch_id'],
            'mch_order_number' => $return['orderid'],
            'pay_type'         => 'net_pay',
            'pay_money'      => $return['amount'],
            'order_desc'     => $body,
            'notify_url' => $return['notifyurl'],
            'return_url' => $return['callbackurl'],
            'gateway' => $return['gateway'],
            'signkey' => $return['signkey']
        ];

        $encryp = encryptDecrypt(serialize($arraystr), 'lgbya');
        $rpay_url = $this->_site . 'Pay_Dlmnetpay_repay.html';
        $this->assign([
            'bank_array' => $this->bankItem,
            'rpay_url'   => $rpay_url,
            'orderid'    => $return['orderid'],
            'body'       => $body,
            'money'      => $return['amount']/100,
            'encryp'     => $encryp,
        ]);
        $this->display('BankPay/pc');exit();


    }

    public function repay(){
        $data = I('request.');
        $bank_code = $data['bankCode'];
        $arraystr = unserialize(encryptDecrypt($data['encryp'], 'lgbya', 1));
        $arraystr['bank_code'] = $bank_code;
        $gateway = $arraystr['gateway'];
        $signkey = $arraystr['signkey'];
        unset($arraystr['gateway']);
        unset($arraystr['signkey']);
        $arraystr['sign'] = $this->_createSign($arraystr, $signkey);

        $body = $this->request($gateway, $arraystr);
        $arr = json_decode($body, true);
        if ($arr['retCode'] == '00') {
            $sign = $arr['sign'];
            unset($arr['sign']);
            $mysign = $this->_createSign($arr, $signkey);
            if($sign == $mysign) {
                if($arr['payUrl']) {
                    exit($arr['payUrl']);
                } else {
                    $this->showmessage($arr['retMsg']);
                }
            }
        } else {
            $this->showmessage($arr['retMsg']);
        }
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
        if(!$data['mch_order_number']) {
            exit('FAIL');
        }
        $key = getKey($data['mch_order_number']);
        if(!$key) {
            exit('FAIL');
        }
        $sign = $data['sign'];
        unset($data['sign']);
        $mysign = $this->_createSign($data, $key);
        if($mysign == $sign) {
            if($data['order_status'] == 3) {
                $this->EditMoney($data['mch_order_number'], 'Dlmnetpay', 0);
                exit('success');
            } else {
                exit($data['order_status']);
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

    protected function _createSign($data, $key)
    {
        $sign          = '';
        ksort($data);
        foreach( $data as $k => $vo ){
            if($vo !== '')
                $sign .=  $k . '=' . $vo . '&' ;
        }
        $sign = trim($sign,'&').$key;
        return strtoupper(md5($sign));
    }
}