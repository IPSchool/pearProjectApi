SET FOREIGN_KEY_CHECKS=0;

-- Jira API Token（utf8mb4，与 Hero 全库规范一致）
CREATE TABLE IF NOT EXISTS `pear_jira_api_token` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_code` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户 code',
  `account_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Jira accountId',
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'SHA256(api token)',
  `token_label` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT 'Token 备注',
  `created_at` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `last_used_at` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否吊销',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_account_id`(`account_id`) USING BTREE,
  INDEX `idx_member_code`(`member_code`) USING BTREE,
  INDEX `idx_token_hash`(`token_hash`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Jira API Token' ROW_FORMAT = COMPACT;

SET FOREIGN_KEY_CHECKS=1;
