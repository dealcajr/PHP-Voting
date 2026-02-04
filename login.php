<?php
require_once 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: vote.php');
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
                $stmt = $db->prepare("SELECT id, password_hash, role, is_active, first_name FROM users WHERE LOWER(student_id) = LOWER(?)");
                $stmt->execute([$student_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $login_error = 'Your account is deactivated. Please contact an administrator.';
                    } else {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['student_id'] = $student_id;
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $user['first_name'];

                        // Log admin login
                        if ($user['role'] === 'admin') {
                            logAdminAction('login', 'Admin logged in');
                        }

                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header('Location: admin/index.php');
                        } else {
                        // For voters, check if election token is required
                            $db = getDBConnection();
                            $election_stmt = $db->query("SELECT election_token FROM election_settings WHERE is_open = 1 ORDER BY id DESC LIMIT 1");
                            $election = $election_stmt->fetch();
                            if ($election && !empty($election['election_token'])) {
                                // Check if user has already validated token
                                $user_stmt = $db->prepare("SELECT token_validated FROM users WHERE id = ?");
                                $user_stmt->execute([$user['id']]);
                                $user_data = $user_stmt->fetch();
                                if ($user_data && $user_data['token_validated'] == 1) {
                                    $_SESSION['election_token_validated'] = true;
                                    header('Location: vote.php');
                                } else {
                                    header('Location: token_input.php');
                                }
                            } else {
                                header('Location: vote.php');
                            }
                        }
                        exit();
                    }
                } else {
                    $login_error = 'Invalid Student ID or Password.';
                }
            } catch (PDOException $e) {
                $login_error = 'Database error. Please try again later.';
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}

// Get school name
$db = getDBConnection();
$stmt = $db->query("SELECT school_name FROM school_info LIMIT 1");
$school_name = $stmt->fetchColumn();

// Check for timeout or access denied messages
$timeout = isset($_GET['timeout']) ? 'Your session has expired. Please log in again.' : '';
$access_denied = isset($_GET['access_denied']) ? 'Access denied. Please log in.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-branding">
                <h1><?php echo htmlspecialchars($school_name ?? APP_NAME); ?></h1>
                <p class="lead">SSLG Voting System</p>
            </div>
            <div class="login-form">
                <h2 class="text-center mb-4">Account Login</h2>
                <p class="text-center text-muted mb-4">Sign in with your Student ID and password for full account access.</p>

                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <?php if ($timeout): ?>
                    <div class="alert alert-warning"><?php echo $timeout; ?></div>
                <?php endif; ?>
                <?php if ($access_denied): ?>
                    <div class="alert alert-warning"><?php echo $access_denied; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student ID</label>
                        <input type="text" class="form-control form-control-lg" id="student_id" name="student_id" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>


            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
