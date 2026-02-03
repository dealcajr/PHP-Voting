ALTER TABLE `election_settings` 
ADD `logo_path` VARCHAR(255) NULL DEFAULT NULL, 
ADD `theme_color` VARCHAR(7) NULL DEFAULT '#343a40', 
ADD `allowed_ips` TEXT NULL DEFAULT NULL, 
ADD `chief_commissioner_token` VARCHAR(255) NULL DEFAULT NULL, 
ADD `screening_validation_token` VARCHAR(255) NULL DEFAULT NULL, 
ADD `electoral_board_token` VARCHAR(255) NULL DEFAULT NULL;

CREATE TABLE `commissioner_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_used` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
