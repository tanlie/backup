<?php

require_once './vendor/autoload.php';
use Workerman\Worker;

function createSign($params,$key="12345678"){
   // $key = "12345678";
    ksort($params);
    $str = '';
    foreach($params as $k => $v){
        if($k != "sign" && !is_array($v)){
            $str .= $k."=".$v."&";
        }
        if(is_array($v)){
            $temp = json_encode($v);
            $str .= $k."=".$temp."&";
        }
    }
    $string = $str."key=".$key;
    $string = preg_replace('# #','',$string);
    $sign = md5($string);
    return $sign;
}

//开4001端口给大屏机
$tcp_worker = new Worker("tcp://0.0.0.0:3309");
$tcp_worker->count = 1;
$tcp_worker->onWorkerStart = function() use($tcp_worker){

    $inner_http_worker = new Worker("http://0.0.0.0:3310"); //开个4002端口，向uid的页面推送数据
    $inner_http_worker->onMessage = function($connection, $buffer)   //$buff 是http post 过来的东西
    {
        global $tcp_worker;
        $uid = $_POST['device_id'];
        file_put_contents('receive_http_msg',json_encode($_POST),FILE_APPEND);
        if(!$uid){
            $out['errCode'] = 202;
            $out['errMsg'] = 'no device_id, no push';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign; 
            file_put_contents('http_back',json_encode($out),FILE_APPEND);
            $connection->send(json_encode($out));   // 返回推送结果
            $connection->close();
            return; 
        }

        if(!$_POST['sign']){
            $out['errCode'] = 202;
            $out['errMsg'] = 'no sign, no push';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign; 
	    file_put_contents('http_back',json_encode($out),FILE_APPEND);	
            $connection->send(json_encode($out));   // 返回推送结果
	    $connection->close();
            return;     
        }

       // $sign = createSign($_POST,'6a204bd89f3c8348afd5c77c717a097a');
        //if($sign != $_POST['sign']){
        //    $out['errCode'] = 202;
        //    $out['errMsg'] = 'sign error, no push';
        //    $out['timestamp'] = time();
        //    $sign = createSign($out);
        //    $out['sign'] = $sign; 
        //    file_put_contents('http_back',json_encode($out),FILE_APPEND);
        //    $connection->send(json_encode($out));   // 返回推送结果
	//    $connection->close();
        //    return;
       // }

        if(!$_POST['timestamp']){
            $out['errCode'] = 202;
            $out['errMsg'] = 'no timestamp, no push';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign;
            file_put_contents('http_back',json_encode($out),FILE_APPEND); 
            $connection->send(json_encode($out));   // 返回推送结果
	    $connection->close();
            return;  
        }

        $now = time();
        if(($now - (int)$_POST['timestamp']) > 3600){
            $out['errCode'] = 202;
            $out['errMsg'] = ' over 3600 seconds, no push';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign; 
            file_put_contents('http_back',json_encode($out),FILE_APPEND);
            $connection->send(json_encode($out));   // 返回推送结果
	    $connection->close();	
            return;
        }

        $ret = sendMessageByUid($uid,json_encode($_POST)); //通过workerman，向uid的页面推送数据
        $out['errCode'] = $ret? 0 : 202;
        $out['errMsg'] = $ret? 'success': 'fail';
        $out['timestamp'] = time();
        $out['device_id'] = $_POST['device_id'];
        $sign = createSign($out);
        $out['sign'] = $sign; 
         file_put_contents('http_back',json_encode($out),FILE_APPEND);
        $connection->send(json_encode($out));   // 返回推送结果
	$connection->close();
    };

    $inner_http_worker->listen();
};  



// 新增加一个属性，用来保存uid到connection的映射
$tcp_worker->uidConnections = array();


$tcp_worker->onMessage = function($connection,$data) use ($tcp_worker){
        file_put_contents('receive_tcp_msg',$data,FILE_APPEND);
        $data = json_decode($data,true); //接收硬件推送过来的信息
        if(!$data['device_id']){
	    $out['cmd'] = $data['cmd'];    
	    $out['errCode'] = 400;
            $out['device_id'] = $data['device_id'];
            $out['errMsg'] ='no device_id,no in';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign;
	    $connection->send(json_encode($out));
	    file_put_contents('htcp_back',json_encode($out));
	    sleep(1);
	    $connection->close();
           return;	
	}        

	$sign = createSign($data,"6a204bd89f3c8348afd5c77c717a097a");          
	//if($sign != $data["sign"]){
	//    $out['cmd'] = $data['cmd'];
	//    $out['errCode'] = 400;
        //    $out['device_id'] = $data['device_id'];
        //    $out['errMsg'] ='sign error';
        //    $out['timestamp'] = time();
        //    $sign = createSign($out);
        //    $out['sign'] = $sign;
        //    $connection->send(json_encode($out));
	//    file_put_contents('htcp_back',json_encode($out));
	//    sleep(1);
	//    $connection->close();
	 //  return;
	//}	

         $connection->lastMessageTime = time();
        if(!isset($connection->uid)){
            $connection->uid = $data['device_id'];
            $tcp_worker->uidConnections[$connection->uid] = $connection;//保存连接
            $out['errCode'] = 0;
	    $out['cmd'] = $data['cmd'];	
            $out['device_id'] = $data['device_id'];
            $out['errMsg'] ='save new connection success';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign; 
             file_put_contents('htcp_back',json_encode($out));
	     $connection->send(json_encode($out)); 
            return;
        } else {
            $another_connection = $tcp_worker->uidConnections[$data['device_id']];
            $out['errCode'] = 0;
	    $out['cmd'] = $data['cmd'];	
            $out['device_id'] = $data['device_id'];
            $out['errMsg'] ='receive old connection success';
            $out['timestamp'] = time();
            $sign = createSign($out);
            $out['sign'] = $sign; 
            $another_connection->send(json_encode($out)); //给硬件返回结果
	     file_put_contents('htcp_back',json_encode($out));
            return;
        }
};






$tcp_worker->onConnect = function($connection){
    $out['cmd'] = 100;
    $out['errCode'] = 0;
    $out['errMsg'] = 'socket connected';
    $out['timestamp'] = time();
    $out['data'] = [];
    $str = "cmd=100&data=&errCode=0&errMsg=socket connected&timestamp=".$out['timestamp']."&key=123456789";
    $out['sign'] = md5($str);
   // echo 'new comming in';
   // $connection->send(json_encode($out));
};



// 当有客户端连接断开时
$tcp_worker->onClose = function($connection)use($tcp_worker) {
    global $tcp_worker;
    if (isset($connection->uid)) {
        // 连接断开时删除映射
        unset($tcp_worker->uidConnections[$connection->uid]);
    }
};


$tcp_worker->listen();

function sendMessageByUid($uid,$message)
{
    global $tcp_worker;
    if(isset($tcp_worker->uidConnections[$uid])){
        $connection = $tcp_worker->uidConnections[$uid];
        $connection->send($message);
        return true;
    } else {
        return false;
    }
}



Worker::runAll();
                  
