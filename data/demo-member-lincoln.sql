-- 主演示账号：登录名与显示名改为 Lincoln（密码仍为 md5(123456)）
UPDATE `pear_member`
SET `account` = 'Lincoln', `name` = 'Lincoln', `realname` = 'Lincoln'
WHERE `code` = '6v7be19pwman2fird04gqu53';

UPDATE `pear_member_account`
SET `name` = 'Lincoln'
WHERE `member_code` = '6v7be19pwman2fird04gqu53';
