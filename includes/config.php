<?php
// SSLG Voting System Configuration
// Database configuration
define('DB_FILE', __DIR__ . '/../sslg_voting.db'); // SQLite database file path

// Application settings
define('APP_NAME', 'SSLG Voting System');
define('SCHOOL_NAME', 'Bonifacio D. Borebor Sr. High School');
define('APP_VERSION', '1.0.0');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// File upload settings
define('UPLOAD_DIR', 'assets/images/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Database connection function
function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO(
                "sqlite:" . DB_FILE,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Session timeout check
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Role-based access control
function requireRole($role) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Location: login.php?access_denied=1');
        exit();
    }
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Generate unique student ID
function generateStudentID() {
    $db = getDBConnection();

    // Find the highest existing StuXXX ID
    $stmt = $db->prepare("SELECT student_id FROM users WHERE student_id LIKE 'STU%' ORDER BY CAST(SUBSTRING(student_id, 4) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute();
    $last_id = $stmt->fetchColumn();

    if ($last_id) {
        // Extract the number part and increment
        $number = (int)substr($last_id, 3);
        $next_number = $number + 1;
    } else {
        // Start from 1 if no Stu IDs exist
        $next_number = 1;
    }

    // Generate the new ID with Stu prefix and 3-digit number
    $student_id = 'STU' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

    // Double-check if this ID already exists (shouldn't happen with sequential generation)
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        // If it exists (rare case), try incrementing once more
        $next_number++;
        $student_id = 'STU' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }

    return $student_id;
}

// Log admin actions
function logAdminAction($action, $details = '') {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}
?>