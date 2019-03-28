<?php
namespace Pay\Controller;
use Think\Log;
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
/**
 * 速汇付-支付渠道
 * 官网：http://www.babaodao.com/
 * demo地址：
 * 测试：未完成（上游暂未开通快捷支付渠道）
 * 2018-05-08
 */
class BaBaoDaoController extends PayController
{

    /**
     * @var string 支付网关
     */
    private $gateway = 'http://www.babaodap.com/api/pay/quickPayInit';

    //自己生成的私钥
    private $private_key = '-----BEGIN RSA PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAKgFVZTuOARaWOOcMv7NmWa8QJ59ufvbuwXVbpSE95KKFCMGDAMPFkXqfnslcS0GgyKbgtHznrTb1/nfQrwb+jUecB+E8y0B4XlLSHFP05RhEcjc3UcAcMpPb71zbSMuEGOkk9oOQbjbXrzZHBkZDaDIgx+S4e+GcQtI1UqXNHGdAgMBAAECgYAaij0qeTZ/+YVw7otflDpW8AWFA3cNQXgZQ81jyF0S2Jy1q47aLNfn01KHQTLPWef833OallDLYK6M2adA72pmF67EYaxLi9tv1Ebigl2AANvPqJHguryiyjLZ51soRpcak/gnEX7HcvnTd6imYFGz/3f2mdvXk8QJKAFyOkKAQQJBAOdpaJtgfWHljI0TEFLv227FOWz3DuYC8QK+m4owSmp0hBVkJxa4UNsdSBPUTWbHu57YOsCzYTNSAGl9vE5VuRsCQQC536Pow9Fzfv++Ct/FGXmt84Vv3H44kLqp7lw32fAQx8OWovZjaSK3Bld0Q33sPLAdGx1ba3Q4UvOgKvzIjiOnAkBH4JHBUSMguTAC0Z0MZbv+l/vSQJq8DsXVWGbvcThsAGzWSBlsESvsIxg0MIkqF3fLStZU7GKQkjPWkFtg6XdpAkEAsVNNcVPXb4M6gtim8MfEERMhOz204UwZ+OJg8hul7qxVyVFBFEgKCWgwaMe2y3h+X9YtZLkX0GA68pPwQ3lvQwJAY2i+/kt3AnqJ+j2aU/IdW2TGgvvSMpR7UlSuUoiBVpFeamJeBS9BT+dtJkrLVDMYZsLMkQBy2SuvNDxUXMFydg==
-----END RSA PRIVATE KEY-----';

    //自己生成的公钥
    private $public_key = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCZCVsGjoRi7XKoi0biZLfg5cgBVrzIeFeFAHAnga/bMSFdBMLjZ3f5SAh6FgaovdBEpw/DcJ1NdBG4ycsIBguvQbkThq9pbWjHASDshCnPGNSdMEl4/9rHsHzKi5Ad+taGkPzrFjHswdodPKs2YvFLkMYCxlDv5WDKtKKxzJ9kVQIDAQAB
-----END PUBLIC KEY-----';

    /*由ECPAY提供的公钥*/
    private $public_ecpay_key = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOAoslcPOFmqk/Okv5sT3z+TsnwjCXtev4OPTM9oLQpr7DwHNYlXIxGkI0rf0RWW6zKMXvrNCYXBjanUYvi0ukM0ujLJiZ+qMutRzxkckqN1ZXSRsjPoCG7S46M1Ew52TKYYkPm/53gqe+gQzdIEDAg8cuxIbSiuKGr2em/jnRfQIDAQAB
-----END PUBLIC KEY-----';

