-- 创建计划任务日志表
CREATE TABLE IF NOT EXISTS `pre_cron_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task` varchar(32) NOT NULL COMMENT '任务名称',
  `result` text NOT NULL COMMENT '执行结果',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0失败，1成功',
  `addtime` datetime NOT NULL COMMENT '执行时间',
  PRIMARY KEY (`id`),
  KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='计划任务日志';

-- 添加计划任务日志清理设置
INSERT INTO `pre_config` (`k`, `v`) VALUES ('cron_log_clean_days', '30') ON DUPLICATE KEY UPDATE `v` = '30';