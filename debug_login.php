<?php
require_once 'includes/config.php';

echo "=== Login Debug Test ===\n\n";

// Simulate login attempts
$test_cases = [
    ['student_id' => 'STU001', 'password' => 'password', 'expected' => 'success'],
    ['student_id' => 'stu001', 'password' => 'password', 'expected' => 'success'], // lowercase
    ['student_id' => 'STU001', 'password' => 'wrong', 'expected' => 'fail'],
    ['student_id' => 'ADMIN001', 'password' => 'password', 'expected' => 'success'],
    ['student_id' => 'INVALID', 'password' => 'password', 'expected' => 'fail'],
];

foreach ($test_cases as $test) {
    echo "Testing: StudentID='{$test['student_id']}', Password='{$test['password']}'\n";

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, password_hash, role, is_active, first_name FROM users WHERE LOWER(student_id) = LOWER(?)");
        $stmt->execute([$test['student_id']]);
        $user = $stmt->fetch();

        if ($user) {
            echo "  User found: ID={$user['id']}, Role={$user['role']}, Active={$user['is_active']}\n";
            if (password_verify($test['password'], $user['password_hash'])) {
                echo "  ✓ Password correct\n";
                if ($user['is_active']) {
                    echo "  ✓ Account active - LOGIN SUCCESSFUL\n";
                } else {
                    echo "  ✗ Account inactive\n";
                }
            } else {
                echo "  ✗ Password incorrect\n";
            }
        } else {
            echo "  ✗ User not found\n";
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Test admin login separately
echo "=== Admin Login Test ===\n";
echo "Testing admin password: 'password'\n";

try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, password_hash, role, is_active, first_name, student_id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        echo "Admin found: ID={$user['id']}, StudentID={$user['student_id']}\n";
        if (password_verify('password', $user['password_hash'])) {
            echo "✓ Admin password correct - LOGIN SUCCESSFUL\n";
        } else {
            echo "✗ Admin password incorrect\n";
        }
    } else {
        echo "✗ No active admin found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
