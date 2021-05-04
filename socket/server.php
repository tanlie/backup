<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/3
 * Time: 15:32
 */

require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use PHPSocketIO\SocketIO;

$logFile=__DIR__.'/log/workerman.log';

function checkSign($params){
  if(!$params['sign']){
     return false;
   }
   if(!$params['img_url']){
   return false;
  }	
   return true;	
}


// 创建socket.io服务端，监听3120端口
$io = new SocketIO(3308);

$io->on('workerStart', function()use($io,$logFile){
    $inner_http_worker = new Worker('http://0.0.0.0:3307');
    $inner_http_worker->onMessage = function($http_connection, $data,$logFile)use($io){
        try{
            if(!isset($_POST)) {
                return $http_connection->send('fail, $_POST  not found');
            }
            $params = $_POST;
            checkSign($params);
            $json=json_encode($_POST);
            file_put_contents(__DIR__.'/log/log-'.date('Y-m-d').'.log','['.date('Y-m-d H:i:s').']'.$json."\n",FILE_APPEND);
            $io->emit('chat', $json);
            $http_connection->send('ok');

        }catch(\Exception $e){
            error_log($e->getMessage(), 3, $logFile);
            throw $e;
        }
    };
    $inner_http_worker->listen();
});

// 当有客户端连接时
$io->on('connection', function($socket)use($io){
    // 定义chat message事件回调函数
    $socket->on('chat', function($msg)use($io){
        try{
        // 触发所有客户端定义的chat message from server事件
        $io->emit('chat message from server', $msg);
        }catch(\Exception $e){
            error_log($e->getMessage(), 3, $logFile);
            throw $e;
        }
    });
});
Worker::$logFile = $logFile;


Worker::runAll();


