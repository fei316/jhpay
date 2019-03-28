<?php
namespace User\Controller;

/**
 * 支付通道控制器
 * Class ChannelController
 * @package User\Controller
 */
class ChannelController extends UserController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 通道费率
     */
    public function index()
    {
        //已开通通道
        $list = M('ProductUser')
            ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
            ->where(['pay_product_user.userid'=>$this->fans['uid'],'pay_product_user.status'=>1,'pay_product.isdisplay'=>1])
            ->field('pay_product.name,pay_product.id,pay_product_user.status')
            ->select();

        foreach ($list as $key=>$item){
            $feilv = M('Userrate')->where(['userid'=>$this->fans['uid'],'payapiid'=>$item['id']])->getField('feilv');
            $list[$key]['feilv'] = $feilv;
        }

        //结算方式：
        $tkconfig = M('Tikuanconfig')->where(['userid'=>$this->fans['uid']])->find();
        if(!$tkconfig || $tkconfig['tkzt']!=1){
            $tkconfig = M('Tikuanconfig')->where(['issystem'=>1])->find();
        }

        $this->assign('tkconfig',$tkconfig);
        $this->assign('list',$list);
        $this->display();
    }

    /**
     * 开发文档
     */
    public function apidocumnet()
    {
        if($this->fans[groupid] != 4) {
            $this->error('您没有权限访问该页面!');
        }
        $sms_is_open = smsStatus();//短信开启状态
        $info = M('Member')->where(['id'=>$this->fans['uid']])->find();
        $this->assign('sms_is_open',$sms_is_open);
        $this->assign('mobile', $this->fans['mobile']);
        $this->assign('info',$info);
        $this->display();
    }

    public function apikey()
    {
        $code = I('request.code');
        $res = check_auth_error($this->fans['uid'], 6);
        if(!$res['status']) {
            $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
        }
        $data = M('Member')->field('paypassword')->where(['id'=>$this->fans['uid']])->find();
        if(md5($code) != $data['paypassword']){
            log_auth_error($this->fans['uid'],6);
            $this->ajaxReturn(['status'=>0,'msg'=>'支付密码错误']);
        } else {
            clear_auth_error($this->fans['uid'],6);
        }
        $apikey = M('Member')->where(['id'=>$this->fans['uid']])->getField('apikey');
        $this->ajaxReturn(['status' => 1, 'apikey' => $apikey]);
    }

}