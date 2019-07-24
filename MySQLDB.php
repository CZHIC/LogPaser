<?php
//array('192.168.0.5', 'root', '', 'weibo'),
final class MySQLDB
{
    private $conn = null;
    private $arrServers;
    private $persist = false;
    private static $_db = null;
    
    public static function getInstance()
    {
        if (self::$_db === null) {
            $className = __CLASS__;
            self::$_db= new $className();
        }
        return self::$_db;
    }
    
    private function __construct()
    {
         
        $this->arrServers = array(MYSQL_HOST, MYSQL_USER, MYSQL_PSW, MYSQL_DB);
    }
    
    public function connect()
    {
        if ($this->conn) {
            return true;
        }
        if ($this->persist) {
            $this->conn = @mysql_pconnect($this->arrServers[0], $this->arrServers[1], $this->arrServers[2]) or $this->errorlog();
        } else {
            $this->conn = @mysqli_connect($this->arrServers[0], $this->arrServers[1], $this->arrServers[2], $this->arrServers[3]) or $this->errorlog();
        }
        @mysqli_select_db($this->conn, $this->arrServers[3]);
        $mysql_version = mysqli_get_server_info($this->conn);
        if (version_compare($mysql_version, '4.1.0', '>=')) {
            $this->query("SET SQL_MODE='',CHARACTER_SET_CONNECTION='utf8',CHARACTER_SET_RESULTS='utf8',CHARACTER_SET_CLIENT='binary',NAMES 'utf8'");
        } elseif (version_compare($mysql_version, '5.0.7', '>=')) {
            @mysqli_set_charset($this->conn, 'utf8');
        }
        return true;
    }
    
    public function query($sql = '')
    {
 //Execute the sql query
        is_resource($this->conn) || $this->connect();
        $this->result = @mysqli_query($this->conn, $sql) or $this->errorlog($sql);
        return $this->result;
    }
    
    public function getAll($sql, $mode = MYSQLI_ASSOC)
    {
        $result = $this->query($sql);
        $temp = array();
        while ($array = @mysqli_fetch_array($result, $mode)) {
            $temp[] = $array;
        }
        return (array)$temp;
    }
    
    public function getOne($sql, $mode = MYSQLI_ASSOC)
    {
        $result = $this->query($sql);
        return @mysqli_fetch_array($result, $mode);
    }
    
    public function update($tbl, $arrData, $where)
    {
        $sql = "update ignore $tbl SET ";
        foreach ((array)$arrData as $key => $value) {
            $sql .= "`$key`='" . $this->escape($value) . "',";
        }
        $sql = substr($sql, 0, -1);
        $sql .= " where 1 ";
        if (is_array($where) && count($where)) {
            foreach ($where as $key => $value) {
                $sql .= " and `$key`='" . $this->escape($value) . "'";
            }
            $this->query($sql);
            return @mysqli_affected_rows($this->conn);
        }
        die('error');
    }
    
    public function insert($tbl, $arrData)
    {
        $sql = "INSERT IGNORE INTO $tbl SET ";
        foreach ((array)$arrData as $key => $value) {
            $sql .= "`$key`='" . $this->escape($value) . "',";
        }
        $this->query(substr($sql, 0, -1));
        return @mysqli_insert_id($this->conn);
    }
    
    public function deleteTable($tbl, $where)
    {
        $sql = "delete from $tbl where 1";
        if (is_array($where) && count($where)) {
            foreach ($where as $key => $value) {
                $value = $this->escape($value);
                if ($key == $value) {
                    return false;
                }
                $sql .= " and `$key`='" . $value . "'";
            }
            $this->query($sql);
            return @mysqli_affected_rows($this->conn);
        }
        die('error');
    }

    public function escape($string)
    {
        is_resource($this->conn) || $this->connect();
        $string = trim($string);
        if (function_exists('mysql_real_escape_string')) {
            return mysqli_real_escape_string($this->conn, $string);
        } else {
            return mysqli_escape_string($this->conn, $string);
        }
    }

    public function close()
    {
        is_resource($this->conn) && @mysqli_close($this->conn);
    }
    
    private function errorlog($msg = '')
    {
        $trc = debug_backtrace();
        $s = date('Y-m-d H:i:s');
        $s .= "\tERR\t" . @mysqli_errno($this->conn).':'.mysqli_error($this->conn);
        $s .= "\t" . $trc[0]['file'];
        $s .= "\tline " . $trc[0]['line'];
        $s .= "\t" . $msg;
        $file =  LOG_ROOT.'mysql_error.log';
        file_put_contents($file, "{$s}\n", @filesize($file)<512*1024 ? FILE_APPEND : null);
        die('DB Invalid!!!');
    }
}
