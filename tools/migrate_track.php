<?php
require_once 'includes/config.php';

try {
    $db = getDBConnection();

    // Check if track column already exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'track'");
    $column = $stmt->fetch();

    if (!$column) {
        // Add the track column
        $db->exec("ALTER TABLE users ADD COLUMN track VARCHAR(100) DEFAULT NULL AFTER section");
        echo "✅ Track column added successfully to users table.\n";
    } else {
        echo "ℹ️ Track column already exists in users table.\n";
    }

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?>
