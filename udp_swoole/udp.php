<?php
// [11211,1001,1,0,1559555403,0,0,0] 
// 统计项， 渠道 ， 值 ， 加减动作（0加1减2赋值） ，时间戳 ， 用户uid, 注册时间 ， sappid



include_once "DataFile.php";

$__Table = new Swoole\Table(1024);   //内存表
$__Table->column('content', swoole_table::TYPE_STRING, 200);       //1,2,4,8
$__Table->create();

$__atomic = new Swoole\Atomic(); // 计数器

$df = new DataFile();



$serv = new swoole_server("0.0.0.0", 9503, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set(array(
            'worker_num'=>1,
            'daemonize'=>0,
));
$serv->on('Start', 'onStart');
$serv->on('packet', 'onPacket');

$serv->start();
    
function onStart($serv)
{
    echo "Start\n";
}

function onPacket(swoole_server $serv, $data, $clientInfo)
{
    global $df ,$__atomic;
    $__atomic->add(1);
    $num = $__atomic->get();
    if($num > 10000000) {
        $__atomic->set(0);
    }
    $df->save($data);
    $serv->sendto($clientInfo['address'], $clientInfo['port'], "Server ".$data);
}