    /**
     * 商户的用户从未在 速汇付 使用过快捷支付的方式，初次交易需要先进行签约才可以下一步交易。
     */
    public function Sign()
    {
        $businessHead = [
            'charset'        => '00', //00表示UTF-8，暂时只支持UTF-8
            'version'        => 'V1.0.0',
            'merchantNumber' => 'PAY000053000213', //商户ID todo: 用数据库里的值
            'tradeType'      => 'quickPaySignApi',
            'requestTime'    => date('YmdHis'),
            'signType'       => 'RSA', //暂时只支持RSA，必须大写的RSA
        ];
        $businessContext = [
            //'idNo' => I('request.id_no'), //todo uncomment
            'idNo' => '445221199202071334',
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

        ksort($arr_order);

        $json_order = json_encode($arr_order);
        $cryptos = '';
        $pu_ecpay_key = openssl_pkey_get_public($this->public_ecpay_key);//这个函数可用来判断ECPAY提供的私钥是否是可用的，可用返回资源id Resource id
        foreach (str_split($json_order, 117) as $value) {

            openssl_public_encrypt($value, $encryptDatas, $pu_ecpay_key);

            $cryptos .= $encryptDatas;
        }

        $context = [
            'context' => base64_encode($cryptos),
        ];
        //var_dump(json_encode($context));

        list($return_code, $return_content) = $this->http_post_data($this->gateway, json_encode($context));//

        var_dump($return_content);

        $mer_private_key = openssl_pkey_get_private($this->private_key);//取私钥资源号

        $ec_public_key = openssl_pkey_get_public($this->public_ecpay_key);//取PAY公钥资源号

        $data = $this->rsa_decrypt($return_content['context'], $mer_private_key);//执行解密流程

        $context_arr = json_decode($data, true);//转为数组格式

        echo "fuck";
        echo "<br><br>" . base64_decode($context_arr['context']);
    }


    /**
     * @param array $channel
     */
    public function Pay($channel)
    {

        $return = $this->getParameter('速汇付', $channel, BaBaoDaoController::class, 100);

        $businessHead = [
            'charset'        => '00', //00表示UTF-8，暂时只支持UTF-8
            'version'        => 'V1.0.0',
            'merchantNumber' => $return["mch_id"], //商户ID
            'tradeType'      => 'quickPayInitApi',
            'requestTime'    => date('YmdHis'),
            'signType'       => 'RSA', //暂时只支持RSA，必须大写的RSA
        ];
        $businessContext = [
            'orderNumber'   => $return['orderid'], //商户订单号
            'payType'       => 'QUICK_SAVINGS',
            'idType'        => 'IDENTITY_CARD',
            'idNo'          => I('request.id_no'), //身份证号
            'userName'      => I('request.user_name'),
            'mobile'        => I('request.mobile'),
            'cardNo'        => I('request.card_no'),
            'cardType'      => 'SAVINGS', //暂时不支持信用卡
            'amount'        => bcmul(I('request.pay_amount'), 100),
            'currency'      => 'CNY',
            'orderCreateIp' => get_client_ip(),
            'commodityName' => '商品名称',
            'commodityDesc' => '商品描述',
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

        ksort($arr_order);

        $json_order = json_encode($arr_order);
        $cryptos = '';
        $pu_ecpay_key = openssl_pkey_get_public($this->public_ecpay_key);//这个函数可用来判断ECPAY提供的私钥是否是可用的，可用返回资源id Resource id
        foreach (str_split($json_order, 117) as $value) {

            openssl_public_encrypt($value, $encryptDatas, $pu_ecpay_key);

            $cryptos .= $encryptDatas;
        }

        $context = [
            'context' => base64_encode($cryptos),
        ];
        var_dump(json_encode($context));

        list($return_code, $return_content) = $this->http_post_data($this->gateway, json_encode($context));//
        echo "<br><br>" . $return_code;
        echo "<br><br>" . $return_content;

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

    //异步通知地址
    public function notifyurl()
    {
        Log::record('速汇付异步通知：开始：' . PHP_EOL . var_export(func_get_args(), true), Log::INFO);

        //$businessHead = [
        //    'charset'        => I('request.charset'),
        //    'version'        => I('request.version'),
        //    'merchantNumber' => I('request.merchantNumber'),
        //    'tradeType'      => I('request.tradeType'),
        //    'responseTime'   => I('request.responseTime'),
        //    'signType'       => I('request.signType'),
        //];
        //$businessContext = [
        //    'payOrderNumber' => I('request.payOrderNumber'),
        //    'orderNumber'    => I('request.orderNumber'),
        //    'orderStatus'    => I('request.orderStatus'),
        //    'orderTime'      => I('request.orderTime'),
        //    'currency'       => I('request.currency'),
        //    'amount'         => I('request.amount'),
        //    'fee'            => I('request.fee'),
        //    'payType'        => I('request.payType'),
        //];

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
            $this->EditMoney($orderNumber, 'BaBaoDao', 0);
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
