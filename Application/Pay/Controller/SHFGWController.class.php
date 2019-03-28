<?php
namespace Pay\Controller;
use Think\Log;

/**
 * 速汇付-网关支付渠道
 * 官网：http://www.babaodao.com/
 * demo地址：
 * 2018-05-08
 */
class SHFGWController extends PayController
{

    /**
     * @var string 支付网关
     */
    private $gateway = 'http://www.babaodao.com/api/pay/cardPay';

    //自己生成的私钥
    private $private_key = '-----BEGIN RSA PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAJqFI/vUq1RFlxYepyKJB7+3BxSokntp5bSAsZNqoulCj0qsJd3EA1/1KqxBLKcUz2zXc1o4t5T2UUqVUwYXwIcSQrqlQyUTTA8T4xdDDuJhxf3DYDBTuL/sncNrsftfzLTmP0MQgJBKW0HJM30WJ4FmICDnjt97SHrd+WDUJK8ZAgMBAAECgYBsa9l43ZuuEPpXggCiQeZXBjUIsNO/lumfwuFW3+8ZnyNuMOaM+fmoPh3IKh8LyZVV+MMu3jcqZb9ahvZvgwEKaPgR/lBT7g9Is0o3qsh93yFM8rVzq+4Qu4tEPqTwXbpaEEGdPxNkzNdKocY1q4RqSa3cfpU2JW+jO+NOXAZxtQJBAPLkm/gUeMM7iCv4KtjRHHiKymRmDh5WK08GOp9OBuaUrrhHVDp1Z6k1Ac3FkHB/G8znU6xYxp1Td1FoCtNhgh8CQQCi27kyz8PU0Ji0wz3u3SR4Hov1inOGKJt58jvP+X8d4z61mv/q4J8n540m6KcZhLaZK8NCHbcM92BJkMLb4FfHAkEAmynjYSViyAVdxgjxBjT/pRm0lVKErmiJnh/yjxX/XomY2+vlKLsbj4JnNpaA4PyyO8GDOFQ1/Qb28DAwyjw+LQJAb+qJGab3j88NseMeM4EbJ8TuL33Gp+JN/f5+Jgzx0yswFAMBbXqRRQ31zVBCTOILzbTqSQw8mBeDvupRTmKcTwJASlvOUneL/HvJnyEHSSVBANwTq4iQRXpIhwuRaKiG/VSY+jbeKtX1xR4YTh2trW/ziBOxUzyEbovbpnvWGDhdoA==
-----END RSA PRIVATE KEY-----';

    //自己生成的公钥
    private $public_key = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCahSP71KtURZcWHqciiQe/twcUqJJ7aeW0gLGTaqLpQo9KrCXdxANf9SqsQSynFM9s13NaOLeU9lFKlVMGF8CHEkK6pUMlE0wPE+MXQw7iYcX9w2AwU7i/7J3Da7H7X8y05j9DEICQSltByTN9FieBZiAg547fe0h63flg1CSvGQIDAQAB
-----END PUBLIC KEY-----';

    /*由ECPAY提供的公钥*/
    private $public_ecpay_key = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCZCVsGjoRi7XKoi0biZLfg5cgBVrzIeFeFAHAnga/bMSFdBMLjZ3f5SAh6FgaovdBEpw/DcJ1NdBG4ycsIBguvQbkThq9pbWjHASDshCnPGNSdMEl4/9rHsHzKi5Ad+taGkPzrFjHswdodPKs2YvFLkMYCxlDv5WDKtKKxzJ9kVQIDAQAB
-----END PUBLIC KEY-----';


