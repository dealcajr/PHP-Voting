<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $db = getDBConnection();

    // Check if 'manifesto' column exists
    $stmt = $db->query("PRAGMA table_info('candidates')");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasManifesto = false;
    foreach ($columns as $col) {
        if (isset($col['name']) && $col['name'] === 'manifesto') {
            $hasManifesto = true;
            break;
        }
    }

    if ($hasManifesto) {
        echo "Column 'manifesto' already exists in 'candidates' table.\n";
        exit(0);
    }

    // Add the manifesto column (SQLite supports ADD COLUMN)
    $db->exec("ALTER TABLE candidates ADD COLUMN manifesto TEXT DEFAULT NULL");

    echo "Added 'manifesto' column to 'candidates' table successfully.\n";
} catch (PDOException $e) {
    echo "Error updating database schema: " . $e->getMessage() . "\n";
    exit(1);
}

?>
