
--
-- 数据库: `statistics`
--

-- --------------------------------------------------------

--
-- 表的结构 `ct_logfile`
--

CREATE TABLE IF NOT EXISTS `ct_logfile` (
  `file_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT '文件名',
  `data_time` datetime NOT NULL COMMENT '数据时间',
  `server_ip` varchar(15) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT '上报的服务器ip',
  `parser_time` datetime NOT NULL COMMENT '处理时间',
  `iscomplete` tinyint(1) NOT NULL COMMENT '是否处理过',
  `group` tinyint(1) NOT NULL COMMENT '处理组',
  PRIMARY KEY (`file_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;