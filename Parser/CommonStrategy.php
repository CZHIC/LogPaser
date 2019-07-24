<?php
require_once 'IParserStrategy.php';
/**
 * 一般处理策略
 * @author LeoLuo
 *
 */
class CommonStrategy implements IParserStrategy
{
    
    private $_database;
    
    private $_recordNormal= array();
    
    private $_recordReg= array();
    
    private $_count = 0;
    
    private $_maxCount = DATA_CACHE_COUNT;
    
    public function __construct()
    {
        $this->_database = MySQLDB::getInstance();
    }
    
    public function __destruct()
    {
        $this->save();
    }
    
    /**
     * {@inheritDoc}
     * @see IParserStrategy::parserLine()
     */
    public function parserLine($record)
    {
        $siteid  = isset($record[0]) ? (int)$record[0] : 0;
        $typeid  = isset($record[1]) ? (int)$record[1] : 0;
        $appid   = isset($record[2]) ? (int)$record[2] : 0;
        $value   = isset($record[3]) ? (int)$record[3] : 0;
        $act     = isset($record[4]) ? (int)$record[4] : 0;
        $time    = isset($record[5]) ? (int)$record[5] : 0;
        $uid     = isset($record[6]) ? (int)$record[6] : 0;
        $regtime = isset($record[7]) ? (int)$record[7] : 0;
        $sappid  = isset($record[8]) ? (int)$record[8] : 0;
        
        $this->_collectNormal($uid, $siteid, $appid, $sappid, $time, $typeid, $act, $value);
        $this->_collectReg($uid, $siteid, $appid, $sappid, $time, $regtime, $typeid, $act, $value);
        
        $this->_count++;
        if ($this->_count >= $this->_maxCount) {
            $this->save();
        }
    }
    
    
    /**
     * {@inheritDoc}
     * @see IParserStrategy::save()
     */
    public function save()
    {
        $this->_saveNormal();
        $this->_saveReg();
        $this->_count = 0;
        $this->_recordNormal = array();
        $this->_recordReg = array();
    }
    
    
    private function _collectNormal($uid, $siteid, $appid, $sappid, $time, $typeid, $act, $value)
    {
        //大厅10200类型上报act强制改为0
        if ($siteid==5 && $typeid==10200) {
            $act = 0;
        }
        
        $key = implode("_", array($siteid,$typeid,$appid,$sappid,$uid));
        if (!isset($this->_recordNormal[$key])) {
            $this->_recordNormal[$key] = array($value, $time, $act);
        } else {
            $this->_recordNormal[$key][1] = max($this->_recordNormal[$key][1], $time);
            $this->_recordNormal[$key][2] = 0;  //每个周期遇到重复key记录时，act改为0（原有逻辑就这样，也不知道为什么？？？？）
            
            if ($act==0 || $act==1) {
                $this->_recordNormal[$key][0] += intval($value);
            } elseif ($act == 2) {
                $this->_recordNormal[$key][2] = 2;
                $this->_recordNormal[$key][0] = intval($value);
            } else {
                Logger::err("Data OP Error: {$uid},{$siteid},{$appid},{$sappid},{$time},{$typeid},{$act},{$value}");
            }
        }
    }
    
    private function _saveNormal()
    {
        foreach ($this->_recordNormal as $k => $v) {
            list($siteid,$typeid,$appid,$sappid,$uid) = explode("_", $k);
            $mdate = date("Ymd", $v[1]);
            $mmonth = date("Ym", $v[1]);
            if ($mmonth == 197001) {
                continue;
            }
            
            $value = ($v[2]==1) ? -$v[0] : $v[0];
            
            $tbl = "ct_data{$mmonth}";
            $sql = "INSERT INTO {$tbl} (`siteid`,`lang`,`pid`,`appid`,`sappid`,`stypeid`,`svalue`,`mdate`) VALUES('{$siteid}',0,'{$siteid}','{$appid}','{$sappid}','{$typeid}','{$value}','{$mdate}') ON DUPLICATE KEY UPDATE `svalue`=";
            if ($v[2]==0 || $v[2]==1) {
                $sql .= "`svalue`+{$value}";
            } else {
                $sql .= "{$value}";
            }
            $this->_database->query($sql);
        }
    }
    
    private function _collectReg($uid, $siteid, $appid, $sappid, $time, $regtime, $typeid, $act, $value)
    {
        if ($siteid!=5 || !$regtime) {
            return ;
        }
        $regdate= date("Ymd", $regtime);
        $key = implode("_", array($siteid,$typeid,$appid,$sappid,$regdate,$uid));
        if (!isset($this->_recordReg[$key])) {
            $this->_recordReg[$key] = array($value, $time, $act);
        } else {
            $this->_recordReg[$key][1] = max($this->_recordReg[$key][1], $time);
            $this->_recordReg[$key][2] = 0; //延用normal的方式， act改为0
            if ($act==0 || $act==1) {
                $this->_recordReg[$key][0] += $value;
            } elseif ($act==2) {
                $this->_recordReg[$key][0] = $value;
            } else {
                Logger::err("Data OP Error: {$uid},{$siteid},{$appid},{$sappid},{$time},{$regtime},{$typeid},{$act},{$value}");
            }
        }
    }
    
    private function _saveReg()
    {
        foreach ($this->_recordReg as $k => $v) {
            list($siteid,$typeid,$appid,$sappid,$regdate,$uid) = explode("_", $k);
            $mdate = date('Ymd', $v[1]);
            $mmonth = date("Ym", $v[1]);
            
            $value = ($v[2]==1) ? -$v[0] : $v[0];
            
            $tbl = "ct_data_reg{$mmonth}";
            $sql = "INSERT INTO {$tbl} (`siteid`,`appid`,`sappid`,`lang`,`pid`,`stypeid`,`svalue`,`regdate`,`mdate`) VALUES({$siteid},{$appid},{$sappid},0,{$siteid},{$typeid},{$value},{$regdate},{$mdate}) ON DUPLICATE KEY UPDATE `svalue`=";
            if ($v[2]==0 || $v[2]==1) {
                $sql .= "`svalue`+{$value}";
            } else {
                $sql .= "{$value}";
            }
            $this->_database->query($sql);
        }
    }
}
