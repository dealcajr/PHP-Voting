<?php
require_once 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// Handle login form submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $login_error = 'Invalid request. Please try again.';
    } else {
        $student_id = sanitizeInput($_POST['student_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($student_id) || empty($password)) {
            $login_error = 'Please enter both Student ID and Password.';
        } else {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT id, password_hash, role, is_active, first_name FROM users WHERE student_id = ? AND role = 'admin'");
                $stmt->execute([$student_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $login_error = 'Your account is deactivated. Please contact system administrator.';
                    } else {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['student_id'] = $student_id;
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $user['first_name'];

                        // Log admin login
                        logAdminAction('admin_login', 'Administrator logged in');

                        header('Location: admin/index.php');
                        exit();
                    }
                } else {
                    $login_error = 'Invalid administrator credentials.';
                }
            } catch (PDOException $e) {
                $login_error = 'Database error. Please try again later.';
                error_log("Admin login error: " . $e->getMessage());
            }
        }
    }
}

// Get school name
$db = getDBConnection();
$stmt = $db->query("SELECT school_name FROM school_info LIMIT 1");
$school_name = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-branding">
                <h1><?php echo htmlspecialchars($school_name ?? APP_NAME); ?></h1>
                <p class="lead">Administrator Access</p>
            </div>
            <div class="login-form">
                <h2 class="text-center mb-4">Admin Login</h2>
                <p class="text-center text-muted mb-4">Enter your administrator credentials to access the system.</p>

                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Administrator ID</label>
                        <input type="text" class="form-control form-control-lg" id="student_id" name="student_id" required autofocus placeholder="Enter admin ID">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required placeholder="Enter password">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Login as Administrator</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted">← Back to Main Menu</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
