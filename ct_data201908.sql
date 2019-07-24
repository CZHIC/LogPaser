
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- 数据库: `statistics`
--

-- --------------------------------------------------------

--
-- 表的结构 `ct_data201908`
--
CREATE TABLE `ct_data201908` (
  `appid` int(10) NOT NULL COMMENT '应用ID',
  `sappid` int(10) NOT NULL DEFAULT '0' COMMENT '子应用ID',
  `siteid` int(10) NOT NULL COMMENT '站点ID',
  `lang` tinyint(3) NOT NULL COMMENT '语言版本',
  `pid` int(10) NOT NULL COMMENT '分区ID',
  `stypeid` int(10) NOT NULL COMMENT '统计类型',
  `svalue` double NOT NULL COMMENT '统计值',
  `mdate` int(11) NOT NULL COMMENT '统计时间',
  PRIMARY KEY (`appid`,`siteid`,`stypeid`,`mdate`,`sappid`),
  KEY `index_name` (`appid`,`siteid`,`stypeid`,`mdate`,`sappid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='统计数据表';