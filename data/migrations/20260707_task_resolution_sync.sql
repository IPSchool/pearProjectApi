-- 修复 status / done / resolution 三者不一致的历史数据
UPDATE `pear_task` SET `status` = 1 WHERE `done` = 1 AND `status` <> 1;
UPDATE `pear_task` SET `resolution` = NULL WHERE `status` <> 1;
UPDATE `pear_task` SET `resolution` = 'fixed' WHERE `status` = 1 AND (`resolution` IS NULL OR `resolution` = '');
