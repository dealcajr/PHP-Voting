<?php
// Use SQLite database
try {
    $db = new PDO('sqlite:sslg_voting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add missing columns to school_info table
    $db->exec("ALTER TABLE school_info ADD COLUMN school_id_no TEXT DEFAULT NULL");
    $db->exec("ALTER TABLE school_info ADD COLUMN principal_name TEXT DEFAULT NULL");

    echo "Columns 'school_id_no' and 'principal_name' added successfully to school_info table.";
} catch (PDOException $e) {
    echo "Error adding columns: " . $e->getMessage();
}
?>
