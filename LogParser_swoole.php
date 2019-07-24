<?php
/**
 * 多进程swoole处理
 * PHP version 7
 * swoole 4.3
 *
 * @category Null
 * @package  Swoole
 * @author   Display NAme <chuzhichao@yiihua.com>
 * @license  www.yiihua.com chuzhchao
 * @link     www.yiihua.com
 */


define("SERVER_ROOT", dirname(__FILE__));
define("APP_ROOT", SERVER_ROOT.'/../app/');
define('LOG_ROOT', SERVER_ROOT.'/logs/');
define('DATA_ROOT', '/data/agentserver/data/');
//数据缓存条数
define('DATA_CACHE_COUNT', 10);
//处理进程数
define('PARSER_PROCESS', 5);//用于对入库的文件进行分组

define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_USER', 'root');
define('MYSQL_PSW', '123456');
define('MYSQL_DB', 'statistics');

require_once 'Logger.php';
require_once 'MySQLDB.php';
require_once 'DataFile.php';
require_once 'Parser/Parser.php';


date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL ^ E_NOTICE);
set_time_limit(0);


$svrid = array(
        '127.0.0.1' => 5,
    );
$DataFile  = new DataFile();

$workerNum = 6;
$pool = new Swoole\Process\Pool($workerNum);

//使当前进程蜕变为一个守护进程。
Swoole\Process::daemon($nochdir = true, $noclose = true);

$pool->on(
    "WorkerStart",
    function ($pool, $workerId) {
        sleep(10);
        if ($workerId ==5) {  //处理文件
            global $DataFile;
            $DataFile->importFile();
        } else {
            global $DataFile;
            global $svrid;
            $datas = $DataFile->getFile($workerId); // 根据进程id查询未处理文件，避免重复处理
            foreach ($datas as $data) {
                $handle = @fopen($data['file_name'], 'r');
                if ($handle) {
                    while (($buffer = fgets($handle)) !== false) {
                        $temparr = json_decode($buffer);
                        if (!$temparr) {
                            continue;
                        }
                        array_unshift($temparr, $svrid[$data['server_ip']]);
                        $buffer = json_encode($temparr);
                        if ($buffer = trim($buffer)) {
                            Parser::save($buffer);
                        }
                    }
                    if (!feof($handle)) {
                        echo "Error: unexpected fgets() fail\n";
                    }
                    Parser::save(null);
                    fclose($handle);
                    $DataFile->fileParserComplete($data['file_name']);
                }
            }
        }
        // echo "Worker#{$workerId} is started\n";
    }
);

$pool->on(
    "WorkerStop",
    function ($pool, $workerId) {
        // echo "Worker#{$workerId} is stopped\n";
    }
);

$pool->start();
