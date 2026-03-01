<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../sslg_voting.db');
    $db->exec("ALTER TABLE election_settings ADD COLUMN require_open_tokens INTEGER NOT NULL DEFAULT 0");
    echo "column added\n";
} catch (PDOException $e) {
    echo "error: " . $e->getMessage() . "\n";
}
