<?php

/**
 * @author zhangjianwei
 * @date   2018-05-09
 */

namespace Pay\Controller;

use Think\Log;


/**
 * 立客支付
 * @package Pay\Controller
 */
class LiKeController extends PayController
{
    //支付网关
    private $gateway = 'http://120.78.196.14/testOnlinePay';
    //节点号，由平台分配
    private $nodeId = 10000012;
    //加密密钥
    private $key = ''; //从后台读取
    //签名密钥（商户私钥）
    private $privateKey = 'MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAOTYIqkjyCrIHdIeOAvTwaggG6mAhXU6byrW5SIqAXE3znaiBeOeDVNWJzs/pQtXuTn6fB1LoU3Q93hPcLkh7kdoH3+BJDzoPWZ5tPyzgua2nad9xMNNphfRYDVTiEoAxOnFc3aNI22gse+wPS0Ll29/LGp+z3e/p+e1cRP/ibFJAgMBAAECgYEA3pVbISisiPAcEUNTQC23LtAMF9Hp/RvZBNIADDrPLFAbgUgWck5Ip8YkYnyFC4NHphz8m4H0Yrvd+CdMfMWD/BkPRf3eafhnJlHGKyGqsAXLmGh/mvJbleE3NH9LS1N/0+pPam58mAjvkujxoPQ0v5BxHyS7r14lBMkvxiXN9AECQQD8B2zTpvsXDWJFwjKYmKRkWCs3JOaOJmWX6MTY3qPSE6mFW/93blDAs1kEioB01ZsbKiE3fIubZVcFEzI90nCXAkEA6HMxd+GYWA7+UdeOklhz/XhBdtlsOeHZDG8glOFhsHJguURcnov2TG4G5L1t+qdnpZzTeNKVrSyT2ECE4gVJHwJAVwiZZF39x/AvR7fQkTHlU2G/SsPLert3ygXwNJRuLlXr7MngZvYJnQJSc2cBBVfewHrEDc1MyNUuP+ppJ0BM8QJBALdi6gwiNwaCDbKT1S8wCZJXZY5WSkQAIjTlF1dd2KxUEGsZu9h5o3747wdXS4UMvYCzEUOpH9zX5mwdurh2YxECQQDuPsVpoJlevwbIuRymGzvYvVZvDP2N+O4rN0lrJnlhTXkYdsRLSw92QcBX0jRqjwl/LwEMPt8EaK25xJ6rEc07';
    //验签密钥（平台公钥）
    private $publicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCfRRqiTyiDRvgPwAnHm+odB6kEY1O51Zh5rlr3iSYEgDKfO00yD6ZCAh6MlKfYT0DD+WKN91lt6t9g/u0Cw2WJwGeUiOEWUDso/MiOGmdGYrfsarEzGCTSRmu1tIdwFKNi9HThcMTs7aU99lBtoGIYu2mxsXoWnLbdExZ9TaOBgwIDAQAB';

