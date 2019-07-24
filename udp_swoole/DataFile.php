<?php


class DataFile
{
    private $lNUm = 5; // 缓存条数
    private $path= '/home/wwwroot/swoole/DataFile/';
    function __construct()
    {
    }
    public function save($data)
    {
        global $__Table , $__atomic;
        $__Table->set($__atomic->get(), array('content'=>$data));

        $list = swoole_get_local_ip();
        $ip = $list['eth0']; // 内网ip

        // foreach($__Table as $key =>$vs){
        //  echo $key.' : ' .$vs['content']."\n";
        // }
        //  echo "--------------------------分割线----------------------------------\n";
        if ($__Table->count() < $this->lNUm) {
            return true;
        }
        $this->collect($ip);
        return true;
    }

    //写文件
    public function collect($ip)
    {
        global $__Table;
        $rl =  array();
        $path = $this->path.date('Ymd')."/";
        $file = $path . date('Ymd_H').'_'.$ip;
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        $size = file_exists($file) ? @filesize($file) : 0;
        foreach ($__Table as $k => $v) {
            $str = $v['content'];
            $ret = file_put_contents($file, $str, FILE_APPEND);
            $__Table->del($k);
        }
        return true;
    }
}
