<?php
require_once 'includes/config.php';

// Check if user has completed LRN step
if (!isset($_SESSION['voting_lrn']) || !isset($_SESSION['voting_user_id'])) {
    header('Location: vote_login.php');
    exit();
}

// Check if user is already fully logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: vote.php');
    }
    exit();
}

// Handle password form submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $login_error = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';

        if (empty($password)) {
            $login_error = 'Please enter your password.';
        } else {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT password_hash, role, is_active FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['voting_user_id']]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $login_error = 'Your account is deactivated. Please contact an administrator.';
                    } else {
                        // Successful login for voting
                        $_SESSION['user_id'] = $_SESSION['voting_user_id'];
                        $_SESSION['student_id'] = $_SESSION['voting_student_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $_SESSION['voting_name'];

                        // Clear temporary voting session data
                        unset($_SESSION['voting_lrn']);
                        unset($_SESSION['voting_user_id']);
                        unset($_SESSION['voting_student_id']);
                        unset($_SESSION['voting_name']);

                        // For voters, check if election token is required
                        $election_stmt = $db->query("SELECT election_token FROM election_settings WHERE is_open = 1 ORDER BY id DESC LIMIT 1");
                        $election = $election_stmt->fetch();
                        if ($election && !empty($election['election_token'])) {
                            // Check if user has already validated token
                            $user_stmt = $db->prepare("SELECT token_validated FROM users WHERE id = ?");
                            $user_stmt->execute([$_SESSION['user_id']]);
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
                        exit();
                    }
                } else {
                    $login_error = 'Invalid password. Please try again.';
                }
            } catch (PDOException $e) {
                $login_error = 'Database error. Please try again later.';
                error_log("Vote password error: " . $e->getMessage());
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
    <title><?php echo $school_name ?? APP_NAME; ?> - Enter Password</title>
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
                <h2 class="text-center mb-4">Enter Password</h2>
                <p class="text-center text-muted mb-4">
                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['voting_name']); ?></strong><br>
                    Please enter your password to continue voting.
                </p>

                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required autofocus placeholder="Enter your password">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Continue to Vote</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="vote_login.php" class="text-muted">Back to LRN Entry</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
