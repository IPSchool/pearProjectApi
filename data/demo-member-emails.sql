-- 演示账号邮箱更新（@dabaili.cn）
-- 账号登录名不变，仅更新 pear_member / pear_member_account 的 email 字段

UPDATE `pear_member` SET `email` = 'luyu@dabaili.cn' WHERE `account` = '123456';
UPDATE `pear_member` SET `email` = 'heitou@dabaili.cn' WHERE `account` = 'Alians';
UPDATE `pear_member` SET `email` = 'shiziyu@dabaili.cn' WHERE `account` = 'Chihiro';
UPDATE `pear_member` SET `email` = 'qiaozui@dabaili.cn' WHERE `account` = 'Json';
UPDATE `pear_member` SET `email` = 'panpi@dabaili.cn' WHERE `account` = 't2u3wz';
UPDATE `pear_member` SET `email` = 'maisui@dabaili.cn' WHERE `account` = 'ewtfq5';

UPDATE `pear_member_account` SET `email` = 'luyu@dabaili.cn' WHERE `member_code` = '6v7be19pwman2fird04gqu53';
UPDATE `pear_member_account` SET `email` = 'heitou@dabaili.cn' WHERE `member_code` = 'kqdcn2w40p58r31zyoefjib';
UPDATE `pear_member_account` SET `email` = 'shiziyu@dabaili.cn' WHERE `member_code` = 'y680trgedcavbhnz24u7i5m3';
UPDATE `pear_member_account` SET `email` = 'qiaozui@dabaili.cn' WHERE `member_code` = 'vys8gd32cfui6brtwzj4pqho';
UPDATE `pear_member_account` SET `email` = 'panpi@dabaili.cn' WHERE `member_code` = '058u3fnod4ayibjmsp26qkwz';
UPDATE `pear_member_account` SET `email` = 'maisui@dabaili.cn' WHERE `member_code` = '65kwuynmf8g0z1va4qe2tlrb';
