-- 系统管理：LLM 与存储补充项（幂等）
INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'storage_qiniu_region', ''
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'storage_qiniu_region');

INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'llm_enabled', '0'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'llm_enabled');

INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'llm_provider', 'openai'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'llm_provider');

INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'llm_api_base', ''
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'llm_api_base');

INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'llm_api_key', ''
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'llm_api_key');

INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'llm_default_model', 'gpt-4o-mini'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'llm_default_model');

INSERT INTO `pear_system_config` (`name`, `value`)
SELECT 'llm_max_tokens', '4096'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pear_system_config` WHERE `name` = 'llm_max_tokens');
