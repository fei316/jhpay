<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-12-29
 * Time: 上午1:31
 */
function huoqutktype()
{
    $Tikuanconfig = M("Tikuanconfig");
    $tktype = $Tikuanconfig->where(["websiteid" => session("websiteid") , "userid" => 0])->getField("tktype");
    if ($tktype == 1) {
        $tktypestr = "单笔";
    } else {
        $tktypestr = "比例";
    }
    return $tktypestr;
}

function getinviteconfigzt($id)
{
    $Invitecode = M("Invitecode");
    $list = $Invitecode->where(["id" => $id])->find();
    $inviteconfigzt = $list["inviteconfigzt"];
    $yxdatetime = $list["yxdatetime"];
    switch ($inviteconfigzt) {
        case 0:
            return '<span style="color:#F00;">禁用</span>';
            break;
        case 1:
            if (time() < $yxdatetime) {
                return '可以使用';
            } else {
                return '<span style="color:#06C">已过期</span>';
            }
            
            break;
        case 2:
            return '<span style="color:#060;">已使用</span>';
            break;
    }
}

/**
 * description: 递归菜单
 * @param unknown $array
 * @param number $fid
 * @param number $level
 * @param number $type 1:顺序菜单 2树状菜单
 * @return multitype:number
 */
function get_column($array,$type=1,$fid=0,$level=0)
{
    $column = [];
    if($type == 2)
        foreach($array as $key => $vo){
            if($vo['pid'] == $fid){
                $vo['level'] = $level;
                $column[$key] = $vo;
                $column [$key][$vo['id']] = get_column($array,$type=2,$vo['id'],$level+1);
            }
        }else{
        foreach($array as $key => $vo){
            if($vo['pid'] == $fid){
                $vo['level'] = $level;
                $column[] = $vo;
                $column = array_merge($column, get_column($array,$type=1,$vo['id'],$level+1));
            }
        }
    }

    return $column;
}