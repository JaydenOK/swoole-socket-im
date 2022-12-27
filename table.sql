CREATE TABLE `yb_user` (
    `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL DEFAULT '',
    `nickname` varchar(50) NOT NULL DEFAULT '',
    `password` varchar(50) NOT NULL DEFAULT '',
    `salt` varchar(50) NOT NULL DEFAULT '',
    `email` varchar(50) NOT NULL DEFAULT '',
    `mobile` varchar(50) NOT NULL DEFAULT '',
    `wechat` varchar(50) NOT NULL DEFAULT '',
    `avatar` varchar(100) NOT NULL DEFAULT '',
    `gender` tinyint(1) NOT NULL DEFAULT '0',
    `birthday` varchar(50) NOT NULL DEFAULT '',
    `login_time` varchar(20) NOT NULL DEFAULT '',
    `login_ip` varchar(50) NOT NULL DEFAULT '',
    `access_token` varchar(1000) NOT NULL DEFAULT '',
    `status` varchar(50) NOT NULL DEFAULT '',
    `create_time` datetime DEFAULT NULL,
    `update_time` datetime DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='user';


CREATE TABLE `yb_shop` (
    `shop_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `shop_name` varchar(50) NOT NULL DEFAULT '',
    `uid` int(11) unsigned NOT NULL DEFAULT '0',
    `status` tinyint(1) NOT NULL DEFAULT '0',
    `create_time` datetime DEFAULT NULL,
    `update_time` datetime DEFAULT NULL,
    PRIMARY KEY (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='shop';


CREATE TABLE `yb_shop_service` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `shop_id` int(11) unsigned NOT NULL DEFAULT '0',
    `uid` int(11) unsigned NOT NULL DEFAULT '0',
    `service_name` varchar(50) NOT NULL DEFAULT '',
    `is_online` tinyint(1) NOT NULL DEFAULT '0',
    `create_time` datetime DEFAULT NULL,
    `update_time` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='shop_service';


CREATE TABLE `yb_chat` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`uid` int(11) unsigned NOT NULL DEFAULT '0',
`to_uid` int(11) unsigned NOT NULL DEFAULT '0',
`shop_id` int(11) unsigned NOT NULL DEFAULT '0',
`chat_id` varchar(50) NOT NULL DEFAULT '',
`chat_name` varchar(50) NOT NULL DEFAULT '',
`chat_type` varchar(50) NOT NULL DEFAULT '',
`msg_type` varchar(50) NOT NULL DEFAULT '',
`last_msg` varchar(50) NOT NULL DEFAULT '',
`last_time` varchar(50) NOT NULL DEFAULT '',
`unread` int(11) unsigned NOT NULL DEFAULT '0',
`is_del` tinyint(1) NOT NULL DEFAULT '0',
`create_time` datetime DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `idx_chat_id` (`chat_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='chat';


CREATE TABLE `yb_chat_message` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`uid` int(11) NOT NULL DEFAULT '0',
`to_uid` int(11) NOT NULL DEFAULT '0',
`chat_id` varchar(50) NOT NULL DEFAULT '',
`chat_type` varchar(50) NOT NULL DEFAULT '',
`msg_type` varchar(50) NOT NULL DEFAULT '',
`msg` varchar(1000) NOT NULL DEFAULT '',
`create_time` datetime DEFAULT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='chat_message';


