<?php
/**
 * 处理策略接口
 * @author LeoLuo
 *
 */
interface IParserStrategy{
	/**
	 * 处理一条数据行
	 * @param array $record
	 */
	public function parserLine($record);
	
	/**
	 * 强制保存数据
	 */
	public function save();
}