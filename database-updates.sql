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
  `user_id` INT(10) UNSIGNED NOT NULL,
  `tag_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `tag_id`),
  INDEX `fk_user_has_tag_tag1_idx` (`tag_id` ASC),
  INDEX `fk_user_has_tag_user1_idx` (`user_id` ASC),
  CONSTRAINT `fk_user_has_tag_user1`
    FOREIGN KEY (`user_id`)
    REFERENCES `prednasky`.`user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_has_tag_tag1`
    FOREIGN KEY (`tag_id`)
    REFERENCES `prednasky`.`tag` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_czech_ci;
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

-- END HERE --
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
