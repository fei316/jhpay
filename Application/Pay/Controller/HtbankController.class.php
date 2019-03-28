<?php
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
namespace Pay\Controller;

use Think\Exception;

class HtbankController extends PayController
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
        $notifyurl = $this->_site . 'Pay_Htbank_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Htbank_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'Htbank', // 通道名称
            'title' => '汇通网关支付',
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

        $input=array(
            'pay_type'=>'108',
            //接口类型，固定01
            'service_id'=>'01',
            //商户号
            'userid'=>$return['mch_id'],
            //密码(商户密码 进行md532位编码转小写)
            'userpwd'=>md5($return['appsecret']),
            //终端交易时间，YYYYMMDDHHMMSS，全局统一时间格式
            'terminal_time'=>date('YmdHis',time()),
            'total_fee'=>$return['amount']*100,
            'notify_url'=>$notifyurl,
            'attach'=>$return["orderid"],
            'title'=>$return['subject'],
            'body'=>$return['subject'],
        );
        $singto=[
            'pay_type'=>$input['pay_type'],
            //接口类型，固定01
            'service_id'=>$input['service_id'],
            //商户号
            'userid'=>$input['userid'],
            //密码(商户密码 进行md532位编码转小写)
            'userpwd'=>$input['userpwd'],
            //终端交易时间，YYYYMMDDHHMMSS，全局统一时间格式
            'terminal_time'=>$input['terminal_time'],
            'total_fee'=>$input['total_fee'],
        ];
        //签名
        $input['key_sign']=$this->sign($singto, $return['signkey']);
        $url='http://pay.fhtoto.com/api.php/pay/pay';
        $input=json_encode($input);

        $rs=$this->vpost($url,$input);

        $rs=json_decode($rs,true);
        if($rs['result']['code'] == '10000') {
            if($rs['data']['result_code'] == '01') {
                echo $rs['data']['html'];
            } else {
                $this->showmessage($rs['data']['result_msg']);
            }
        } else {
            $this->showmessage($rs['result']['msg']);
        }
    }


    //同步通知
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["orderid"]])->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'Htbank', 1);
        }else{
            exit("error");
        }

    }

    //异步通知
    public function notifyurl()
    {
        file_put_contents('./Data/notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $parameter=json_decode(file_get_contents("php://input"),true);
        if(!empty($parameter)) {
            if ($parameter['return_code'] == '01') {
                if ($parameter['result_code'] == '01') {
                    if ($parameter['attach']) {
                        $signkey = getKey($parameter['attach']);
                        if ($parameter['key_sign'] == $this->verpaysign($parameter, $signkey)) {
                            $this->EditMoney($parameter['attach'], 'Htbank', 0);
                            echo json_encode(['return_code' => '01', 'return_msg' => '成功'], JSON_UNESCAPED_UNICODE );
                        } else {
                            echo json_encode(['return_code' => '02', 'return_msg' => '验签失败'], JSON_UNESCAPED_UNICODE );
                        }
                    }
                }
            } else {
                echo json_encode(['return_code' => '02', 'return_msg' => $parameter['return_msg']], JSON_UNESCAPED_UNICODE );
            }
        }
    }

    //验证签名
    private function verpaysign($date, $signkey){
        ksort($date);
        $buttf="";
        foreach ($date as $key=>$val){
            if ($key=='pay_type' || $key=='service_id' || $key=='userid' || $key=='userpwd' || $key=='terminal_time' || $key=='total_fee')
                $buttf.=$key.'='.$val.'&';
        }
        $buttf=substr($buttf,0,strlen($buttf)-1);
        $buttf=$buttf.$signkey;
        // echo $buttf.'<br>';
        return md5($buttf);
    }

    //签名获取
    private function sign($paydata,$signkey){
        $str = "";
        ksort($paydata);
        foreach($paydata as $k=>$v){
            $str = $str.$k."=".$v.'&';
        }
        $str = substr($str,0,strlen($str)-1);
        //新增验签
        return md5($str.$signkey);
    }


    //curl请求
    private function vpost($url, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}