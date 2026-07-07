-- Hero / Gate B：全库 Unicode（utf8mb4）与用户内容字段规范
-- 新环境：data/pearproject.sql 已内置 utf8mb4
-- 已有库：由 docker/jira/run-migrations.php 在启动时幂等执行

SET NAMES utf8mb4;

-- projectInfo：Wiki / DIY 画布 JSON + Markdown（原 varchar(255) 不足）
ALTER TABLE `pear_project_info`
  MODIFY COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '名称',
  MODIFY COLUMN `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '值',
  MODIFY COLUMN `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '描述';

-- project：项目介绍 Markdown + emoji
ALTER TABLE `pear_project`
  MODIFY COLUMN `name` varchar(90) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '名称',
  MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '描述';

-- task：任务描述（与 2.9.0/task-description-utf8mb4.sql 合并，保留兼容）
ALTER TABLE `pear_task`
  MODIFY COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '详情',
  MODIFY COLUMN `path` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '上级任务路径';

-- project_log：评论 / 动态 Markdown
ALTER TABLE `pear_project_log`
  MODIFY COLUMN `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '操作内容',
  MODIFY COLUMN `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

-- events / 通知 / 邮件队列
ALTER TABLE `pear_events`
  MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '描述';

ALTER TABLE `pear_events_log`
  MODIFY COLUMN `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '操作内容',
  MODIFY COLUMN `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '日志描述';

ALTER TABLE `pear_notify`
  MODIFY COLUMN `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  MODIFY COLUMN `content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '内容',
  MODIFY COLUMN `send_data` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '关联数据';

ALTER TABLE `pear_mailqueue`
  MODIFY COLUMN `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN `failReason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- member
ALTER TABLE `pear_member`
  MODIFY COLUMN `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注';

ALTER TABLE `pear_member_account`
  MODIFY COLUMN `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '描述';

-- organization
ALTER TABLE `pear_organization`
  MODIFY COLUMN `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '描述';

-- jira api token（2.9.0 增量表）
ALTER TABLE `pear_jira_api_token`
  MODIFY COLUMN `token_label` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT 'Token 备注';
