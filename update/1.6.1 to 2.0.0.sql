--
--把tableprefix_换你的表前缀再执行;
--

ALTER TABLE  `tableprefix_asset` ADD  `uid` INT( 11 ) NOT NULL DEFAULT  '0' COMMENT  '用户 id' AFTER  `aid` ;

--
--tableprefix_users表
--
ALTER TABLE  `tableprefix_users` ADD  `coin` INT( 11 ) NOT NULL DEFAULT  '0' COMMENT  '金币';
ALTER TABLE  `tableprefix_users` ADD  `mobile` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  '' COMMENT  '手机号',
ADD INDEX (  `mobile` );

CREATE TABLE IF NOT EXISTS `cmf_weusers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `openid` varchar(50) NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `sex` smallint(1) NOT NULL,
  `province` varchar(20) NOT NULL,
  `city` varchar(20) NOT NULL,
  `country` varchar(20) NOT NULL,
  `headimgurl` varchar(255) NOT NULL,
  `privilege` text NOT NULL,
  `create_time` datetime NOT NULL,
  `last_login_time` date NOT NULL COMMENT '最后登陆时间',
  `last_login_ip` varchar(20) NOT NULL COMMENT '最后登陆ip',
  PRIMARY KEY (`id`),
  KEY `key_openid` (`openid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
