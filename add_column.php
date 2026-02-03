<?php
require_once 'includes/config.php';

try {
    $db = getDBConnection();
    $db->exec("ALTER TABLE users ADD COLUMN token_validated TINYINT(1) DEFAULT 0");
    echo "Column 'token_validated' added successfully to users table.";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?>