    /**
     * @param array $channel
     */
    public function Pay($channel)
    {

        $return = $this->getParameter('速汇付', $channel, SHFGWController::class, 100);

        $businessHead = [
            'charset'        => '00', //00表示UTF-8，暂时只支持UTF-8
            'version'        => 'V1.0.0',
            'merchantNumber' => $return["mch_id"], //商户ID
            'tradeType'      => 'rpmbankPayment',
            'requestTime'    => date('YmdHis'),
            'signType'       => 'RSA', //暂时只支持RSA，必须大写的RSA
        ];
        $businessContext = [
            'orderNumber'   => $return['orderid'], //商户订单号
            'amount'        => $return['amount'],
            'currency'      => 'CNY',
            'commodityName' => '商品名称',
            'commodityDesc' => '商品描述',
            'payType'       => 'UNION_B2C_SAVINGS', //B2C-储蓄卡
            'cardType'      => 'SAVINGS', //暂时不支持信用卡
            'bankNumber'    => I('request.bank_number', '1002'), //银行代码，由速汇付定义 //TODO: 默认参数用于测试
            'returnUrl'     => $return["callbackurl"],
            'notifyUrl'     => $return["notifyurl"],
        ];

        ksort($businessContext);

        $json_businessContext = json_encode($businessContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);/*订单数组转化为JSON格式*/

        $pi_key = openssl_pkey_get_private($this->private_key);//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        //---------------------------------------------------------------------------------------------
        openssl_sign($json_businessContext, $sign, $pi_key, OPENSSL_ALGO_MD5);//根据提供的私钥进行订单签名

        $sign = base64_encode($sign);//最终的签名

        /*echo "<br></br>".$sign;　*/
        /*var_dump($sign);*/
        $businessHead['sign'] = $sign;//将签名加入businessHead中

        $arr_order['businessHead'] = $businessHead;

        $arr_order['businessContext'] = $businessContext;

        /*  组装arr_order格式*/

        //ksort($arr_order);

        $json_order = json_encode($arr_order, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $cryptos = '';
        $pu_ecpay_key = openssl_pkey_get_public($this->public_ecpay_key);//这个函数可用来判断ECPAY提供的私钥是否是可用的，可用返回资源id Resource id
        foreach (str_split($json_order, 117) as $value) {

            openssl_public_encrypt($value, $encryptDatas, $pu_ecpay_key);

            $cryptos .= $encryptDatas;
        }
        $cryptos = base64_encode($cryptos);

        header("Location:" . $this->gateway . "?context=" . $cryptos);/* Redirect browser */

        exit;

    }

    protected function http_post_data($url, $data_string)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json; charset=utf-8",
                "Content-Length: " . strlen($data_string)]
        );
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return [$return_code, $return_content];
    }

    //同步通知地址
    public function callbackurl()
    {
        Log::record('速汇付同步通知：开始：' . PHP_EOL . var_export(func_get_args(), true), Log::INFO);

        $notify_data = json_decode(file_get_contents('php://input'), true);//取POST过来的JSON数据，普通的$_POST无法取值

        $notify = $notify_data['context'];//提取密文

        $mer_private_key = openssl_pkey_get_private($this->private_key);//取私钥资源号

        $ec_public_key = openssl_pkey_get_public($this->public_ecpay_key);//取PAY公钥资源号

        $data = $this->rsa_decrypt($notify, $mer_private_key);//执行解密流程

        $context_arr = json_decode($data, true);//转为数组格式

        $sign = $context_arr['businessHead']['sign'];//取SIGN

        $businessContext = $context_arr['businessContext'];//取businessContext

        ksort($businessContext);//按ASCII码从小到大排序

        $json_businessContext = json_encode($businessContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $isVerify = (boolean)openssl_verify($json_businessContext, base64_decode($sign), $ec_public_key, OPENSSL_ALGO_MD5);

        if ($isVerify) {
            /**
             * 验签成功，执行商户自己的逻辑
             */
            $orderNumber = $context_arr['businessContext']['orderNumber'];
            Log::record('同步支付通知：参数验证成功，订单号：' . $orderNumber, Log::INFO);
            if ($context_arr['businessContext']['orderStatus'] == 'SUC') {
                $this->EditMoney($orderNumber, 'SHFGW', 1);
                Log::record('同步支付通知：订单修改成功，订单号：' . $orderNumber, Log::INFO);
            }
            echo '支付成功';
        } else {
            Log::record("同步支付通知：参数验证失败：\n" . var_export($context_arr, true) . "\nsign:" . $sign, Log::ERR);
            echo '支付失败';
        }
    }

    //异步通知地址
    public function notifyurl()
    {
        Log::record('速汇付异步通知：开始：' . PHP_EOL . var_export(func_get_args(), true), Log::INFO);

        $notify_data = json_decode(file_get_contents('php://input'), true);//取POST过来的JSON数据，普通的$_POST无法取值

        $notify = $notify_data['context'];//提取密文

        $mer_private_key = openssl_pkey_get_private($this->private_key);//取私钥资源号

        $ec_public_key = openssl_pkey_get_public($this->public_ecpay_key);//取PAY公钥资源号

        $data = $this->rsa_decrypt($notify, $mer_private_key);//执行解密流程

        $context_arr = json_decode($data, true);//转为数组格式

        $sign = $context_arr['businessHead']['sign'];//取SIGN

        $businessContext = $context_arr['businessContext'];//取businessContext

        ksort($businessContext);//按ASCII码从小到大排序

        $json_businessContext = json_encode($businessContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $isVerify = (boolean)openssl_verify($json_businessContext, base64_decode($sign), $ec_public_key, OPENSSL_ALGO_MD5);

        if ($isVerify) {
            /**
             * 验签成功，执行商户自己的逻辑
             */
            $orderNumber = $context_arr['businessContext']['orderNumber'];
            Log::record('异步支付通知：参数验证成功，订单号：' . $orderNumber, Log::INFO);
            if ($context_arr['businessContext']['orderStatus'] == 'SUC') {
                $this->EditMoney($orderNumber, 'SHFGW', 0);
            }
            Log::record('异步支付通知：订单修改成功，订单号：' . $orderNumber, Log::INFO);

            echo 'SUC';  //成功返回SUC，系统则不会继续推送notify
        } else {
            Log::record("异步支付通知：参数验证失败：\n" . var_export($context_arr, true) . "\nsign:" . $sign, Log::ERR);
            echo 'FAIL';
        }
    }


    /**
     * RSA解密
     * @param $encrypted
     * @param $rsa_private_key
     * @return string
     */
    protected function rsa_decrypt($encrypted, $rsa_private_key){
        $crypto = '';
        $encrypted = base64_decode($encrypted);
        foreach (str_split($encrypted, 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $rsa_private_key);
            $crypto .= $decryptData;
        }
        return $crypto;
    }
}

?>
