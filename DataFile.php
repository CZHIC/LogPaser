<?php
/**
 * 日志文件管理
 * 数据库队列
 * @author LeoLuo
 */
class DataFile
{
   
    private $db;
    private $group = 0;
    private $indexRecords = array();
    private $curRecordCount = 0;
    private $maxRecordCount = DATA_CACHE_COUNT;
    
    
    function __construct()
    {
        $this->db = MySQLDB::getInstance();
    }
    
    /**
     * 获取待处理的日志文件
     * @param int $group 分组id
     * 不同的组采用不同的进程进行处理
     */
    public function getFile($group)
    {
        Logger::info('Get File:'.$group);
        return $this->db->getAll('select `file_name`,`server_ip` from `ct_logfile` where `iscomplete` = 0 and `group` = '.intval($group));
    }
    

    
    /**
     * 导入同步过来的日志文件到数据库队列
     *
     * 获得今天最后一个导入的文件修改日期
     * 把今天剩余的文件导入到数据库中
     */
    public function importFile()
    {
        $dir = date('Ymd');
        $last_time = 0;
        $temp = $this->db->getOne('select data_time,file_name from ct_logfile order by data_time DESC limit 1');
        if ($temp) {
            $last_time = strtotime($temp['data_time']);
            $last_dir = date('Ymd', $last_time);
            while ($last_dir != $dir) {
                $this->scanDataFileToDB($last_dir, $last_time);
                $last_time = mktime(0, 0, 0, date("m", $last_time), date("d", $last_time)+1, date("Y", $last_time));
                $last_dir = date('Ymd', $last_time);
            }
        }
        Logger::info('last_time:'.date('Y-m-d H:i:s', $last_time));
        $this->scanDataFileToDB($dir, $last_time, isset($temp['file_name'])?$temp['file_name']:null);
    }
    

    private function scanDataFileToDB($dir, $last_time, $last_file = null)
    {
        
        $dir = DATA_ROOT.$dir;
        Logger::info('scan dir:'.$dir);
        if (is_dir($dir)) {
            $dirHandle = opendir($dir);
            while (false !== ($entry0 = readdir($dirHandle))) {
                if (!preg_match("/^[0-9]{8}_[0-9]{2}_([0-9]{1,3}\.){3}[0-9]{1,3}$/", $entry0)) {
                    continue;
                }
                $entry = $dir.'/'.$entry0;
                if (!is_file($entry)) {
                    continue;
                }
                $datetime = explode("_", $entry0);
                $date = $datetime[0];
                $h = $datetime[1];
                $file_time = strtotime("$date $h:00:00");

                if ($file_time >= $last_time && $file_time < strtotime(date('Y-m-d H:').'00:00')) {
                    if ($last_file != null && $entry == $last_file) {
                        continue;
                    }
                    $ip = substr(strrchr($entry, "_"), 1);
                    Logger::info('importFile:'.$entry);
                    $this->db->insert('ct_logfile', array('file_name'=>$entry,'data_time'=>date('Y-m-d H:i:s', $file_time),'server_ip'=>$ip,'iscomplete' => 0,'group' => $this->group ));
                    $this->group ++;
                    $this->group = $this->group%PARSER_PROCESS;
                }
            }
        }
    }
    
    /**
     * 文件已处理标记
     * @param string $file_name 文件名
     */
    public function fileParserComplete($file_name)
    {
        Logger::info('file Parser Complete:'.$file_name);
        return $this->db->update('ct_logfile', array('iscomplete' => 1,'parser_time'=>date('Y-m-d H:i:s')), array('file_name' => $file_name));
    }
}
