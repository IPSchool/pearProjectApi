-- 评论点赞与表情反应
CREATE TABLE IF NOT EXISTS `pear_project_log_reaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_code` varchar(30) NOT NULL COMMENT 'project_log.code',
  `member_code` varchar(30) NOT NULL,
  `reaction` varchar(20) NOT NULL DEFAULT 'like' COMMENT 'like 或 emoji',
  `create_time` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_log_member_reaction` (`log_code`, `member_code`, `reaction`),
  KEY `idx_log_code` (`log_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目日志/评论反应';
