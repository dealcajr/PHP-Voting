<?php
require_once 'includes/config.php';

echo "Testing SSLG Voting System Authentication...\n\n";

// Test admin login
echo "--- Testing Admin Login ---\n";
$_POST = [
    'student_id' => 'ADMIN001',
    'password' => 'admin123',
    'csrf_token' => generateCSRFToken()
];

ob_start();
include 'login.php';
$content = ob_get_clean();

if (strpos($content, 'location: admin/dashboard.php') !== false || isset($_SESSION['user_id'])) {
    echo "✅ Admin login successful\n";
} else {
    echo "❌ Admin login failed\n";
}

// Test student login
echo "\n--- Testing Student Login ---\n";
session_destroy();
session_start();
$_POST = [
    'student_id' => 'STU001',
    'password' => 'admin123',
    'csrf_token' => generateCSRFToken()
];

ob_start();
include 'login.php';
$content = ob_get_clean();

if (strpos($content, 'location: vote.php') !== false || isset($_SESSION['user_id'])) {
    echo "✅ Student login successful\n";
} else {
    echo "❌ Student login failed\n";
}

// Test invalid login
echo "\n--- Testing Invalid Login ---\n";
session_destroy();
session_start();
$_POST = [
    'student_id' => 'INVALID',
    'password' => 'wrongpass',
    'csrf_token' => generateCSRFToken()
];

ob_start();
include 'login.php';
$content = ob_get_clean();

if (strpos($content, 'Invalid Student ID or Password') !== false) {
    echo "✅ Invalid login properly rejected\n";
} else {
    echo "❌ Invalid login not properly rejected\n";
}

echo "\n🎉 Authentication tests completed!\n";
?>
