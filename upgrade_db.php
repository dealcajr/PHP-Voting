<?php
require_once 'includes/config.php';

try {
    $db = getDBConnection();

    // Add columns to election_settings table
    $db->exec("ALTER TABLE election_settings
        ADD COLUMN logo_path VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN theme_color VARCHAR(7) NULL DEFAULT '#343a40',
        ADD COLUMN allowed_ips TEXT NULL DEFAULT NULL,
        ADD COLUMN chief_commissioner_token VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN screening_validation_token VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN electoral_board_token VARCHAR(255) NULL DEFAULT NULL");

    // Create commissioner_logins table
    $db->exec("CREATE TABLE IF NOT EXISTS commissioner_logins (
        id int(11) NOT NULL AUTO_INCREMENT,
        token_used varchar(255) NOT NULL,
        ip_address varchar(45) NOT NULL,
        timestamp timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "Database upgrade completed successfully.";
} catch (PDOException $e) {
    echo "Error during upgrade: " . $e->getMessage();
}
?>
