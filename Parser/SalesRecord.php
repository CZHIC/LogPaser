<?php
require_once 'IParserStrategy.php';
/**
 * 
 * 道具销售处理策略
 * @author MikePeng
 *
 */
class SalesRecord implements IParserStrategy{
	private $indexRecords = array();	//当前处理value
	private $curRecordCount = 0;		//累计条目
	private $maxRecordCount = DATA_CACHE_COUNT;
	
	private $db;
		
	public function __destruct()
	{
		$this->save();
	}
	
	public function __construct()
	{
		$this->db = MySQLDB::getInstance();
	}
	
	public function parserLine($record) 
	{	
    	$this->curRecordCount++;    	
    	$this->collect($record);
    	if ( $this->curRecordCount >= $this->maxRecordCount) {
			$this->save();
    	}
    	return true;  
	}
	
	public function save()
	{
		$this->_save();
    	$this->indexRecords = array();
		$this->curRecordCount = 0;
	}

	public function _save(){
		$records = $this->indexRecordsToArray();
		foreach ($records as $record){
			$appConfig = Parser::getAppConfig($record[2]);
			if (!$appConfig)
				break;
			if ($record[10] == 1)
				$num = -$record[6];
			else 
				$num = $record[6];
			$tabledate = ($record[9] ? date('Ym',$record[9]) : date('Ym'));
			$sql = "INSERT INTO ct_salesrecord".$tabledate." (`mid`,`lev`,`pid` ,`pcf` ,`pcate` ,`pframe` ,`num`,`bynum`,`pt`,`otime`) VALUES('{$record[0]}','{$record[1]}','{$record[2]}'
			,'{$record[3]}','{$record[4]}','{$record[5]}','{$record[6]}','{$record[7]}','{$record[8]}','{$record[9]}') ON DUPLICATE KEY UPDATE ";
			if ($record[10] == 0)
				$sql .= "`num` = `num` + {$record[6]},`bynum` = `bynum` + {$record[7]}";
			elseif ($record[10] == 1)
				$sql .= "`num` = `num` - {$record[6]},`bynum` = `bynum` - {$record[7]}";
			else 
				$sql .= "`num` = {$record[6]},`bynum` = {$record[7]}";
			$this->db->query($sql);
		}
	}
	
	/**
	 * 
	 * Enter description here ...
	 * @param array $line(0=>统计项,1=>分区,2=>值,3=>操作码,4=>时间,5=>用户)  
	 * value = array(0=>数量,1=>单价，2=>销售类型，3=>分类，4=>帧号，5=>用户等级)
	 */
	private function collect($line){
		if (!is_array($line[2]) || !$line[2])
			return false;
		$key = $line[5]."|".$line[1]."|".$line[2][2]."|".$line[4];//用户|分区|销售类型 |时间
		if (!isset($this->indexRecords[$key])){
			$this->indexRecords[$key] = array((int)$line[2][0],$line[2],$line[3]);
		}else {
			if (($line[3] == 0) || ($line[3] == 1))
				$this->indexRecords[$key][0] += (int)$line[2][0];
			elseif ($line[3] == 2)
				$this->indexRecords[$key] = array($line[2][0],$line[2],$line[3]);
			else
				Logger::err('Data OP Error:'.$line);
		}
		return true;
	}
	
	private function indexRecordsToArray(){
		$records = array();
		foreach ($this->indexRecords as $key=>$value):
			$tmp = explode('|', $key);
			$pcf = $value[1][3]."_".$value[1][4];
			$bynum = (int)$value[0]*$value[1][1];
			$records[] = array($tmp[0],(int)$value[1][5],$tmp[1],$pcf,(int)$value[1][3],(int)$value[1][4],(int)$value[0],$bynum,$tmp[2],$tmp[3],$value[2]);
		endforeach;
		return $records;
	}
}