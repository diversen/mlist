CREATE TABLE `listqueue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `list` int(11) NOT NULL,
  `mail` int(11) NOT NULL,
  `status` int(1) DEFAULT 0,
  `date` TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;