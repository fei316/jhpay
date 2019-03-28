<?php
namespace Pay\Controller;

use Org\Util\HttpClient;
use Org\Util\Ysenc;

class WftAliSmController extends PayController{
	
	public function __construct(){
		parent::__construct();
	}

	
	public function Pay($array){

		$orderid = I("request.pay_orderid");
		
		$body = I('request.pay_productname');
		$notifyurl = $this->_site . 'Pay_WftAliSm_notifyurl.html';

		$callbackurl = $this->_site . 'Pay_WftAliSm_callbackurl.html';

		$parameter = array(
			'code' => 'WftAliSm',
			'title' => '威富通支付（支付宝扫码）',
			'exchange' => 100, // 金额比例
            'gateway' => '',
            'orderid'=>'',
            'out_trade_id' => $orderid, //外部订单号
            'channel'=>$array,
            'body'=>$body
		);

		//支付金额
		$pay_amount = I("request.pay_amount", 0);
		

		 // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);

      
        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);
        
        //跳转页面，优先取数据库中的跳转页面
        $return["notifyurl"] || $return["notifyurl"] = $notifyurl;
        
        //获取请求的url地址
        $url=$return["gateway"];


	   $arraystr = array(
            'service' => 'pay.alipay.native',
            'mch_id' => $return['mch_id'],
            'out_trade_no' => $return['orderid'],
            'body' => $body,
            'total_fee' => $return['amount'],
            'mch_create_ip' => '127.0.0.1',
            'notify_url' => $return['notifyurl'],
            'nonce_str' => $this->createRandomStr(),
        );    
        $arraystr['sign'] = $this->_createSign($arraystr, $return['signkey']);

        $xmlstr = arrayToXml($arraystr);
        list($return_code, $return_content) = $this->httpPostData($url, $xmlstr);
		
	
	    $respJson = xmlToArray($return_content);
	
        if($respJson['status'] == '0' && $respJson['result_code'] == '0'&&$respJson["code_img_url"]){
            $sign_array = $respJson;
            unset($sign_array['sign']);
            $respSign = $this->_createSign($sign_array,$return['signkey']);
            if(strtoupper($respSign) !=  $respJson['sign']){
                $this->showmessage('验签失败！');
            }else{
                import("Vendor.phpqrcode.phpqrcode",'',".php");
                $url = $respJson['code_url'];
                $QR = "Uploads/codepay/". $return['orderid'] . ".png";//已经生成的原始二维码图
                \QRcode::png($url, $QR, "L", 20);
                $this->assign("imgurl", '/'.$QR);
                $this->assign('params',$return);
                $this->assign('orderid',$return['orderid']);
                $this->assign('money',sprintf('%.2f',$return['amount']/100));
                $this->display("WeiXin/alipay");
            }
        }else{
            $this->showmessage($respJson['err_msg']);
        }
           
        return;
    }


    public function createRandomStr( $length = 32 ) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ ){
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

   
    protected function _createSign($data, $key){
        $sign = '';
        ksort($data);
        foreach( $data as $k => $vo ){
            $sign .= $k . '=' . $vo . '&';
        }
        return md5($sign . 'key=' . $key);
    }




    public function httpPostData($url, $data_string){

        $cacert = ''; //CA根证书  (目前暂不提供)
        $CA = false ;   //HTTPS时是否进行严格认证
        $TIMEOUT = 30;  //超时时间(秒)
        $SSL = substr($url, 0, 8) == "https://" ? true : false;
        
        $ch = curl_init ();
        if ($SSL && $CA) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   //  只信任CA颁布的证书
            curl_setopt($ch, CURLOPT_CAINFO, $cacert);      //  CA根证书（用来验证的网站证书是否是CA颁布）
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    //  检查证书中是否设置域名，并且是否与提供的主机名匹配
        } else if ($SSL && !$CA) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  //  信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);    //  检查证书中是否设置域名
        }


        curl_setopt ( $ch, CURLOPT_TIMEOUT, $TIMEOUT);
        curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, $TIMEOUT-2);
        curl_setopt ( $ch, CURLOPT_POST, 1 );
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data_string );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded') );
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();

        $return_code = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
       
        curl_close($ch);
        return array (
            $return_code,
            $return_content
        );
    }
	

	public function callbackurl(){
		
        $Order = M("Order");
        $pay_status = $Order->where("pay_orderid = '".$_REQUEST["orderid"]."'")->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'WftAliSm', 1);
        }else{
            exit("error");
        }
	}

	 // 服务器点对点返回
    public function notifyurl(){
        // $data = $GLOBALS['HTTP_RAW_POST_DATA'];
        

        $data = $GLOBALS['HTTP_RAW_POST_DATA'];

        $data = xmlToArray($data);
 
        $sign = $data['sign'];
        unset($data['sign']);
		
		$Order = M("Order");
        $signkey = $Order->where("pay_orderid = '".$data['out_trade_no']."'")->getField("key");
  


        $respSign = strtoupper($this->_createSign($data,$signkey));

        if($data['status'] == 0 && $respSign == $sign){
            $this->EditMoney($data["out_trade_no"], 'WftAliSm', 0);
            exit('success');
        }
        
        exit('fail');
    }

}