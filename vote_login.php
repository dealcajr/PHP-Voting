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

// Handle LRN form submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $login_error = 'Invalid request. Please try again.';
    } else {
        $lrn = sanitizeInput($_POST['lrn'] ?? '');

        if (empty($lrn)) {
            $login_error = 'Please enter your LRN.';
        } elseif (!preg_match('/^\d{12}$/', $lrn)) {
            $login_error = 'LRN must be exactly 12 digits.';
        } else {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT id, role, is_active, first_name, last_name, student_id FROM users WHERE lrn = ? AND role = 'voter'");
                $stmt->execute([$lrn]);
                $user = $stmt->fetch();

                if ($user) {
                    if (!$user['is_active']) {
                        $login_error = 'Your account is deactivated. Please contact an administrator.';
                    } else {
                        // Store LRN in session and redirect to password page
                        $_SESSION['voting_lrn'] = $lrn;
                        $_SESSION['voting_user_id'] = $user['id'];
                        $_SESSION['voting_student_id'] = $user['student_id'];
                        $_SESSION['voting_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        header('Location: vote_password.php');
                        exit();
                    }
                } else {
                    $login_error = 'Invalid LRN. Please check your LRN and try again.';
                }
            } catch (PDOException $e) {
                $login_error = 'Database error. Please try again later.';
                error_log("Vote login error: " . $e->getMessage());
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
    <title><?php echo $school_name ?? APP_NAME; ?> - Vote Login</title>
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
                <h2 class="text-center mb-4">Vote Now</h2>
                <p class="text-center text-muted mb-4">Enter your LRN to cast your vote.</p>

                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                        <input type="text" class="form-control form-control-lg" id="lrn" name="lrn" required autofocus placeholder="Enter your 12-digit LRN" maxlength="12" pattern="\d{12}">
                        <small class="text-muted">Enter exactly 12 digits</small>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Continue to Vote</button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
