<?php
try {
    $db = new PDO('sqlite:c:/xampp/htdocs/phpvoting/sslg_voting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column exists
    $result = $db->query("PRAGMA table_info(election_settings)")->fetchAll(PDO::FETCH_ASSOC);
    $col_exists = array_search('require_open_tokens', array_column($result, 'name'));
    
    if ($col_exists === false) {
        $db->exec('ALTER TABLE election_settings ADD COLUMN require_open_tokens INTEGER NOT NULL DEFAULT 0');
        echo "Column require_open_tokens added successfully.\n";
    } else {
        echo "Column require_open_tokens already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
