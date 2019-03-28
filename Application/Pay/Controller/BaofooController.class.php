<?php
namespace Pay\Controller;
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */

class BaofooController extends PayController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Baofoo_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Baofoo_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'Baofoo', // 通道名称
            'title' => '宝付',
            'exchange' => 100, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => I("request.pay_orderid", ""),
            'body'=>$body,
            'channel'=>$array
        );
        $return = $this->orderadd($parameter);

        $MemberID = $return["sid"]; // 商户号
        $TransID = $return["orderid"]; // 流水号
        $TerminalID = $return["account"]; // 终端号
        $PayID = $return["bankcode"]; // 支付方式
        $TradeDate = date("YmdHis"); // 交易时间
        $OrderMoney = $return["amount"]; // 订单金额
        $ProductName = "zhifupingtai"; // 产品名称
        $Amount = 1; // 商品数量
        $Username = $return["memberid"]; // 支付用户名
        $AdditionalInfo = $return["resrved"]; // 订单附加消息
        $PageUrl = $return["callbackurl"]; // 通知商户页面端地址
        $ReturnUrl = $return["notifyurl"]; // 服务器底层通知地址
        $NoticeType = 1; // 通知类型
        $Md5key = $return["key"]; // md5密钥（KEY）
        $MARK = "|";
        $Signature = md5($MemberID . $MARK . $PayID . $MARK . $TradeDate . $MARK . $TransID . $MARK . $OrderMoney . $MARK . $PageUrl . $MARK . $ReturnUrl . $MARK . $NoticeType . $MARK . $Md5key);
        $InterfaceVersion = "4.0";
        $KeyType = "1";
        $arraystr = array(
            "MemberID" => $MemberID,
            "TransID" => $TransID,
            "TerminalID" => $TerminalID,
            "PayID" => $PayID,
            "TradeDate" => $TradeDate,
            "OrderMoney" => $OrderMoney,
            "ProductName" => $ProductName,
            "Amount" => $Amount,
            "Username" => $Username,
            "AdditionalInfo" => $AdditionalInfo,
            "PageUrl" => $PageUrl,
            "ReturnUrl" => $ReturnUrl,
            "NoticeType" => $NoticeType,
            "Signature" => $Signature,
            "InterfaceVersion" => $InterfaceVersion,
            "KeyType" => $KeyType
        );
        $this->setHtml('http://gw.baofoo.com/payindex', $arraystr);
    }

    public function callbackurl()
    { // 页面通知返回
        $MemberID = $_REQUEST['MemberID']; // 商户号
        $TerminalID = $_REQUEST['TerminalID']; // 商户终端号
        $TransID = $_REQUEST['TransID']; // 商户流水号
        $Result = $_REQUEST['Result']; // 支付结果
        $ResultDesc = $_REQUEST['ResultDesc']; // 支付结果描述
        $FactMoney = $_REQUEST['FactMoney']; // 实际成功金额
        $AdditionalInfo = $_REQUEST['AdditionalInfo']; // 订单附加消息
        $SuccTime = $_REQUEST['SuccTime']; // 支付完成时间
        $Md5Sign = $_REQUEST['Md5Sign']; // md5签名
        $MARK = "~|~";
        $Md5key = $this->getSignkey('Baofoo', $MemberID); // 密钥
        $WaitSign = md5('MemberID=' . $MemberID . $MARK . 'TerminalID=' . $TerminalID . $MARK . 'TransID=' . $TransID . $MARK . 'Result=' . $Result . $MARK . 'ResultDesc=' . $ResultDesc . $MARK . 'FactMoney=' . $FactMoney . $MARK . 'AdditionalInfo=' . $AdditionalInfo . $MARK . 'SuccTime=' . $SuccTime . $MARK . 'Md5Sign=' . $Md5key);
        if ($Md5Sign == $WaitSign) {
            $this->EditMoney($TransID, 'Baofoo', 1);
        } else {
            echo "数据验证失败";
        }
    }

    public function notifyurl()
    { // 服务器点对点返回
        $MemberID = $_REQUEST['MemberID']; // 商户号
        $TerminalID = $_REQUEST['TerminalID']; // 商户终端号
        $TransID = $_REQUEST['TransID']; // 商户流水号
        $Result = $_REQUEST['Result']; // 支付结果
        $ResultDesc = $_REQUEST['ResultDesc']; // 支付结果描述
        $FactMoney = $_REQUEST['FactMoney']; // 实际成功金额
        $AdditionalInfo = $_REQUEST['AdditionalInfo']; // 订单附加消息
        $SuccTime = $_REQUEST['SuccTime']; // 支付完成时间
        $Md5Sign = $_REQUEST['Md5Sign']; // md5签名
        $MARK = "~|~";
        $Md5key = $this->getSignkey('Baofoo', $MemberID); // 密钥
        $WaitSign = md5('MemberID=' . $MemberID . $MARK . 'TerminalID=' . $TerminalID . $MARK . 'TransID=' . $TransID . $MARK . 'Result=' . $Result . $MARK . 'ResultDesc=' . $ResultDesc . $MARK . 'FactMoney=' . $FactMoney . $MARK . 'AdditionalInfo=' . $AdditionalInfo . $MARK . 'SuccTime=' . $SuccTime . $MARK . 'Md5Sign=' . $Md5key);
        if ($Md5Sign == $WaitSign) {
            $this->EditMoney($TransID, 'Baofoo', 0);
            exit("ok");
        } else {
            echo "数据验证失败";
        }
    }
}
?>
