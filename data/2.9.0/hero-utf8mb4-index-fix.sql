-- utf8mb4 迁移前：缩短带索引的 varchar，避免 MySQL 5.7 索引长度 767 字节限制
-- 须在 CONVERT TO utf8mb4 之前执行（run-migrations.php）

ALTER TABLE `pear_events_log`
  MODIFY COLUMN `project_code` varchar(30) NULL DEFAULT NULL COMMENT '项目编号',
  MODIFY COLUMN `events_code` varchar(30) NULL DEFAULT NULL COMMENT '日程编号';

ALTER TABLE `pear_mailqueue`
  MODIFY COLUMN `sendTime` varchar(30) NULL DEFAULT NULL;

ALTER TABLE `pear_project_auth_node`
  MODIFY COLUMN `node` varchar(191) NULL DEFAULT NULL COMMENT '节点路径';

ALTER TABLE `pear_project_version_log`
  MODIFY COLUMN `project_code` varchar(30) NULL DEFAULT NULL COMMENT '项目编号',
  MODIFY COLUMN `features_code` varchar(30) NULL DEFAULT NULL COMMENT '版本库编号';
