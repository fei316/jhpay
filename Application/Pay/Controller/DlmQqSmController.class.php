<?php
namespace Pay\Controller;
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
class DlmQqSmController extends PayController
{

    public function Pay($array)
    {

        $orderid = I("request.pay_orderid", '');

        $body = I('request.pay_productname', '');

        $parameter = [
            'code'         => 'DlmQqSm',
            'title'        => '多来米（QQ扫码）',
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
        $return["notifyurl"] || $return["notifyurl"] = $this->_site . 'Pay_DlmQqSm_notifyurl.html';

        $return['callbackurl'] || $return['callbackurl'] = $this->_site . 'Pay_DlmQqSm_callbackurl.html';

        $arraystr = [
            'mch_appid'        => $return['mch_id'],
            'mch_order_number' => $return['orderid'],
            'pay_type'         => 'qq',
            'pay_money'      => $return['amount'],
            'order_desc'     => $body,
            'notify_url' => $return['notifyurl'],
            'return_url' => $return['callbackurl'],
        ];

        $arraystr['sign'] = $this->_createSign($arraystr, $return['signkey']);

        $body = $this->request($return['gateway'], $arraystr);
        $arr = json_decode($body, true);
        if ($arr['retCode'] == '00') {
            $sign = $arr['sign'];
            unset($arr['sign']);
            $mysign = $this->_createSign($arr, $return['signkey']);
            if($sign == $mysign) {
                if($arr['payUrl']) {
                    $return['amount'] /= 100;
                    $this->showQRcode($arr['payUrl'], $return, 'qq');
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
                $this->EditMoney($data['mch_order_number'], 'DlmQqSm', 0);
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