    public function Pay($channel)
    {
        $bankCode = I('request.bankCode', '');

        if (empty($bankCode)) {
            exit('bankCode 参数缺失');
        }

        $exchange = 1;
        $return = $this->getParameter('立客支付', $channel, LiKeController::class, $exchange);

        $this->key = $return['signkey'];
        $this->privateKey = "-----BEGIN RSA PRIVATE KEY-----\n".wordwrap($this->privateKey, 64, "\n", TRUE)."\n-----END RSA PRIVATE KEY-----";
        $this->publicKey = "-----BEGIN PUBLIC KEY-----\n".wordwrap($this->publicKey, 64, "\n", TRUE)."\n-----END PUBLIC KEY-----";

        //请求表单初始化
        $form = [
            'version'    => '1.0',
            'nodeId'     => $this->nodeId,
            'orgId'      => $return['mch_id'],
            'orderTime'  => time(),
            'txnType'    => 'T11110', //网关支付 T11110 （页面跳转）
            'signType'   => 'RSA',
            'charset'    => 'UTF-8',
            'bizContext' => '',
            'sign'       => '',
            'reserve1'   => '',
        ];

        // 业务参数，不同类型有差异，需对应修改
        $bizContext = [
            'outTradeNo'  => $return['orderid'],
            'totalAmount' => $return['amount'], //限额 150~20000，单位元
            'currency'    => 'CNY',
            'body'        => '商品',
            //'detail'      => '测试订单详情',
            'pageUrl'     => $return['callbackurl'],
            'notifyUrl'   => $return['notifyurl'],
            'orgCreateIp' => get_client_ip(),
            'payerBank'   => $bankCode, //付款银行编码
            //'deviceInfo'  => '',
            //'appName'     => '',
            //'appId'       => '',
            //'feeRate'     => '0.5', //根据需求修改
            //'dfFee'       => '2',
            //'reserve1'    => ''
        ];

        // 1. 业务参数 json 编码
        $bizContextJson = json_encode($bizContext);

        Log::record('origin bizContext:' . PHP_EOL . $bizContextJson . PHP_EOL, Log::INFO);

        // 2. 业务参数签名
        $bizContextSign = PayLib::rsaSHA1Sign($bizContextJson, $this->privateKey);
        // 3. 业务参数加密
        $bizContextAESEncrypt = PayLib::AESEncrypt($bizContextJson, $this->key);

        // 4. 回填表单
        $form['sign'] = $bizContextSign;
        $form['bizContext'] = $bizContextAESEncrypt;

        Log::record('request form:' . PHP_EOL . json_encode($form) . PHP_EOL, Log::INFO);

        $this->setHtml($this->gateway, $form);

        ////5. 发送请求
        //$response = PayLib::postForm($this->gateway, $form);
        //Log::record('response body:' . PHP_EOL . $response . PHP_EOL, Log::INFO);
        //
        //// 解析响应 json
        //$response = json_decode($response, TRUE);
        ////var_dump($response);
        //if ($response['code'] != 'SUCCESS') {
        //    Log::record('立客支付响应错误：' . json_encode($response), Log::INFO);
        //    exit;
        //}
        //
        //// 业务参数解密
        //$bizContextAESDecrypt = PayLib::AESDecrypt($response['bizContext'], $this->key);
        //Log::record('bizContext AES Decrypt:' . PHP_EOL . $bizContextAESDecrypt . PHP_EOL, Log::INFO);
        //
        //// 验签
        //$verify = PayLib::rsaSHA1Verify($bizContextAESDecrypt, $response['sign'], $this->publicKey);
        //
        //Log::record('verify result:' . PHP_EOL . $verify . PHP_EOL, Log::INFO);
        //
        //if ($verify == 1) {
        //
        //}

    }


    //异步通知
    public function notifyurl()
    {

        Log::record('立客支付异步通知：参数：' . json_encode($_POST), Log::INFO);

        $returncode = I('request.retCode');
        $returnMsg = I('request.retMsg');

        $orderid = I('request.outTradeNo'); //商户订单号
        $money = I('request.totalAmount');

        //获取订单信息
        $order = M('Order')->where(['pay_orderid' => $orderid])->find();
        if (empty($order)) {
            Log::record('立客支付异步通知：订单不存在：单号：' . $orderid, Log::INFO);
            exit('订单不存在');
        }
        $key = $order['key']; //签名key

        //todo 验证签名 !!important!!

        switch ($returncode) {
            case "RC0000": //成功
                //判断金额是否相等
                if (floatval($order['pay_amount']) !== floatval($money)) {
                    Log::record('立客支付异步通知：返回成功但金额错误：单号：' . $orderid . '金额：' . $money, Log::INFO);
                    exit('金额错误');
                }

                //修改订单信息
                $this->EditMoney($orderid, '', 0);
                Log::record('立客支付异步通知：完成：订单号：' . $orderid, Log::INFO);
                echo 'SUCCESS';
                break;
            default:
                //失败
                Log::record('立客支付异步通知：失败信息：' . $returnMsg . '，数据：' . json_encode($_POST), Log::INFO);
                break;
        }
    }

