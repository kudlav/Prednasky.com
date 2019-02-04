-- MySQL Workbench Synchronization

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';
-- BEGIN HERE --


-- 1.0 => 1.1 --
ALTER TABLE `prednasky`.`user_has_video`
ADD COLUMN `show_email` TINYINT(1) NOT NULL AFTER `role_id`;

-- 1.1 => 1.2 --
ALTER TABLE `prednasky`.`tag`
CHANGE COLUMN `value` `value` VARCHAR(100) NULL DEFAULT NULL ,
ADD UNIQUE INDEX `tag_UNIQUE` (`name` ASC, `value` ASC),
DROP INDEX `name_UNIQUE` ;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.2";$$
DELIMITER ;

-- 1.2 => 1.3 --
ALTER TABLE `prednasky`.`token`
ADD COLUMN `template` INT(10) UNSIGNED NOT NULL AFTER `type`,
ADD COLUMN `pending_blocks` TEXT NOT NULL AFTER `created`,
ADD COLUMN `current_state` VARCHAR(45) NULL DEFAULT NULL AFTER `last_update`,
ADD INDEX `fk_token_template1_idx` (`template` ASC);
CREATE TABLE IF NOT EXISTS `prednasky`.`template` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `blocks` TEXT NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC),
  UNIQUE INDEX `name_UNIQUE` (`name` ASC))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;
ALTER TABLE `prednasky`.`token` 
ADD CONSTRAINT `fk_token_template1`
  FOREIGN KEY (`template`)
  REFERENCES `prednasky`.`template` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
ALTER TABLE `prednasky`.`file`
CHANGE COLUMN `type` `type` VARCHAR(45) NOT NULL ;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.3";$$
DELIMITER ;
INSERT INTO `prednasky`.`template` (`id`, `name`, `blocks`, `description`) VALUES (DEFAULT, 'config_youtube_downloader.ini', 'msg_download_start;youtubedl;msg_download_end;avprobe;msg_input_audiolength;msg_input_videolength;msg_input_hasaudio;msg_input_hasvideo;has_audio;has_video;msg_video_start;msg_video_end;convert_video;thumbnail_video;copyresults_video;avprobe_outmedia;msg_output_videolength;msg_output_audiolength;finish', 'Download video from YouTube');

-- 1.3 => 1.4 --
ALTER TABLE `prednasky`.`user` 
DROP COLUMN `surname`,
CHANGE COLUMN `name` `fullname` VARCHAR(100) NOT NULL ;
ALTER TABLE `prednasky`.`video_has_file` 
ADD INDEX `fk_video_has_file_file1_idx` (`file_id` ASC),
DROP INDEX `fk_video_has_file_file1_idx` ;
ALTER TABLE `prednasky`.`user_has_video` 
DROP PRIMARY KEY,
ADD PRIMARY KEY (`user_id`, `video_id`, `role_id`);
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.4";$$
DELIMITER ;

-- 1.4 => 1.5 --
CREATE TABLE IF NOT EXISTS `prednasky`.`right` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  INDEX `fk_right_user1_idx` (`user_id` ASC),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC),
  CONSTRAINT `fk_right_user1`
    FOREIGN KEY (`user_id`)
    REFERENCES `prednasky`.`user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;
CREATE TABLE IF NOT EXISTS `prednasky`.`right_has_tag` (
  `right_id` INT(10) UNSIGNED NOT NULL,
  `tag_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`right_id`, `tag_id`),
  INDEX `fk_right_has_tag_tag1_idx` (`tag_id` ASC),
  INDEX `fk_right_has_tag_right1_idx` (`right_id` ASC),
  CONSTRAINT `fk_right_has_tag_right1`
    FOREIGN KEY (`right_id`)
    REFERENCES `prednasky`.`right` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_right_has_tag_tag1`
    FOREIGN KEY (`tag_id`)
    REFERENCES `prednasky`.`tag` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.5";$$
DELIMITER ;

-- 1.5 => 1.6 --
ALTER TABLE `prednasky`.`video`
ADD COLUMN `complete` TINYINT(1) NOT NULL AFTER `state`;
UPDATE `prednasky`.`video_state` SET `name`='private' WHERE  `id`=1;
UPDATE `prednasky`.`video_state` SET `name`='logged_in' WHERE  `id`=2;
UPDATE `prednasky`.`video` SET `state`=1 WHERE `state`=2;
UPDATE `prednasky`.`video` SET `state`=1 WHERE `state`=3;
DELETE FROM `prednasky`.`video_state` WHERE  `id`=3;
UPDATE `prednasky`.`video` SET `state`=2 WHERE `state`=4;
DELETE FROM `prednasky`.`video_state` WHERE  `id`=4;
START TRANSACTION;
INSERT INTO `prednasky`.`video_state` (`id`, `name`) VALUES ('3', 'public');
UPDATE `prednasky`.`video` SET `state`='3' WHERE  `state`=5;
DELETE FROM `prednasky`.`video_state` WHERE  `id`=5;
COMMIT;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.6";$$

-- 1.6 => 1.7 --
ALTER TABLE `prednasky`.`right` DROP FOREIGN KEY `fk_right_user1`;
ALTER TABLE `prednasky`.`token` DROP FOREIGN KEY `fk_token_video1`;
ALTER TABLE `prednasky`.`video_has_tag` DROP FOREIGN KEY `fk_video_has_tag_video1`;
ALTER TABLE `prednasky`.`user_has_video` DROP FOREIGN KEY `fk_user_has_video_video1`;
ALTER TABLE `prednasky`.`video_has_file` DROP FOREIGN KEY `fk_video_has_file_video1`, DROP FOREIGN KEY `fk_video_has_file_file1`;
ALTER TABLE `prednasky`.`video_relation` DROP FOREIGN KEY `fk_video_has_video_video1`, DROP FOREIGN KEY `fk_video_has_video_video2`;
ALTER TABLE `prednasky`.`video_has_file` 
ADD CONSTRAINT `fk_video_has_file_video1`
  FOREIGN KEY (`video_id`)
  REFERENCES `prednasky`.`video` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_video_has_file_file1`
  FOREIGN KEY (`file_id`)
  REFERENCES `prednasky`.`file` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;
