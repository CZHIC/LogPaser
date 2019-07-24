<?php
/**
 * 解析日志文件数据
 * @author LeoLuo
 */
final class Parser
{
    static $parserStrategy = array();
    static $stategyMap = array(
         //1 => 'CommonSalesrecord',
        1108 => 'SalesRecord',
               
    );
    static $appConfig = array();
    /**
     * 分析保存数据
     * @param array|null $record
     * 参数为null立即保存数据
     */
    public static function save($record = null)
    {
        if ($record === null) {
            foreach (self::$stategyMap as $key => $class_name) {
                if (isset(self::$parserStrategy[$class_name]) && is_object(self::$parserStrategy[$class_name])) {
                    self::$parserStrategy[$class_name]->save();
                }
            }
        } else {
            $record = json_decode($record);
            $tid = $record[0];
            $class_name = 'CommonStrategy';
            if (isset(self::$stategyMap[$tid])) {
                $class_name = self::$stategyMap[$tid];
            }
            $object = self::getInstance($class_name);
            $object->parserLine($record);
        }
    }
    
    /**
     * 获得策略对象
     * @param string $class_name
     */
    public static function getInstance($class_name)
    {
        if (isset(self::$parserStrategy[$class_name]) && is_object(self::$parserStrategy[$class_name])) {
            return self::$parserStrategy[$class_name];
        } else {
            require_once $class_name.'.php';
            self::$parserStrategy[$class_name] = new $class_name();
            return self::$parserStrategy[$class_name];
        }
    }

    public static function getAppConfig($pid)
    {
        if (empty(self::$appConfig)) {
            $temp = include_once(APP_ROOT.'config/appconf/appid.conf.php');
            self::$appConfig = $temp['APP_PID_CONF'];
            foreach (self::$appConfig as $temp_pid => &$conf) {
                array_walk($conf, create_function('&$value', '$value = intval($value);'));
            }
        }
        return self::$appConfig[$pid];
    }
}
