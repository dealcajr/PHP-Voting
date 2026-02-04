<?php
require_once 'includes/config.php';

echo "=== Login Test ===\n\n";

try {
    $db = getDBConnection();

    // Test admin login
    echo "Testing admin login...\n";
    $stmt = $db->prepare("SELECT id, password_hash, role, student_id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        echo "Admin found: ID=" . $admin['id'] . ", StudentID=" . $admin['student_id'] . "\n";
        $test_password = 'password'; // This should match the hash
        if (password_verify($test_password, $admin['password_hash'])) {
            echo "✓ Admin password verification successful\n";
        } else {
            echo "✗ Admin password verification failed\n";
            echo "Hash: " . $admin['password_hash'] . "\n";
            echo "Testing with 'password': " . (password_verify('password', $admin['password_hash']) ? 'true' : 'false') . "\n";
        }
    } else {
        echo "✗ No active admin user found\n";
    }

    // Test voter login
    echo "\nTesting voter login...\n";
    $stmt = $db->prepare("SELECT id, password_hash, role, student_id FROM users WHERE student_id = ? AND is_active = 1");
    $stmt->execute(['STU001']);
    $voter = $stmt->fetch();

    if ($voter) {
        echo "Voter found: ID=" . $voter['id'] . ", StudentID=" . $voter['student_id'] . "\n";
        if (password_verify('password', $voter['password_hash'])) {
            echo "✓ Voter password verification successful\n";
        } else {
            echo "✗ Voter password verification failed\n";
        }
    } else {
        echo "✗ Voter STU001 not found\n";
    }

    // Check all users
    echo "\nAll users in database:\n";
    $stmt = $db->query("SELECT id, student_id, role, is_active FROM users");
    while ($user = $stmt->fetch()) {
        echo "- ID: {$user['id']}, StudentID: {$user['student_id']}, Role: {$user['role']}, Active: {$user['is_active']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
