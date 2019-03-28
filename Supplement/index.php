<?php
class HttpUtils
{

    /**
     * @param $sendInfo
     * @return array
     * 连接远程服务，执行服务
     */
    public static function httpPost($url,$sendArray){
        $ch = curl_init();
        $curl_url = $url;

        curl_setopt($ch, CURLOPT_URL, $curl_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//不直接输出，返回到变量
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sendArray);
        $curl_result = curl_exec($ch);
        curl_close($ch);
        return $curl_result;
    }
}
$merchantId="552323231";
$merReqNo = $_REQUEST['merReqNo'];
$tradeNo  = $_REQUEST['tradeNo'];
$merKey = "PLP092D5N88W556561FF3";
//json报文
$sendArray = Array(
    "merchantId" => $merchantId,
    "merReqNo"=>$merReqNo,
    "tradeNo"=>$tradeNo,
);
$sign = getSign($sendArray,$merKey);
$sendArray["sign"] = $sign;
$sendJson = json_encode($sendArray);
$json = HttpUtils::httpPost("http://123565656/ctp/view/monitorTrans.php",$sendJson);

$rspArray = json_decode($json, true);
$respDesc = $rspArray["respDesc"];
$respCode = $rspArray["respCode"];
$serverRspNo = $rspArray["serverRspNo"];
@file_put_contents( dirname( __FILE__ ).'/log_zfbpost.txt',date("Y-m-d H:i:s")."   ".var_export($sendArray, true), FILE_APPEND );
@file_put_contents( dirname( __FILE__ ).'/log_zfb.txt', date("Y-m-d H:i:s")."   ".$json."\r\n", FILE_APPEND );

function getSign($sendArray,$merKey) {
    
    try {
        if(null == $sendArray){
            return "123456";
        }
        //先干掉sign字段
        $keys = array_keys($sendArray);
        $index = array_search("sign", $keys);
        if ($index !== FALSE) {
            array_splice($sendArray, $index, 1);
        }
        //对数组排序
        ksort($sendArray);
        //生成待签名字符串
        $srcData = "";
        foreach ($sendArray as $key => $val) {
            if($val === null || $val === "" ){
                //值为空的跳过，不参与加密
                continue;
            }
            $srcData .= "$key=$val" . "&";
        }
        $srcData = substr($srcData, 0, strlen($srcData) - 1);
        //            echo "\n";
        //            echo $srcData;
        //            echo "\n";
        //生成签名字符串
        $sign = md5($srcData.$merKey);
        return $sign;
    }catch (Exception $e){
        return null;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf8">
    <title>支付宝补单结果</title>
</head>
<body onLoad="document.pay.submit()">



 <br>
    <style>
        .form-group>span.col-md-4{font-size:0.9em;color:#6B6D6E;line-height: 30px}
    </style>



            <!--补单框-->
<div class="panel panel-default">       
<div class="panel-body">
<div class="form-ajax form-horizontal">
<div class="form-group">
                    <label class="col-md-2 control-label">
                      补单结果:
                    </label>
                    <div class="col-md-4">
                        <input type="text" class="form-control" value="<?php echo $respCode?>"
                        disabled>
						
                    </div>
					<span class="col-md-4">
                        0000表示补单成功，其余表示补单失败。
                    </span>
                </div>

				<div class="form-group">
                    <label class="col-md-2 control-label">
                        代付返回码:
                    </label>
                    <div class="col-md-4">
                        <input style="width:300px;" type="text" class="form-control" value="<?php echo $respDesc?>"
                        disabled>
                    </div>
					<span class="col-md-4">补单返回码说明。如果失败，提示具体原因</span>
                </div>
				<div class="form-group">
                    <label class="col-md-2 control-label">
                        平台单号为:
                    </label>
                    <div class="col-md-4">
                        <input style="width:300px;" type="text" class="form-control" value="<?php echo $merReqNo?>"
                        disabled>
                    </div>
                </div>
				
				<div class="form-group">
                    <label class="col-md-2 control-label">
                        支付宝单号为:
                    </label>
                    <div class="col-md-4">
                        <input style="width:300px;" type="text" class="form-control" value="<?php echo $tradeNo?>"
                        disabled>
                    </div>
                </div>
				
				<div class="form-group">
                    <label class="col-md-2 control-label">
                        服务端响应流水号:
                    </label>
                    <div class="col-md-4">
                        <input style="width:300px;" type="text" class="form-control" value="<?php echo $serverRspNo?>"
                        disabled>
                    </div>
                </div>

            </div></div> </div></div></div>