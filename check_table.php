<?php
require_once 'includes/config.php';

try {
    $db = getDBConnection();
    $result = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="commissioners"');
    if ($result->fetch()) {
        echo 'Commissioners table exists.';
        $count = $db->query('SELECT COUNT(*) FROM commissioners')->fetchColumn();
        echo ' Records: ' . $count;
    } else {
        echo 'Commissioners table does not exist.';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
