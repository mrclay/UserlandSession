CREATE TABLE IF NOT EXISTS `userland_sessions` (
   `id` VARCHAR(40) NOT NULL,
   `data` MEDIUMBLOB,
   `time` INT NOT NULL,
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;