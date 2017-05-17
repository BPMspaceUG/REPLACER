-- 20170319_dump_replacer_structure_no_data_V1.sql

CREATE TABLE `replacer_tag` (
  `replacer_tag_id` bigint(20) NOT NULL,
  `replacer_tag_name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`replacer_tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `replacer_tag_id` (
  `replacer_tag_id` bigint(20) NOT NULL,
  `replacer_id` bigint(20) DEFAULT NULL,
  `tag_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`replacer_tag_id`),
  KEY `replacer_id_fk1_idx` (`replacer_id`),
  KEY `replacer_tag_id_idx` (`tag_id`),
  CONSTRAINT `replacer_id_fk1` FOREIGN KEY (`replacer_id`) REFERENCES `replacer` (`replacer_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `replacer_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `replacer_tag` (`replacer_tag_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `bpmspace_replacer_v1`.`replacer` 
ADD COLUMN `state_id_replacer` INT(11) NULL AFTER `replacer_language_de`,
ADD COLUMN `replacercol` VARCHAR(45) NULL AFTER `state_id_replacer`;