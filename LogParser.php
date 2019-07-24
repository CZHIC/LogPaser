<?php
define("SERVER_ROOT", dirname(__FILE__));
define("APP_ROOT", SERVER_ROOT.'/../app/');
define('LOG_ROOT', SERVER_ROOT.'/logs/');
define('DATA_ROOT', '/data/agentserver/data/');
//处理进程数
define('PARSER_PROCESS', 5);
//数据缓存条数
define('DATA_CACHE_COUNT', 10);

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


class LogParser
{
       
    const STOP    = 'STOP';
    const RUNNING = 'RUNNING';

    // 子进程 id
    private $children = array();

    // 启动时间
    private $startTime = false;

    // 进程状态
    private $status = self::RUNNING;

    // 保存进程的文件
    private $pidFile = '';
    
    private $processNum = 0;
    
    private $dataFile = null;
    
    private $fileProcess = false;

    private $svrid = array(
        '127.0.0.1' => 5,
    );
    
    function __construct($dataFile)
    {
        
        if (!extension_loaded("pcntl")) {
            die("Mpass require pcntl extension loaded");
        }

        /** assure run in cli mode */
        if (substr(php_sapi_name(), 0, 3) !== 'cli') {
            die("This Programe can only be run in CLI mode");
        }
        
        $this->dataFile = $dataFile;
        $this->pidFile = SERVER_ROOT.'/PHP_LogParser.pid';
        
        if (version_compare(phpversion(), "5.3.0", "lt")) {
            /* tick use required as of PHP 4.3.0 */
            declare(ticks = 1);
        }
    }

    function __destruct()
    {
    }

    /**
     * 监控主进程需要做的事情,内部调用
     */
    private function _monitor()
    {
        while (true) {
            $pid = @pcntl_wait($status, WNOHANG);
            if ($this->status === self::STOP) {
                if (count($this->children) <= 0) {
                    unlink($this->pidFile);
                    break;
                }

                if ($pid > 0 && array_key_exists($pid, $this->children)) {
                    unset($this->children[$pid]);
                }

                if (count($this->children) > 0) {
                    //$this->ping();
                }
            } else {
                if ($pid > 0 && array_key_exists($pid, $this->children)) {
                    if ($pid == $this->fileProcess) {
                        $this->fileProcess();
                    } else {
                        $this->parserProcess($this->children[$pid]);
                    }
                    unset($this->children[$pid]);
                } else {
                    sleep(2);
                    $this->monitor();
                }
            }
            
            if (version_compare(phpversion(), "5.3.0", "ge")) {
                /** since php 5.3.0 declare statement is deprecated,
                 *  use pcntl_signal_dispatch instead */
                pcntl_signal_dispatch();
            }
        }
    }

    /**
     * 处理 Server 外部的一些命令
     */
    public function run($argv)
    {
        // 需要验证 ip 和端口的合法性
        $param = $this->analyseParam($argv);
        switch ($param['cmd']) {
            case 'start':
                $this->start($param);
                break;
            case 'stop':
                $this->stop($param);
                break;
            default:
                Logger::err('unrecognized parameter');
                exit("unrecognized parameter.\n");
        }
    }