    //同步回调地址
    public function callbackurl()
    {
        //接收订单号
        Log::record('立客支付同步跳转：' . json_encode($_POST), Log::INFO);
        $orderNumber = I('request.outTradeNo');
        if (empty($orderNumber)) {
            Log::record("立客支付同步跳转：参数错误：" . json_encode($_POST), Log::INFO);
            exit('参数错误');
        }

        $order_info = M('Order')->where(['pay_orderid' => $orderNumber])->find();
        if (!$order_info) {
            Log::record("立客支付同步跳转：订单不存在：参数：" . json_encode($_POST), Log::INFO);
            exit("订单不存在");
        }

        //上游未通知时，重试
        if (!in_array($order_info["pay_status"], [1, 2])) {
            sleep(5);
            $order_info = M('Order')->where(['pay_orderid' => $orderNumber])->find();
        }
        //再重试
        if (!in_array($order_info["pay_status"], [1, 2])) {
            sleep(5);
            $order_info = M('Order')->where(['pay_orderid' => $orderNumber])->find();
        }
        if (!($order_info["pay_status"] == 1 || $order_info["pay_status"] == 2)) {
            exit("支付已提交，请返回商户页面查询是否成功");
        }

        //返回下游
        $return_code = "00"; // 交易状态，00表示成功
        $return_array = [ // 返回字段
                          "memberid"       => $order_info["pay_memberid"], // 商户ID
                          "orderid"        => $order_info['out_trade_id'], // 订单号
                          'transaction_id' => $order_info["pay_orderid"], //支付流水号
                          "amount"         => $order_info["pay_amount"], // 交易金额
                          "datetime"       => date("YmdHis"), // 交易时间
                          "returncode"     => $return_code, // 交易状态
        ];

        $userid = intval($order_info["pay_memberid"] - 10000); // 商户ID
        $member_info = M('Member')->where(['id' => $userid])->find();
        if (!$member_info) {
            Log::record("立客支付同步跳转：商户不存在：订单号：{$orderNumber}" . "，用户id：" . $userid, Log::INFO);
            exit("商户不存在");
        }
        $sign = $this->createSign($member_info['apikey'], $return_array);
        $return_array["sign"] = $sign;
        $return_array["attach"] = $order_info["attach"];

        $this->setHtml($order_info["pay_callbackurl"], $return_array);
    }
}


/**
 * Class PayLib
 * 商家接入 SDK
 */
class PayLib
{
    /**
     * AES/PKCS5_PADDING/ECB 128 位加密
     *
     * @param string $preEncryptString 原始 json 字符串
     * @param string $aesKey           base64_encode 编码过的 key
     *
     * @return string base64_encode 编码过的加密字符串
     */
    public static function AESEncrypt($preEncryptString, $aesKey)
    {
        $aesKey = base64_decode($aesKey);

        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
        $preEncryptString = self::pkcs5_pad($preEncryptString, $size);
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $aesKey, $iv);
        $encryptData = mcrypt_generic($td, $preEncryptString);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        $encryptData = base64_encode($encryptData);

