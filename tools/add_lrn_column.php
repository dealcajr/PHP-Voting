<?php
require_once 'includes/config.php';

try {
    $db = getDBConnection();

    // Check if lrn column already exists
    $stmt = $db->query("PRAGMA table_info(users)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    if (!in_array('lrn', $columnNames)) {
        // Add lrn column to users table
        $db->exec("ALTER TABLE users ADD COLUMN lrn VARCHAR(20) DEFAULT NULL");

        echo "Successfully added 'lrn' column to users table.\n";
        logAdminAction('database_updated', 'Added lrn column to users table');
    } else {
        echo "'lrn' column already exists in users table.\n";
    }

} catch (PDOException $e) {
    echo "Error adding lrn column: " . $e->getMessage() . "\n";
}
?>