    /**
     * 启动
     *
     * @param param
     */
    private function start()
    {
        if (file_exists($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
            if (file_exists(SERVER_ROOT.'/PHP_LogParser.pid')) {
                Logger::err('server is runing');
                exit("server is runing.\n");
            }
            unlink($this->pidFile);
        }
        $this->init();
        $this->_run();
        $this->_monitor();
    }

    /**
     * 停止 server 的运行
     *
     * @param param
     */
    private function stop($param)
    {
        $pid = file_get_contents($this->pidFile);
        if ($pid === false) {
            Logger::err("stop fail. can not find pid file {$this->pidFile}");
            exit("error: stop fail. can not find pid file {$this->pidFile}\n");
        }

        if (!empty($param['method']) && $param['method'] == '-f') {
            $ret = posix_kill($pid, SIGUSR2);
        } else {
            $ret = posix_kill($pid, SIGUSR1);
        }
        

        if ($ret) {
            Logger::info("stop server succ");
            exit("succ: stop server succ.\n");
        }

        Logger::err("stop server fail");
        exit("error: stop server fail.\n");
    }

    /**
     * 初始化主进程
     */
    private function init()
    {
        $pid = pcntl_fork();
        // 创建子进程失败
        if ($pid == -1) {
            Logger::err("start server fail. can not create process");
            exit("error: start server fail. can not create process.\n");
        } elseif ($pid > 0) {
            exit(0);
        }

        posix_setsid();
        $pid = pcntl_fork();
        // 创建子进程失败
        if ($pid == -1) {
            Logger::err("start server fail. can not create process", self::FATAL);
            exit("error: start server fail. can not create process.\n");
        } elseif ($pid > 0) {
            exit(0);
        }

        pcntl_signal(SIGHUP, SIG_IGN);
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_signal(SIGTTIN, SIG_IGN);
        pcntl_signal(SIGTTOU, SIG_IGN);
        pcntl_signal(SIGQUIT, SIG_IGN);

        $r = pcntl_signal(SIGUSR1, array($this, "signalHandle"));
        if ($r == false) {
            Logger::err("install SIGUSR1 fail");
            exit("error: install SIGUSR1 fail.\n");
        }

        $r = pcntl_signal(SIGUSR2, array($this, "signalHandle"));
        if ($r == false) {
            Logger::err("install SIGUSR2 fail");
            exit("error: install SIGUSR2 fail.\n");
        }

        $pid = posix_getpid();
        $r = file_put_contents($this->pidFile, $pid);
        if ($r <= 0) {
            Logger::err("can not write pid file($this->pidFile)");
            exit("error: can not write pid file($this->pidFile).\n");
        }
    }
    
    private function fileProcess()
    {
        /*创建导入文件进程*/
        $pid = pcntl_fork();
        if ($pid == -1) {
            Logger::err("can not create process");
        } elseif ($pid == 0) {
            pcntl_signal(SIGUSR1, SIG_IGN);
            $this->children   = false;
            $this->pidFile    = false;
            $this->processNum = false;
            Logger::clear();
            while ($this->status === self::RUNNING) {
                $this->dataFile->importFile();
                sleep(10);
            }
            exit(0);
        }
        Logger::info('File Process Create Success Pid:'.$pid);
        $this->fileProcess = $pid;
        $this->children[$pid] = time();
    }
    
    private function _run()
    {
        $this->fileProcess();
                
        for ($this->processNum = 0; $this->processNum < PARSER_PROCESS; $this->processNum++) {
            $this->parserProcess($this->processNum);
        }
        $this->processNum--;
    }

    /**
     * 处理请求
     */
    private function parser($pno)
    {
        while ($this->status === self::RUNNING) {
            $datas = $this->dataFile->getFile($pno);
            foreach ($datas as $data) {
                $handle = @fopen($data['file_name'], 'r');
                if ($handle) {
                    while (($buffer = fgets($handle)) !== false) {
                        $temparr = json_decode($buffer);
                        if (!$temparr) {
                            continue;
                        }
                        
                        array_unshift($temparr, $this->svrid[$data['server_ip']]);
                        //$temparr[] =  $this->svrid[$data['server_ip']];
                        $buffer = json_encode($temparr);
                        //echo $buffer."\n";
                        if ($buffer = trim($buffer)) {
                            $file =  LOG_ROOT.'mysql_error.log';
                            file_put_contents($file, "{$buffer}\n", FILE_APPEND);
                            Parser::save($buffer);
                        }
                    }
                    if (!feof($handle)) {
                        echo "Error: unexpected fgets() fail\n";
                    }
                    Parser::save(null);
                    fclose($handle);
                    $this->dataFile->fileParserComplete($data['file_name']);
                }
                sleep(5);
            }
            sleep(5);
        }
    }

    /**
     * 监控主进程需要做的事情
     */
    public function monitor()
    {
        //echo 'i\'m parent'."\n";
    }

    /**
     * 创建一个进程
     */
    private function parserProcess($pno)
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            Logger::err("can not create process");
        } elseif ($pid == 0) {
            pcntl_signal(SIGUSR1, SIG_IGN);
            $this->children   = false;
            $this->pidFile    = false;
            $this->processNum = false;
            Logger::clear();
            $this->parser($pno);
            exit(0);
        }
        Logger::info('Parser Process Create Success Pid:'.$pid);
        $this->children[$pid] = $pno;
    }

    /**
     * 处理信号量
     * @param signo    接收到的信号
     */
    public function signalHandle($signo)
    {
        switch ($signo) {
            case SIGUSR1:
                $this->status = self::STOP;
                if (empty($this->children)) {
                    break;
                }
                foreach ($this->children as $k => $v) {
                    posix_kill($k, SIGUSR2);
                }
                break;
            case SIGUSR2:
                $this->status = self::STOP;
                if (empty($this->children)) {
                    break;
                }
                foreach ($this->children as $k => $v) {
                    posix_kill($k, SIGKILL);
                }
                break;
        }
    }


    /**
     * 分析参数
     */
    private function analyseParam($argv)
    {
        $opts = getopt('s:f');
        $tip = "usage: $argv[0] -s start|stop [-f]\n";
        $param = array();
        $param['cmd'] = isset($opts['s']) ? strtolower($opts['s']) : 'start';
        if ($param['cmd'] != 'stop' && $param['cmd'] != 'start') {
            exit($tip);
        }
        if (isset($opts['f'])) {
            $param['method'] = '-f';
        }
        return $param;
    }
}


$server = new LogParser(new DataFile());

$server->run($argv);
