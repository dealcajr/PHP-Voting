-- Add commissioners table and insert the 3 commissioners
CREATE TABLE IF NOT EXISTS `commissioners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commission_type` enum('chief','screening','electoral') NOT NULL,
  `name` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_type` (`commission_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert the 3 commissioners with their tokens
INSERT INTO `commissioners` (`commission_type`, `name`, `token`, `is_active`) VALUES
('chief', 'Chief Commissioner', 'CHIEF2024', 1),
('screening', 'Commission on Screening and Validation', 'SCREEN2024', 1),
('electoral', 'Commission of Electoral Board', 'ELECT2024', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `token` = VALUES(`token`),
  `is_active` = VALUES(`is_active`),
  `updated_at` = CURRENT_TIMESTAMP;