        return $encryptData;
    }

    /**
     * @param string $text      原始字符串
     * @param int    $blocksize 补码位
     *
     * @return string 经过补码的字符串
     */
    private static function pkcs5_pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);

        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * AES/PKCS5_PADDING/ECB 128 位解密
     *
     * @param string $encrypted base64_encode 编码过的加密字符串
     * @param string $aesKey    base64_encode 编码过的秘钥
     * @param string $charset   字符集，未使用
     *
     * @return string 原始 json 字符串
     */
    public static function AESDecrypt($encrypted, $aesKey, $charset = 'UTF-8')
    {
        $aesKey = base64_decode($aesKey);
        $encrypted = base64_decode($encrypted);

        $decrypted = mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $aesKey,
            $encrypted,
            MCRYPT_MODE_ECB
        );

        $decrypted = self::pkcs5_unpad($decrypted);

        return $decrypted;
    }

    /**
     * @param string $decrypted 经过补码的字符串
     *
     * @return string 去除补码的字符串
     */
    private static function pkcs5_unpad($decrypted)
    {
        $len = strlen($decrypted);
        $padding = ord($decrypted[$len - 1]);
        $decrypted = substr($decrypted, 0, -$padding);

        return $decrypted;
    }

    // /**
    //  * @param string $data
    //  * @param mixed  $publicPEMKey
    //  * @param int    $padding OPENSSL_PKCS1_PADDING|OPENSSL_NO_PADDING
    //  *
    //  * @return bool|string
    //  */
    // public static function rsaEncrypt($data, $publicPEMKey, $padding = OPENSSL_PKCS1_PADDING)
    // {
    // 	$decrypted = '';

    // 	//decode must be done before split for getting the binary String
    // 	$data = str_split(self::urlSafe_base64decode($data), self::DECRYPT_BLOCK_SIZE);

    // 	foreach ($data as $chunk) {
    // 		$partial = '';

    // 		//be sure to match padding
    // 		$decryptionOK = openssl_private_encrypt($chunk, $partial, $publicPEMKey, $padding);

    // 		if ($decryptionOK === FALSE) {
    // 			return FALSE;
    // 			//here also processed errors in decryption. If too big this will be false
    // 		}
    // 		$decrypted .= $partial;
    // 	}

    // 	return base64_encode($decrypted);
    // }

    // /**
    //  * @param string $string
    //  *
    //  * @return string
    //  */
    // public static function urlSafe_base64decode($string)
    // {
    // 	$data = str_replace(array(' ', '-', '_'), array('+', '+', '/'), $string);
    // 	$mod4 = strlen($data) % 4;
    // 	if ($mod4) {
    // 		$data .= substr('====', $mod4);
    // 	}

    // 	return base64_decode($data);
    // }

    // /**
    //  * 获取解密
    //  */
    // public static function decryptBizContext($encryptedBizContext, $rsa_key)
    // {
    // 	$rsa_key = base64_decode($rsa_key);

    // 	$rsa_key_resource = openssl_pkey_get_public($rsa_key);
    // 	$decrypted = self::rsaDecrypt($encryptedBizContext, $rsa_key_resource, OPENSSL_PKCS1_PADDING);
    // 	$decrypted = json_decode($decrypted, TRUE);

    // 	return $decrypted;
    // }

    // /**
    //  * @param string $data
    //  * @param mixed  $publicPEMKey
    //  * @param int    $padding OPENSSL_PKCS1_PADDING|OPENSSL_NO_PADDING
    //  *
    //  * @return bool|string
    //  */
    // public static function rsaDecrypt($data, $publicPEMKey, $padding = OPENSSL_PKCS1_PADDING)
    // {
    // 	$decrypted = '';

    // 	//decode must be done before split for getting the binary String
    // 	$data = str_split(self::urlSafe_base64decode($data), self::DECRYPT_BLOCK_SIZE);

    // 	foreach ($data as $chunk) {
    // 		$partial = '';

    // 		//be sure to match padding
    // 		$decryptionOK = openssl_public_decrypt($chunk, $partial, $publicPEMKey, $padding);

    // 		if ($decryptionOK === FALSE) {
    // 			return FALSE;
    // 			//here also processed errors in decryption. If too big this will be false
    // 		}
    // 		$decrypted .= $partial;
    // 	}

    // 	return $decrypted;
    // }

    /**
     * 签名  生成签名串  基于sha1withRSA
     *
     * @param string $data 签名前的字符串
     *
     * @param string $privateKey
     *
     * @return string 签名串
     */
    public static function rsaSHA1Sign($data, $privateKey)
    {
        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }

    /**
     * 验签  验证签名  基于sha1withRSA
     *
     * @param string $data      签名前的原字符串
     * @param string $signature 签名串
     * @param string $publicKey
     *
     * @return int
     */
    public static function rsaSHA1Verify($data, $signature, $publicKey)
    {
        $signature = base64_decode($signature);

        //		$publicKey = openssl_pkey_get_public($publicKey);
        //		$keyData = openssl_pkey_get_details($publicKey);

        $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA1);//openssl_verify 验签成功返回 1，失败 0，错误返回 -1

        return $result;
    }

    public static function postForm($url, $data, $headers = [], $referer = NULL)
    {
        $headerArr = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $headerArr[] = $k . ': ' . $v;
            }
        }
        $headerArr[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        if ($referer) {
            curl_setopt($ch, CURLOPT_REFERER, "http://{$referer}/");
        }
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

}