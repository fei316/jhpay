<?php

namespace Pay\Controller;

use Org\Util\HttpClient;

class UPALIWAPController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function Pay($array)
    {
        $parameter = array
        (
            'code' => 'UPALIWAP', // 通道名称
            'title' => 'Unpay支付宝H5',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => I("request.pay_orderid"),
            'body' => I('request.pay_productname'),
            'channel' => $array
        );
        $return = $this->orderadd($parameter);
        $customerid = $return['mch_id'];
        $apikey = $return['signkey'];
        $gateWay = "http://api.unpay.com/PayMegerHandler.ashx";
        $customerid = $customerid;
        $sdcustomno = $return['orderid'];
        $orderAmount = number_format($return['amount'] * 100, 0, ".", "");
        $cardno = "44";
        $noticeurl = $return['notifyurl'];
        $backurl = $return['callbackurl'];
        $sign = "";
        $mark = $customerid;
        $remarks = $sdcustomno;
        $zftype = "3";
        $signstr = "customerid=" . $customerid . "&sdcustomno=" . $sdcustomno . "&orderAmount=" . $orderAmount . "&cardno=" . $cardno . "&noticeurl=" . $noticeurl . "&backurl=" . $backurl . $apikey;
        $sign = strtoupper(md5($signstr));
        $posturl = $gateWay . "?customerid=" . $customerid . "&sdcustomno=" . $sdcustomno . "&orderAmount=" . $orderAmount . "&cardno=" . $cardno . "&noticeurl=" . $noticeurl . "&backurl=" . $backurl . "&sign=" . $sign . "&mark=" . $mark;
        echo '<script>location.href="' . $posturl . '";</script>';
        exit();
    }

    public function callbackurl()
    {
        $orderid = $_GET["sdcustomno"];
        $Order = M("Order");
        $pay_status = $Order->where("pay_orderid = '" . $orderid . "'")->getField("pay_status");
        if ($pay_status <> 0) {
            $this->EditMoney($orderid, 'UPALIWAP', 1);
        } else {
            exit("ok");
        }
    }

// 服务器点对点返回
    public function notifyurl()
    {
        file_put_contents(dirname(__FILE__) . '/UP_get.txt', var_export($_GET, true), FILE_APPEND);
        file_put_contents(dirname(__FILE__) . '/UP_post.txt', var_export($_POST, true), FILE_APPEND);
        $state = $_REQUEST["state"];
        $customerid = $_REQUEST["customerid"];
        $sd51no = $_REQUEST["sd51no"];
        $sdcustomno = $_REQUEST["sdcustomno"];
        $ordermoney = $_REQUEST["ordermoney"];
        $cardno = $_REQUEST["cardno"];
        $mark = $_REQUEST["mark"];
        $sign = $_REQUEST["sign"];
        $resign = $_REQUEST["resign"];
        $des = $_REQUEST["des"];
        $apikey = getKey($sdcustomno);
        $Nsign = "customerid=$customerid&sd51no=$sd51no&sdcustomno=$sdcustomno&mark=$mark&key=$apikey";
        $Nsign = strtoupper(md5($Nsign));
        $Nresign = "sign=$sign&customerid=$customerid&ordermoney=$ordermoney&sd51no=$sd51no&state=$state&key=$apikey";
        $Nresign = strtoupper(md5($Nresign));
        if ($Nsign == $sign && $Nresign == $resign) {
            echo "<result>1</result>";
            if ($state == "1") {
                $ovalue = number_format($ordermoney / 1, 2, ".", "");
                $orderid = $sdcustomno;
                $Order = M("Order");
                $pay_status = $Order->where(["pay_orderid" => $orderid])->getField("pay_status");
                if ($pay_status == 0) {
                    $rows = array(
                        'out_trade_no' => $orderid,
                        'result_code' => '1',
                        'transaction_id' => $orderid,
                        'fromuser' => '1',
                        'time_end' => date("YmdHis"),
                        'total_fee' => $ovalue,
                        'bank_type' => '1',
                        'trade_type' => 'UPALIWAP',
                        'payname' => 'UPALIWAP'
                    );
                    M('Paylog')->add($rows);
                    $this->EditMoney($orderid, 'UPALIWAP', 1);
                }
            } else {
            }
        } else {
        }
    }

    function file_writeTxt($filepath, $source)
    {
        if ($fp = fopen($filepath, 'w')) {
            $filesource = fwrite($fp, $source);
            fclose($fp);
            return $filesource;
        } else
            return false;
    }
}

?>