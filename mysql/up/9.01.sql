CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `list` int(11) DEFAULT NULL,
  `email` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` text,
  `date` TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `mail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to` text,
  `subject` text,
  `email` text,
  `created` TIMESTAMP,
  `title` int(11) DEFAULT NULL,
  `exports` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to` text,
  `status` int(2) DEFAULT 0,
  `parent` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;