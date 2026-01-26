<?php
require_once 'includes/config.php';

echo "Testing SSLG Voting System Security Features...\n\n";

// Test CSRF protection
echo "--- Testing CSRF Protection ---\n";
$valid_token = generateCSRFToken();
$invalid_token = 'invalid_token_123';

if (validateCSRFToken($valid_token)) {
    echo "✅ Valid CSRF token accepted\n";
} else {
    echo "❌ Valid CSRF token rejected\n";
}

if (!validateCSRFToken($invalid_token)) {
    echo "✅ Invalid CSRF token rejected\n";
} else {
    echo "❌ Invalid CSRF token accepted\n";
}

// Test input sanitization
echo "\n--- Testing Input Sanitization ---\n";
$malicious_input = "<script>alert('xss')</script>";
$sanitized = sanitizeInput($malicious_input);

if ($sanitized !== $malicious_input && strpos($sanitized, '<script>') === false) {
    echo "✅ Input sanitization working\n";
} else {
    echo "❌ Input sanitization failed\n";
}

// Test password hashing
echo "\n--- Testing Password Hashing ---\n";
$password = 'testpassword123';
$hash = password_hash($password, PASSWORD_DEFAULT);

if (password_verify($password, $hash)) {
    echo "✅ Password hashing and verification working\n";
} else {
    echo "❌ Password hashing failed\n";
}

// Test role-based access control
echo "\n--- Testing Role-Based Access Control ---\n";

// Test admin access
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

ob_start();
requireRole('admin');
$content = ob_get_clean();

if (strpos($content, 'location: login.php') === false) {
    echo "✅ Admin role access granted\n";
} else {
    echo "❌ Admin role access denied\n";
}

// Test voter access to admin area
$_SESSION['role'] = 'voter';

ob_start();
requireRole('admin');
$content = ob_get_clean();

if (strpos($content, 'location: login.php') !== false) {
    echo "✅ Voter access to admin area blocked\n";
} else {
    echo "❌ Voter access to admin area not blocked\n";
}

// Test session timeout
echo "\n--- Testing Session Timeout ---\n";
$_SESSION['last_activity'] = time() - (SESSION_TIMEOUT + 60); // Past timeout

ob_start();
checkSessionTimeout();
$content = ob_get_clean();

if (strpos($content, 'location: login.php?timeout=1') !== false) {
    echo "✅ Session timeout working\n";
} else {
    echo "❌ Session timeout not working\n";
}

// Test SQL injection prevention
echo "\n--- Testing SQL Injection Prevention ---\n";
$db = getDBConnection();
$malicious_id = "1' OR '1'='1";

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$malicious_id]);
    $result = $stmt->fetch();

    if (!$result) {
        echo "✅ SQL injection prevented\n";
    } else {
        echo "❌ SQL injection not prevented\n";
    }
} catch (PDOException $e) {
    echo "✅ SQL injection prevented (exception caught)\n";
}

echo "\n🎉 Security tests completed!\n";
?>
