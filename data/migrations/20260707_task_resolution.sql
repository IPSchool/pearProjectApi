-- Jira Resolution 字段：pear_task.resolution
ALTER TABLE `pear_task`
  ADD COLUMN `resolution` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
  COMMENT 'Jira resolution code: fixed,wont_fix,duplicate,incomplete,cannot_reproduce,done'
  AFTER `status`;

UPDATE `pear_task` SET `resolution` = 'fixed' WHERE `done` = 1 AND (`resolution` IS NULL OR `resolution` = '');
