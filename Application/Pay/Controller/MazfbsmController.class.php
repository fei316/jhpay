<?php
namespace Pay\Controller;

use Org\Util\HttpClient;
use Org\Util\Ysenc;

class MazfbsmController extends PayController{
	
	public function __construct(){
		parent::__construct();
	}

	
	public function Pay($array){
         //190143
	    //fWjXjuqEzPZhkpGDKy2HY4JfX0sgDrtt

		$orderid = I("request.pay_orderid");
		$body = I('request.pay_productname');
		$notifyurl = $this->_site . 'Pay_Mazfbsm_notifyurl.html';
		$callbackurl = $this->_site . 'Pay_Mazfbsm_callbackurl.html';
		$parameter = array(
			'code' => 'Mazfbsm',
			'title' => '码支付支付宝扫码',
			'exchange' => 1, // 金额比例
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

        $url=$return["gateway"];
        $total_fee=sprintf("%.2f", $return['amount']);
        $codepay_id=$return['mch_id'];//这里改成码支付ID
        $codepay_key=$return['signkey']; //这是您的通讯密钥



        $paytype=1;
        $data = array(
            "id" => $codepay_id,//你的码支付ID
            "pay_id" => $return['orderid'], //唯一标识 可以是用户ID,用户名,session_id(),订单ID,ip 付款后返回
            "type" => $paytype,//1支付宝支付 3微信支付 2QQ钱包
            "price" => $total_fee,//金额100元
            "param" => "1",//自定义参数
            "notify_url"=>$notifyurl,//通知地址
            "return_url"=>$callbackurl,//跳转地址
        ); //构造需要传递的参数

        ksort($data); //重新排序$data数组
        reset($data); //内部指针指向数组中的第一个元素
        $sign = ''; //初始化需要签名的字符为空
        $urls = ''; //初始化URL参数为空
        foreach ($data as $key => $val) { //遍历需要传递的参数
            if ($val == ''||$key == 'sign') continue; //跳过这些不参数签名
            if ($sign != '') { //后面追加&拼接URL
                $sign .= "&";
                $urls .= "&";
            }
            $sign .= "$key=$val"; //拼接为url参数形式
            $urls .= "$key=" . urlencode($val); //拼接为url参数形式并URL编码参数值
        }
        $query = $urls . '&sign=' . md5($sign .$codepay_key); //创建订单所需的参数
        $url = "http://api2.fateqq.com:52888/creat_order/?{$query}"; //支付页面
        header("Location:{$url}"); //跳转到支付页面

        return;
    }


	public function callbackurl(){
		
        $Order = M("Order");
        $pay_status = $Order->where("pay_orderid = '".$_REQUEST["pay_id"]."'")->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'Mazfbsm', 1);
        }else{
            exit("error");
        }
	}

	 // 服务器点对点返回
    public function notifyurl(){
        $this->writelog('收到一次回调消息','Mazfbsm');
        ksort($_POST); //排序post参数
        reset($_POST); //内部指针指向数组中的第一个元素
        $this->writelog('POST='.json_encode($_POST),'Mazfbsm');

        $Order = M("Order");
        $codepay_key = $Order->where("pay_orderid = '".$_POST['pay_id']."'")->getField("key");
        $this->writelog('codepay_key='.$codepay_key,'Mazfbsm');

        $sign = '';//初始化
        foreach ($_POST AS $key => $val) { //遍历POST参数
            if ($val == '' || $key == 'sign') continue; //跳过这些不签名
            if ($sign) $sign .= '&'; //第一个字符串签名不加& 其他加&连接起来参数
            $sign .= "$key=$val"; //拼接为url参数形式
        }

        $this->writelog('sign='.$sign,'Mazfbsm');
        $localsign=md5($sign . $codepay_key);
        $recvsign=$_POST['sign'];

        $this->writelog('recvsign='.$recvsign.'  localsign ='.$localsign,'Mazfbsm');

        if ( $localsign != $recvsign) { //不合法的数据
            $this->writelog('----签名失败---','Mazfbsm');
            exit('fail');  //返回失败 继续补单
        } else
        { //合法的数据
            //业务处理
            $this->writelog('----签名成功---','Mazfbsm');
            $pay_id = $_POST['pay_id']; //需要充值的ID 或订单号 或用户名
            $money = (float)$_POST['money']; //实际付款金额
            $price = (float)$_POST['price']; //订单的原价
            $param = $_POST['param']; //自定义参数
            $pay_no = $_POST['pay_no']; //流水号

            $this->EditMoney($pay_id, 'Mazfbsm', 0);
                exit('success');
        }
    }

    public function writelog($text, $aType='')
    {
        if (! empty ( $text ))
        {
            $fileType = mb_detect_encoding ( $text, array (
                'UTF-8',
                'GBK',
                'GB2312',
                'LATIN1',
                'BIG5'
            ) );

            if ($fileType != 'UTF-8')
            {
                $text = mb_convert_encoding ( $text, 'UTF-8', $fileType );
            }
            file_put_contents (dirname ( __FILE__ )."/log_".$aType. date( "Y-m-d" ).".txt", date ( "Y-m-d H:i:s" ) . "  " . $text . "\r\n", FILE_APPEND );
        }

    }

}