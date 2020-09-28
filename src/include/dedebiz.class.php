<?php if(!defined('DEDEINC')) exit('Request Error!');
// Copyright 2020 The DedeBiz Authors. All rights reserved.
// license that can be found in the LICENSE file.

// 本文件为DedeCMS商业组件(www.dedebiz.com)PHP SDK
// 目的是弥补织梦内容管理系统（DedeCMS）性能和安全方面的不足，提供更多功能

define("DEDEBIZ", true);

// 本文件用于和DedeBiz商业组件进行通信，以获取更多额外的扩展功能
class DedeBizClient
{
    var $socket;
    var $appid;
    var $key;

    function __construct($ipaddr, $port)
    {
        if (!function_exists("socket_create")) {
            echo json_encode(array(
                "code" => -1,
                "data" => null,
                "msg" => "请在php.ini开启extension=sockets",
            ));
            exit;
        }
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $rs = @socket_connect($this->socket, $ipaddr, $port);
        if (!$rs) {
            echo json_encode(array(
                "code" => -1,
                "data" => null,
                "msg" => "连接DedeBiz商业组件服务失败\r\n",
            ));
            exit;
        }
    }

    function request(&$req){
        // 进行签名
        $this->MakeSign($req);
        $str = json_encode($req);
        $length = strlen($str);
        $s = socket_write($this->socket, $str, $length);

        if (!$s) {
            echo json_encode(array(
                "code" => -1,
                "data" => null,
                "msg" => "连接DedeBiz商业组件服务失败\r\n",
            ));
            exit;
        }

        $msg = "";
        while(($str = socket_read($this->socket, 1024)) !== FALSE){
            $msg .= $str;
            if (strlen($str) < 1024) {
                break;
            }
        }
        return $this->CheckSign($msg);
    }

    // 用户获取当前服务器状态信息
    function SystemInfo(){
        $req = array(
            "method" => "system_info",
        );
        return $this->request($req);
    }

    // 检测是否连接
    function Ping($i)
    {
        $req = array(
            "method" => "ping",
            "parms" => array(
                "name" => "www.dedebiz.com",
            )
        );
        return $this->request($req);
    }

    // 获取一个管理员信息
    function AdminGetOne()
    {
        $req = array(
            "method" => "admin_get_one",
            "parms" => array(
                "name" => "admin",
            )
        );
        return $this->request($req);
    }

    // 拼接规则就是method+
    function MakeSign(&$req)
    {
        if (empty($req['timestamp'])) {
            $req['timestamp'] = time();
        }
        if (isset($req['parms']) && count($req['parms']) > 0) {
            ksort($req['parms']);
        }
        
        $pstr = "appid={$this->appid}method={$req['method']}key={$this->key}";
        if (isset($req['parms']) && count($req['parms']) > 0) {
            foreach ($req['parms'] as $key => $value) {
                $pstr .= "$key=$value";
            }
        }

        $pstr .= "timestamp={$req['timestamp']}";
        $req['sign'] = hash("sha256", $pstr);
    }

    // 校验返回数据是否正确
    function CheckSign(&$msg)
    {
        $rsp = json_decode($msg);
        if (!is_object($rsp)) {
            return null;
        }
        $str = sprintf("appid=%skey=%scode=%dmsg=%sdata=%stimestamp=%d", $this->appid, $this->key, $rsp->code, $rsp->msg, $rsp->data, $rsp->timestamp);
        if (hash("sha256", $str) === $rsp->sign) {
            return $rsp;
        } else {
            return null;
        }
    }

    // 关闭通信接口
    // ！！！一次页面操作后一定记得要关闭连接，否则会占用系统资源
    function Close()
    {
        socket_close($this->socket);
    }
}