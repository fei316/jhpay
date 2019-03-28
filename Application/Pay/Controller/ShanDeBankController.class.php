<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-09-04
 * Time: 0:25
 */
namespace Pay\Controller;

/**
 * 第三方接口开发示例控制器
 * Class DemoController
 * @package Pay\Controller
 *
 * 三方通道接口开发说明：
 * 1. 管理员登录网站后台，供应商管理添加通道，通道英文代码即接口类名称
 * 2. 用户管理-》通道-》指定该通道（独立或轮询）
 * 3. 用户费率优先通道费率
 * 4. 用户通道指定优先系统默认支持产品通道指定
 * 5. 三方回调地址URL写法，如本接口 ：
 *    异步地址：http://www.yourdomain.com/Pay_Demo_notifyurl.html
 *    跳转地址：http://www.yourdomain.com/Pay_Demo_callbackurl.html
 *
 *    注：下游对接请查看商户API对接文档部分.
 */

class ShanDeBankController extends PayController
{
    protected $gateway = 'https://cashier.sandpay.com.cn/gateway/api/order/pay';

    protected $bankItem = [
        'ICBC' => '01020000',
        '3001' => '03050000',
        'BOC'  => '01040000',
        'CCB'  => '01050000',
        'ABC'  => '01030000',
        'PSBC' => '01000000',
        'SPDB' => '03100000',
        'CEB'  => '03030000',
        'GDB'  => '03060000',

        //'COMM'  => '03010000',
        //'CITIC' => '03020000',
        //'HXB'   => '03040000',
        //'SPAB'  => '03070000',
        //'CMB'   => '03080000',
        //'CIB'   => '03090000',
        //'BJB'   => '04031000',
        //'HSB'   => '64296511',
        //'上海银行'   => '04012900',
        //'南京银行'   => '04243010',
        //'上海农商'   => '65012900',
        //'宁波银行'   => '04083320',
        //'渤海银行'   => '03170000',
        //'成都银行'   => '64296510',
        //天津银行'   => '04341101',
    ];

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body    = I('request.pay_productname');
        $return  = $this->getParameter('杉德(网关)', $array, __CLASS__, 100);
        $encryp  = encryptDecrypt(serialize($return), 'lgbya');
        if($return['unlockdomain']) {
            echo createForm($return['unlockdomain'].'/Pay_ShanDeBank_Gopay', ['encryp' => $encryp]);
        } else {
            echo createForm($this->_site .'Pay_ShanDeBank_Gopay', ['encryp' => $encryp]);
        }
    }

	public function Gopay(){
		//接收传输的数据
        $postData = I('post.', '');
		$encryp = $postData['encryp'];
        //将数据解密并反序列化
        $data = unserialize(encryptDecrypt($encryp, 'lgbya', 1));
		if($data['unlockdomain']) {
			$rpay_url = $data['unlockdomain'].'/Pay_ShanDeBank_Rpay';
		} else {
			$rpay_url = $this->_site .'Pay_ShanDeBank_Rpay';
		}
        $this->assign([
            'bank_array' => $this->bankItem,
            'rpay_url'   => $rpay_url,
            'orderid'    => $data['orderid'],
            'body'       => $data['subject'],
            'money'      => $data['amount']/100,
            'encryp'     => $encryp,
        ]);
        $this->display('BankPay/pc');
        return;
	}
	
    public function Rpay()
    {

        //接收传输的数据
        $postData = I('post.', '');

        //将数据解密并反序列化
        $data = unserialize(encryptDecrypt($postData['encryp'], 'lgbya', 1));

        //检测数据是否正确
        $data || $this->error('传输数据不正确！');

        $data['bank_code'] = I('post.bankCode', '');
        $data['bank_code'] || $this->error('请选择支付的银行');

        // list($data['appsecret'], $data['notifyurl']) = explode('|', $data['appsecret']);
        // $data['notifyurl']                           = $data['notifyurl'] . '/Pay_ShanDeBank_notifyurl.html';

        //获取私钥，公钥
        $prikey   = $this->loadPk12Cert($data['appid'], $data['appsecret']);
        $pubkey   = $this->loadX509Cert($data['signkey']);
        $jsonData = [
            'head' => [
                'version'     => '1.0',
                'method'      => 'sandpay.trade.pay',
                'productId'   => '00000007',
                'accessType'  => '1',
                'mid'         => $data['mch_id'],
                'channelType' => '07',
                'reqTime'     => date('YmdHis'),
            ],

            'body' => [
                'orderCode'   => $data['orderid'],
                'totalAmount' => str_pad($data['amount'], 12, '0', STR_PAD_LEFT),
                'subject'     => '在线支付',
                'body'        => '在线支付',
                'payMode'     => 'bank_pc',
                'payExtra'    => json_encode(['payType' => '1', 'bankCode' => $data['bank_code']]),
                'clientIp'    => getIP(),
                'notifyUrl'   => $data['notifyurl'],
                'frontUrl'    => $data['callbackurl'],
                'extend'      => '',
            ],
        ];

        $jsonData = json_encode($jsonData);
        $sign     = rsaEncryptVerify($jsonData, $prikey);
        $params = [
            'charset'  => 'utf-8',
            'signType' => '01',
            'data'     => $jsonData,
            'sign'     => $sign,
        ];

        $respond = curlPost($this->gateway, http_build_query($params), ['Content-Type: application/x-www-form-urlencoded']);

        parse_str(urldecode($respond), $arr);

        $arr['data'] = str_replace(["  ", "\t", "\n", "\r"], ['', '', '', ''], $arr['data']);

        $data = json_decode($arr['data'], true);

        $credential = json_decode($data['body']['credential'], true);

        if (isset($credential['params']['orig']) && isset($credential['params']['sign'])) {
            $arr['data'] = $this->mb_array_chunk($data);
            $arr['data'] = str_replace(["\\\/", "\\/", "\/"], ["/", "/", "/"], $arr['data']);
        } else {

            $data['body']['credential'] = $this->json_encodes($credential);
            //使用第二参数JSON_UNESCAPED_UNICODE,阻止json_encode()转译汉字
            $arr['data'] = str_replace(["\\\/", "\\/", "\/", " "], ["/", "/", "/", "+"], $this->json_encodes($data));
        }

        $arr['sign'] = preg_replace('/\s/', '+', $arr['sign']);

        $data = json_decode($arr['data'], 320);

        if ($data['head']['respCode'] == "000000") {
            $credential = str_replace(['"{', '}"'], ['{', '}'], stripslashes($data['body']['credential']));

            $credential = json_decode($credential, true);
            echo createForm($credential['submitUrl'], $credential['params']);
        } else {
            $this->error($data['head']['respMsg']);
        }

    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $Order      = M("Order");
        $data       = json_decode($_POST['data'], true);
        $pay_status = $Order->where(["pay_orderid" => $data['body']["orderCode"]])->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($data['body']["orderCode"], '', 1);
        } else {
            exit("error");
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {

        $post = $_POST;
        file_put_contents('./Data/notify1.txt', "【" . date('Y-m-d H:i:s') . "】\r\n" . serialize($_POST) . "\r\n\r\n", FILE_APPEND);

        if ($post) {
            $sign   = base64_decode($post['sign']); //签名
            $data   = stripslashes($post['data']); //支付数据
            $result = json_decode($data, true); //data数据

            $publicCertPath = getKey($result['body']["orderCode"]);
            $pubkey         = $this->loadX509Cert($publicCertPath);
            if (rsaEncryptVerify($data, $pubkey, $sign) && $result['body']['orderStatus'] == 1) {
                //签名验证成功
                $this->EditMoney($result['body']["orderCode"], '', 0);
                echo "respCode=000000";
                exit;
            } else {
                //签名验证失败
                exit;
            }

        }

    }

    /********************************************辅助方法**************************************************/

    /**
     *获取公钥
     *@param  [$path]
     *@return [mixed]
     *@throws [\Exception]
     */
    protected function loadX509Cert($path)
    {
        $file   = file_get_contents($path);
        $cert   = chunk_split(base64_encode($file), 64, "\n");
        $cert   = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
        $res    = openssl_pkey_get_public($cert);
        $detail = openssl_pkey_get_details($res);
        openssl_free_key($res);
        return $detail['key'];
    }

    /**
     * 获取私钥
     * @param  [$path]
     * @param  [$pwd]
     * @return [mixed]
     * @throws [\Exception]
     */
    protected function loadPk12Cert($path, $pwd)
    {
        $file = file_get_contents($path);
        openssl_pkcs12_read($file, $cert, $pwd);
        return $cert['pkey'];
    }

    /**
     * 对数组变量进行JSON编码，为了（本系统的PHP版本为5.3.0）解决PHP5.4.0以上才支持的JSON_UNESCAPED_UNICODE参数
     *@param mixed array 待编码的 array （除了resource 类型之外，可以为任何数据类型，改函数只能接受 UTF-8 编码的数据）
     *@return  string （返回 array 值的 JSON 形式）
     *@author
     * @d/t     2017-07-17
     */
    protected function json_encodes($array)
    {

        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $str = json_encode($array);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i", function ($matchs) {
                return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
            }, $str);
            return $str;
        } else {
            return json_encode($array, 320);
        }
    }

    /**
     * 分割字符串
     * @param String $str  要分割的字符串
     * @param int $length  指定的长度
     * @param String $end  在分割后的字符串块追加的内容
     */
    protected function mb_chunk_split($string, $length, $end, $once = false)
    {
        $string = iconv('gb2312', 'utf-8//ignore', $string);
        $array  = array();
        $strlen = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string, 0, $length, "utf-8");
            if ($once) {
                return $array[0] . $end;
            }

            $string = mb_substr($string, $length, $strlen, "utf-8");
            $strlen = mb_strlen($string);
        }
        $str = implode($end, $array);
        return $str . '%0A';
    }

    protected function mb_array_chunk($arr)
    {

        $credential                   = json_decode($arr['body']['credential'], true);
        $credential['params']['orig'] = $this->mb_chunk_split($credential['params']['orig'], 76, '%0A');
        $credential['params']['sign'] = $this->mb_chunk_split($credential['params']['sign'], 76, '%0A');
        $arr['body']['credential']    = str_replace(array('==', '+', '='), array('%3D%3D', '%2B', '%3D'), $this->json_encodes($credential));

        return $this->json_encodes($arr);

    }

}
