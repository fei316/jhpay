<?php
namespace Pay\Controller;

/**
 * 银联网关支付（手机）
 */
class UnionpayWgMobileController extends PayController
{

    public function Pay($array)
    {

        $return = $this->getParameter('银联网关支付（手机）', $array, __CLASS__, 100);

        $encryp = encryptDecrypt(serialize($return), 'UnionpayWgMobile');

        if($return['unlockdomain']) {
            echo createForm($return['unlockdomain'].'/Pay_UnionpayWgMobile_Rpay', ['encryp' => $encryp]);
        } else {
            echo createForm($this->_site .'Pay_UnionpayWgMobile_Rpay', ['encryp' => $encryp]);
        }
    }

    /**
     * @param array $channel
     */
    public function Rpay()
    {
        //接收传输的数据
        $data = I('post.', '');
        //将数据解密并反序列化
        $return = unserialize(encryptDecrypt($data['encryp'], 'UnionpayWgMobile', 1));
        //检测数据是否正确
        $return || $this->error('传输数据不正确！');
        if($return['unlockdomain']) {
            $return["notifyurl"] = $return['unlockdomain']."/Pay_UnionpayWgMobile_notifyurl.html";
            $return['callbackurl']  = $return['unlockdomain'] ."/Pay_UnionpayWgMobile_callbackurl.html";
        } else {
            $return["notifyurl"] = $this->_site ."Pay_UnionpayWgMobile_notifyurl.html";
            $return['callbackurl']  = $this->_site . 'Pay_UnionpayWgMobile_callbackurl.html';
        }
        $params = array(

            //以下信息非特殊情况不需要改动
            'version' => \Org\Util\unionpay\SDKConfig::getSDKConfig($return['mch_id'])->version,                 //版本号
            'encoding' => 'utf-8',				  //编码方式
            'txnType' => '01',				      //交易类型
            'txnSubType' => '01',				  //交易子类
            'bizType' => '000201',				  //业务类型
            'frontUrl' => $return["callbackurl"],  //前台通知地址
            'backUrl' => $return["notifyurl"],	  //后台通知地址
            'signMethod' => \Org\Util\unionpay\SDKConfig::getSDKConfig($return['mch_id'])->signMethod,	              //签名方法
            'channelType' => '08',	              //渠道类型，07-PC，08-手机
            'accessType' => '0',		          //接入类型
            'currencyCode' => '156',	          //交易币种，境内商户固定156

            //TODO 以下信息需要填写
            'merId' => $return['mch_id'],
            'orderId' => $return['orderid'],
            'txnTime' => date('YmdHis'),
            'txnAmt' => $return['amount'],
            'payTimeout' => date('YmdHis', strtotime('+15 minutes')),

        );
        \Org\Util\unionpay\AcpService::sign ($params);
        $uri = \Org\Util\unionpay\SDKConfig::getSDKConfig($return['mch_id'])->frontTransUrl;
        $html_form = \Org\Util\unionpay\AcpService::createAutoFormHtml( $params, $uri );
        echo $html_form;
    }

    //同步回调地址
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["orderId"]])->getField("pay_status");
        if($pay_status == 0) {
            sleep(3);//等待3秒
            $pay_status = M('Order')->where(['pay_orderid' => $_REQUEST["orderId"]])->getField("pay_status");
        }
        if ($pay_status <> 0) {
            $this->EditMoney($_REQUEST["orderId"], 'UnionpayWgMobile', 1);
        } else {
            exit('页面已过期请刷新');
        }
    }

    //异步通知地址
    public function notifyurl()
    {
        file_put_contents('./Data/unionpay_notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        if (isset ( $_POST ['signature'])) {
            if(isset($_POST['merId'])) {
                if(\Org\Util\unionpay\AcpService::validate($_POST, $_POST['merId'])) {
                    $orderId = $_POST ['orderId'];
                    $respCode = $_POST ['respCode'];
                    if($respCode == '00') {
                        $this->EditMoney($orderId, 'UnionpayWgMobile', 0);
                    }
                } else {
                    exit('验签失败');
                }
            } else {
                exit('商户号为空');
            }
        } else {
            echo '签名为空';
        }
    }
}

?>
