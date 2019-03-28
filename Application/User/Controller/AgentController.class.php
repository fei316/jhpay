<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-08-22
 * Time: 14:34
 */
namespace User\Controller;

use Think\Page;

/** 商家代理控制器
 * Class DailiController
 * @package User\Controller
 */
class AgentController extends UserController
{

    public function __construct()
    {
        parent::__construct();
        if($this->fans['groupid'] == 4) {
            $this->error('没有权限！');
        }
    }
    /**
     * 邀请码
     */
    public function invitecode()
    {
        if(!$this->siteconfig['invitecode']) {
            $this->error('邀请码功能已关闭');
        }
        $invitecode = I("get.invitecode");
        $syusername = I("get.syusername");
        $status     = I("get.status");
        if (!empty($invitecode)) {
            $where['invitecode'] = ["like", "%" . $invitecode . "%"];
        }
        if (!empty($syusername)) {
            $syusernameid          = M("Member")->where(['username' => $syusername])->getField("id");
            $where['syusernameid'] = $syusernameid;
        }
        $regdatetime = urldecode(I("request.regdatetime"));
        if ($regdatetime) {
            list($cstime, $cetime) = explode('|', $regdatetime);
            $where['fbdatetime']   = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        $where['fmusernameid'] = $this->fans['uid'];
        $count                 = M('Invitecode')->where($where)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page                  = new Page($count, $rows);
        $list                  = M('Invitecode')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['groupname'] = $this->groupId[$v['regtype']];
        }

        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 添加邀请码
     */
    public function addInvite()
    {
        if(!$this->siteconfig['invitecode']) {
            $this->error('邀请码功能已关闭');
        }
        $invitecode = $this->createInvitecode();
        $this->assign('invitecode', $invitecode);
        $this->assign('datetime', date('Y-m-d H:i:s', time() + 86400));
        $this->display();
    }

    /**
     * 邀请码
     * @return string
     */
    private function createInvitecode()
    {
        if(!$this->siteconfig['invitecode']) {
            $this->error('邀请码功能已关闭');
        }
        $invitecodestr = random_str(C('INVITECODE')); //生成邀请码的长度在Application/Commom/Conf/config.php中修改
        $Invitecode    = M("Invitecode");
        $id            = $Invitecode->where(['invitecode' => $invitecodestr])->getField("id");
        if (!$id) {
            return $invitecodestr;
        } else {
            $this->createInvitecode();
        }
    }

    /**
     * 添加邀请码
     */
    public function addInvitecode()
    {
        if (IS_POST) {
            if(!$this->siteconfig['invitecode']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '邀请码功能已关闭']);
            }
            $invitecode = I('post.invitecode');
            $yxdatetime = I('post.yxdatetime');
            $regtype    = I('post.regtype');
            $Invitecode = M("Invitecode");

            //只能添加比自己等级低的商户
            if($regtype >= $this->fans['groupid']) {
                $this->error('没有权限');
            }

            $_formdata  = array(
                'invitecode'     => $invitecode,
                'yxdatetime'     => strtotime($yxdatetime),
                'regtype'        => $regtype,
                'fmusernameid'   => $this->fans['uid'],
                'inviteconfigzt' => 1,
                'fbdatetime'     => time(),
            );
            $result = $Invitecode->add($_formdata);
            $this->ajaxReturn(['status' => $result]);
        }
    }

    /**
     * 删除邀请码
     */
    public function delInvitecode()
    {
        if (IS_POST) {
            $id  = I('post.id', 0, 'intval');
            $res = M('Invitecode')->where(['id' => $id , 'fmusernameid' => $this->fans['uid'], 'is_admin' => 0])->delete();
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 下级会员
     */
    public function member()
    {
        $where['groupid'] = ['neq', 1];
        $username         = I("get.username");
        $status           = I("get.status");
        $authorized       = I("get.authorized");
        $regdatetime      = I('get.regdatetime');
        if (!empty($username) && !is_numeric($username)) {
            $where['username'] = ['like', "%" . $username . "%"];
        } elseif (intval($username) - 10000 > 0) {
            $where['id'] = intval($username) - 10000;
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        if (!empty($authorized)) {
            $where['authorized'] = $authorized;
        }
        $where['parentid'] = $this->fans['uid'];
        if ($regdatetime) {
            list($starttime, $endtime) = explode('|', $regdatetime);
            $where['regdatetime']      = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $where['parentid'] = $this->fans['uid'];
        $count             = M('Member')->where($where)->count();
        $page              = new Page($count, 15);
        $list              = M('Member')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    //导出用户
    public function exportuser()
    {
        $username   = I("get.username");
        $status     = I("get.status");
        $authorized = I("get.authorized");
        $groupid    = I("get.groupid");

        if (is_numeric($username)) {
            $map['id'] = array('eq', intval($username) - 10000);
        } else {
            $map['username'] = array('like', '%' . $username . '%');
        }
        if ($status) {
            $map['status'] = array('eq', $status);
        }
        if ($authorized) {
            $map['authorized'] = array("eq", $authorized);
        }
        $map['parentid'] = array('eq', session('user_auth.uid'));
        $regdatetime     = urldecode(I("request.regdatetime"));
        if ($regdatetime) {
            list($cstime, $cetime) = explode('|', $regdatetime);
            $map['regdatetime']    = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }

        $map['groupid'] = $groupid ? array('eq', $groupid) : array('neq', 0);

        $title = array('用户名', '商户号', '用户类型', '上级用户名', '状态', '认证', '可用余额', '冻结余额', '注册时间');
        $data  = M('Member')
            ->where($map)
            ->select();
        foreach ($data as $item) {
            switch ($item['groupid']) {
                case 4:
                    $usertypestr = '商户';
                    break;
                case 5:
                    $usertypestr = '代理商';
                    break;
            }
            switch ($item['status']) {
                case 0:
                    $userstatus = '未激活';
                    break;
                case 1:
                    $userstatus = '正常';
                    break;
                case 2:
                    $userstatus = '已禁用';
                    break;
            }
            switch ($item['authorized']) {
                case 1:
                    $rzstauts = '已认证';
                    break;
                case 0:
                    $rzstauts = '未认证';
                    break;
                case 2:
                    $rzstauts = '等待审核';
                    break;
            }
            $list[] = array(
                'username'    => $item['username'],
                'userid'      => $item['id'] + 10000,
                'groupid'     => $usertypestr,
                'parentid'    => getParentName($item['parentid'], 1),
                'status'      => $userstatus,
                'authorized'  => $rzstauts,
                'total'       => $item['balance'],
                'block'       => $item['blockedbalance'],
                'regdatetime' => date('Y-m-d H:i:s', $item['regdatetime']),
            );
        }

        $numberField = ['total'];
        exportexcel($list, $title, $numberField);
    }

    //用户状态切换
    public function editStatus()
    {
        if (IS_POST) {
            $userid   = intval(I('post.uid'));
            $member = M('Member')->where(['id'=>$userid])->find();
            if(empty($member)) {
                $this->error('用户不存在！');
            }
            if($member['parentid'] != $this->fans['uid']) {
                $this->error('您没有权限查切换该用户状态！');
            }

            $isstatus = I('post.isopen') ? I('post.isopen') : 0;
            $res      = M('Member')->where(['id' => $userid])->save(['status' => $isstatus]);
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 下级费率设置
     */
    public function userRateEdit()
    {
        //需要加载代理所有开放
        //$this->fans['uid'];
        $userid = I('get.uid', 0, 'intval');
        $member = M('Member')->where(['id'=>$userid])->find();
        if(empty($member)) {
            $this->error('用户不存在！');
        }
        if($member['parentid'] != $this->fans['uid']) {
            $this->error('您没有权限查对该用户进行费率设置！');
        }

        //系统产品列表
        $products = M('Product')
            ->join('LEFT JOIN __PRODUCT_USER__ ON __PRODUCT_USER__.pid = __PRODUCT__.id')
            ->where(['pay_product.status' => 1, 'pay_product.isdisplay' => 1, 'pay_product_user.userid' => $userid, 'pay_product_user.status' => 1])
            ->field('pay_product.id,pay_product.name,pay_product_user.status')
            ->select();
        //用户产品列表
        $userprods = M('Userrate')->where(['userid' => $userid])->select();
        if ($userprods) {
            foreach ($userprods as $item) {
                $_tmpData[$item['payapiid']] = $item;
            }
        }
        //重组产品列表
        $list = [];
        if ($products) {
            foreach ($products as $key => $item) {
                $products[$key]['t0feilv']    = $_tmpData[$item['id']]['t0feilv'] ? $_tmpData[$item['id']]['t0feilv'] : '0.000';
                $products[$key]['t0fengding'] = $_tmpData[$item['id']]['t0fengding'] ? $_tmpData[$item['id']]['t0fengding'] : '0.000';
                $products[$key]['feilv']    = $_tmpData[$item['id']]['feilv'] ? $_tmpData[$item['id']]['feilv'] : '0.000';
                $products[$key]['fengding'] = $_tmpData[$item['id']]['fengding'] ? $_tmpData[$item['id']]['fengding'] : '0.000';
            }
        }

        $this->assign('products', $products);
        $this->display();
    }
    //保存费率
    public function saveUserRate()
    {
        if (IS_POST) {
            $userid = intval(I('post.userid'));
            $member = M('Member')->where(['id'=>$userid])->find();
            if(empty($member)) {
                $this->error('用户不存在！');
            }
            if($member['parentid'] != $this->fans['uid']) {
                $this->error('您没有权限查对该用户进行费率设置！');
            }
            $rows   = I('post.u/a');
            $datalist = [];
            foreach ($rows as $key => $item) {
                $agent_rate = M('Userrate')->where(['userid' => $this->fans['uid'], 'payapiid' => $key])->find();
                if($item['feilv'] < $agent_rate['feilv']) {
                    $this->ajaxReturn(['status' => 0, 'msg'=> 'T+1费率不能低于代理成本！']);
                }
                if($item['t0feilv'] < $agent_rate['t0feilv']) {
                    $this->ajaxReturn(['status' => 0, 'msg'=> 'T+0费率不能低于代理成本！']);
                }
                $rates = M('Userrate')->where(['userid' => $userid, 'payapiid' => $key])->find();
                if ($rates) {
                    $datalist[] = ['id' => $rates['id'], 'userid' => $userid, 'payapiid' => $key, 'feilv' => $item['feilv'], 'fengding' => $item['fengding'], 't0feilv' => $item['t0feilv'], 't0fengding' => $item['t0fengding']];
                } else {
                    $datalist[] = ['userid' => $userid, 'payapiid' => $key, 'feilv' => $item['feilv'], 'fengding' => $item['fengding'], 't0feilv' => $item['t0feilv'], 't0fengding' => $item['t0fengding']];
                }
            }
            M('Userrate')->addAll($datalist, [], true);
            $this->ajaxReturn(['status' => 1]);
        }
    }

    public function checkUserrate()
    {
        if (IS_POST) {
            $pid  = I('post.pid', 0, 'intval');
            $rate = I('post.feilv');
            $t = I('post.t', 1);
            if ($pid) {
                $field = $t == 0? 't0feilv' : 'feilv';
                $selffeilv = M('Userrate')->where(['userid' => $this->fans['uid'], 'payapiid' => $pid])->getField($field);
                if (($selffeilv * 1000) >= ($rate * 1000)) {
                    $this->ajaxReturn(['status' => 1]);
                }
            }
        }
    }
    //下级流水
    public function childord()
    {
        $userid = I('get.userid', 0, 'intval');
        if(!$userid) {
            $this->error('缺少参数！');
        }
        $member = M('Member')->where(['id'=>$userid])->find();
        if(empty($member)) {
            $this->error('用户不存在！');
        }
        if($member['parentid'] != $this->fans['uid']) {
            $this->error('您没有权限查看该用户信息！');
        }
        $userid = $userid + 10000;
        $data   = array();

        $where = array('pay_memberid' => $userid);
        //商户号
        $memberid = I("request.memberid");
        if ($memberid) {
            $where['pay_memberid'] = $memberid;
        }
        //提交时间
        $createtime = urldecode(I("request.createtime"));
        if ($createtime) {
            list($cstime, $cetime)  = explode('|', $createtime);
            $where['pay_applydate'] = $poundageMap['datetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        //成功时间
        $successtime = urldecode(I("request.successtime"));
        if ($successtime) {
            list($sstime, $setime)    = explode('|', $successtime);
            $where['pay_successdate'] = $poundageMap['datetime'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        //查询下级数据
        $where['pay_status'] = ['in', '0,1,2'];
        $statistic = M('Order')->field(['sum(`pay_amount`) pay_amount, sum(`pay_poundage`) pay_poundage, sum(`pay_actualamount`) pay_actualamount'])->where($where)->find();
        //代理分润
        $poundageMap['tcuserid'] = $userid - 10000;
        $poundageMap['userid'] = $this->fans['uid'];
        $poundageMap['lx'] = 9;
        $pay_poundage = M('moneychange')->where($poundageMap)->sum('money');
        $this->assign('pay_amount', number_format($statistic['pay_amount'], 2));
        $this->assign('pay_poundage', number_format($pay_poundage, 2));
        $this->assign('pay_actualamount', number_format($statistic['pay_actualamount'], 2));

        //分页
        $count = M('Order')->where($where)->count();
        $Page  = new Page($count, 10);
        $data  = M('Order')->join('LEFT JOIN __MEMBER__ ON __MEMBER__.id+10000 = __ORDER__.pay_memberid')->where($where)->field('pay_order.*, pay_member.username')->limit($Page->firstRow . ',' . $Page->listRows)->order(['id' => 'desc'])->select();
        $show  = $Page->show();
        $this->assign('list', $data);
        $this->assign('page', $show);
        $this->display();
    }

    public function addUser()
    {
        $this->display();
    }

    /**
     * 生成用户
     */
    public function saveUser()
    {
        $u             = I('post.u/a');
        $u['username'] = trim($u['username']);
        $u['email'] = trim($u['email']);
        $u['birthday'] = strtotime($u['birthday']);

        $has_user = M('member')->where(['username' => $u['username'], 'email' => $u['email'], '_logic' => 'or'])->find();
        if ($has_user) {
            if ($has_user['username'] == $u['username']) {
                $this->ajaxReturn(array("status" => 0, "msg" => '用户名已存在'));
            }
            if ($has_user['email'] == $u['email']) {
                $this->ajaxReturn(array("status" => 0, "msg" => '邮箱已存在'));
            }
        }
        $current_user = session('user_auth');
        $siteconfig   = M("Websiteconfig")->find();
        $u            = generateUser($u, $siteconfig);

        $s['activatedatetime'] = date("Y-m-d H:i:s");
        $u['parentid']         = $current_user['uid'];
        //$u['groupid'] = $current_user['groupid'];

        // 创建用户
        $res = M('Member')->add($u);

        // 发邮件通知用户密码
        sendPasswordEmail($u['username'], $u['email'], $u['origin_password'], $siteconfig);

        $this->ajaxReturn(['status' => $res]);
    }

    /**
     * 下级商户订单
     */
    public function order()
    {
        $where['groupid'] = ['neq', 1];
        $createtime      = urldecode(I('get.createtime'));
        $successtime = urldecode(I("request.successtime"));
        $memberid = I("request.memberid");
        $body = I("request.body");
        $orderid = I("request.orderid");
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        $this->assign('memberid', $memberid);
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        $this->assign('orderid', $orderid);
        if ($createtime) {
            list($starttime, $endtime) = explode('|', $createtime);
            $where['pay_applydate']      = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $this->assign('createtime', $createtime);
        if ($successtime) {
            list($starttime, $endtime) = explode('|', $successtime);
            $where['pay_successdate']     = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $this->assign('successtime', $successtime);
        if ($body) {
            $where['pay_productname'] = array('eq', $body);
        }
        $this->assign('body', $body);
        /*
        $status = I("request.status",0,'intval');
        if ($status) {
            $where['pay_status'] = array('eq',$status);
        }
        */
        $where['pay_status'] = array('in','0,1,2');
        $pay_memberid = [];
        $user_id = M('Member')->where(['parentid'=>$this->fans['uid']])->getField('id', true);
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        if($user_id) {
            foreach($user_id as $k => $v) {
                array_push($pay_memberid, $v+10000);
            }
            if(!$createtime and !$successtime) {
                //今日成功交易总额
                $todayBegin = date('Y-m-d').' 00:00:00';
                $todyEnd = date('Y-m-d').' 23:59:59';
                $stat['todaysum'] = M('Order')->where(['pay_memberid'=>['in', $pay_memberid],'pay_successdate'=>['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status'=>['in', '1,2']])->sum('pay_amount');
                //今日成功笔数
                $stat['todaysuccesscount'] = M('Order')->where(['pay_memberid'=>['in', $pay_memberid],'pay_successdate'=>['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status'=>['in', '1,2']])->count();
                //总成功交易总额
                $totalMap['pay_memberid'] = ['in', $pay_memberid];
                $totalMap['pay_status'] = ['in', '1,2'];
                $stat['totalsum'] = M('Order')->where($totalMap)->sum('pay_amount');
                //总成功笔数
                $stat['totalsuccesscount'] = M('Order')->where($totalMap)->count();
                foreach($stat as $k => $v) {
                    $stat[$k] = $v+0;
                }
                $this->assign('stat', $stat);
            }
            if($memberid) {
                if(in_array($memberid, $pay_memberid)) {
                    $where['pay_memberid'] = $memberid;
                } else {
                    $where['pay_memberid'] = 1;
                }
            } else {
                $where['pay_memberid'] = ['in', $pay_memberid];
            }
            //如果指定时间范围则按搜索条件做统计
            if ($createtime || $successtime) {
               
              $sumMap = $where;
                $field                    = ['sum(`pay_amount`) pay_amount', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'];
                $sum                      = M('Order')->field($field)->where($sumMap)->find();
                foreach ($sum as $k => $v) {
                    $sum[$k] += 0;
                }
                $this->assign('sum', $sum);
            }
            //分页
            $count = M('Order')->where($where)->count();
            $Page  = new Page($count, $rows);
            $data  = M('Order')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order(['id' => 'desc'])->select();
        } else {
            $stat['todaysum'] = $stat['todaysuccesscount'] = $stat['totalsum'] = $stat['totalsuccesscount'] = 0;
            $count = 0;
            $Page  = new Page($count, $rows);
            $data = [];
        }
        $show  = $Page->show();
        $this->assign('list', $data);
        $this->assign('page', $show);
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 导出交易订单
     * */
    public function exportorder()
    {

        $where['groupid'] = ['neq', 1];
        $createtime      = urldecode(I('get.createtime'));
        $successtime = urldecode(I("request.successtime"));
        $memberid = I("request.memberid");
        $body = I("request.body", '', 'strip_tags');
        $orderid = I("request.orderid");
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        if ($createtime) {
            list($starttime, $endtime) = explode('|', $createtime);
            $where['pay_applydate']      = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        if ($successtime) {
            list($starttime, $endtime) = explode('|', $successtime);
            $where['pay_successdate']     = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        if ($body) {
            $where['pay_productname'] = array('eq', $body);
        }
        $status = I("request.status",0,'intval');
        if ($status) {
            $where['pay_status'] = array('eq',$status);
        }
        $where['pay_status'] = array('in','1,2');
        $pay_memberid = [];
        $user_id = M('Member')->where(['parentid'=>$this->fans['uid']])->getField('id', true);
        if($user_id) {
            foreach($user_id as $k => $v) {
                array_push($pay_memberid, $v+10000);
            }
            if($memberid) {
                if(in_array($memberid, $pay_memberid)) {
                    $where['pay_memberid'] = $memberid;
                } else {
                    $where['pay_memberid'] = 1;
                }
            } else {
                $where['pay_memberid'] = ['in', $pay_memberid];
            }
            $data  = M('Order')->where($where)->order(['id' => 'desc'])->select();
        } else {
            $data = [];
        }
        $title = array('订单号','商户编号','交易金额','手续费','实际金额','提交时间','成功时间','支付通道','支付状态');
        foreach ($data as $item){

            switch ($item['pay_status']){
                case 0:
                    $status = '未处理';
                    break;
                case 1:
                    $status = '成功，未返回';
                    break;
                case 2:
                    $status = '成功，已返回';
                    break;
            }
            $list[] = array(
                'pay_orderid'=>$item['out_trade_id'] ? $item['out_trade_id']:$item['pay_orderid'],
                'pay_memberid'=>$item['pay_memberid'],
                'pay_amount'=>$item['pay_amount'],
                'pay_poundage'=>$item['pay_poundage'],
                'pay_actualamount'=>$item['pay_actualamount'],
                'pay_applydate'=>date('Y-m-d H:i:s',$item['pay_applydate']),
                'pay_successdate'=>date('Y-m-d H:i:s',$item['pay_successdate']),
                'pay_bankname'=>$item['pay_bankname'],
                'pay_status'=>$status,
            );
        }
        $numberField = ['pay_amount', 'pay_poundage', 'pay_actualamount'];
        exportexcel($list, $title, $numberField);
    }
}