ALTER TABLE `prednasky`.`user_has_video` 
ADD CONSTRAINT `fk_user_has_video_video1`
  FOREIGN KEY (`video_id`)
  REFERENCES `prednasky`.`video` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;
ALTER TABLE `prednasky`.`video_relation` 
ADD CONSTRAINT `fk_video_has_video_video1`
  FOREIGN KEY (`video_from`)
  REFERENCES `prednasky`.`video` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_video_has_video_video2`
  FOREIGN KEY (`video_to`)
  REFERENCES `prednasky`.`video` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;
ALTER TABLE `prednasky`.`video_has_tag` 
ADD CONSTRAINT `fk_video_has_tag_video1`
  FOREIGN KEY (`video_id`)
  REFERENCES `prednasky`.`video` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;
ALTER TABLE `prednasky`.`token` 
ADD CONSTRAINT `fk_token_video1`
  FOREIGN KEY (`video`)
  REFERENCES `prednasky`.`video` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;
ALTER TABLE `prednasky`.`right` 
ADD CONSTRAINT `fk_user_has_tag_user1`
  FOREIGN KEY (`user_id`)
  REFERENCES `prednasky`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_user_has_tag_tag1`
  FOREIGN KEY (`tag_id`)
  REFERENCES `prednasky`.`tag` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.7";$$

-- 1.7 => 1.8-
ALTER TABLE `prednasky`.`video`
  DROP COLUMN `plane_width`,
  DROP COLUMN `plane_points`,
  CHANGE COLUMN `complete` `complete` TINYINT(1) NOT NULL ,
  ADD FULLTEXT INDEX `fulltext_name` (`name`),
  ADD FULLTEXT INDEX `fulltext_abstract` (`abstract`),
  ADD FULLTEXT INDEX `fulltext_name_abstract` (`name`, `abstract`);
;
INSERT INTO `prednasky`.`role` (`id`, `name`) VALUES (DEFAULT, 'owner');
DROP TABLE IF EXISTS `prednasky`.`right_has_tag`;
DROP TABLE IF EXISTS `prednasky`.`right`;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.8";$$

-- 1.8 => 1.9 --
ALTER TABLE `prednasky`.`user`
  DROP COLUMN `personal_web`,
  DROP COLUMN `institution`,
  CHANGE COLUMN `CAS_id` `CAS_id` VARCHAR(45) NULL DEFAULT NULL;
ALTER TABLE `prednasky`.`video`
  CHANGE COLUMN `complete` `complete` TINYINT(1) NOT NULL ,
  ADD FULLTEXT INDEX `fulltext_name` (`name`),
  ADD FULLTEXT INDEX `fulltext_abstract` (`abstract`),
  ADD FULLTEXT INDEX `fulltext_name_abstract` (`name`, `abstract`);
;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.9";$$
DELIMITER ;

-- 1.9 => 1.10 --
ALTER TABLE `prednasky`.`video`
DROP COLUMN `record_end`,
DROP COLUMN `record_begin`,
ADD COLUMN `record_date` DATE NULL DEFAULT NULL AFTER `published`,
ADD COLUMN `record_time_begin` TIME NULL DEFAULT NULL AFTER `record_date`,
ADD COLUMN `record_time_end` TIME NULL DEFAULT NULL AFTER `record_time_begin`,
CHANGE COLUMN `created` `created` TIMESTAMP NOT NULL ,
CHANGE COLUMN `complete` `complete` TINYINT(1) NOT NULL ,
CHANGE COLUMN `duration` `duration` INT(10) UNSIGNED NULL DEFAULT NULL ,
CHANGE COLUMN `public_link` `public_link` VARCHAR(100) NULL DEFAULT NULL ;
ALTER TABLE `prednasky`.`file`
ADD COLUMN `user` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `path`,
ADD INDEX `fk_file_user1_idx` (`user` ASC); ;
ALTER TABLE `prednasky`.`video_has_file`
DROP COLUMN `show`,
ADD COLUMN `type` VARCHAR(45) NOT NULL COMMENT 'thumbnail, video, attachment' AFTER `file_id`;
ALTER TABLE `prednasky`.`file`
ADD CONSTRAINT `fk_file_user1`
  FOREIGN KEY (`user`)
  REFERENCES `prednasky`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
DROP function IF EXISTS `prednasky`.`database_version`;
DELIMITER $$
USE `prednasky`$$
CREATE FUNCTION `database_version` () RETURNS varchar(5) CHARACTER SET 'utf8'
RETURN "1.10";$$
DELIMITER ;

-- END HERE --
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
