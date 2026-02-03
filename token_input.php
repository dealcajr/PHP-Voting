<?php
require_once 'includes/config.php';

// Check if user is logged in and is a voter
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'voter') {
    header('Location: login.php');
    exit();
}

// Check session timeout
checkSessionTimeout();

// Get election settings
$db = getDBConnection();
$election_stmt = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1");
$election = $election_stmt->fetch();

// Check if election is open
if (!$election || !$election['is_open']) {
    echo "<div class='alert alert-info'>The election is currently closed. Please check back later.</div>";
    exit();
}

// Check if election token is required
if (empty($election['election_token'])) {
    // No token required, redirect to vote
    header('Location: vote.php');
    exit();
}

// Check if token is already validated in session
if (isset($_SESSION['election_token_validated']) && $_SESSION['election_token_validated'] === true) {
    header('Location: vote.php');
    exit();
}

// Handle token submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request. Please try again.</div>';
    } else {
        $provided_token = trim($_POST['election_token'] ?? '');

        if (empty($provided_token)) {
            $message = '<div class="alert alert-danger">Please enter the election access token.</div>';
        } elseif ($provided_token !== $election['election_token']) {
            $message = '<div class="alert alert-danger">Invalid election access token. Please check with your election administrator.</div>';
        } else {
            // Token is valid, set session flag and update database
            $_SESSION['election_token_validated'] = true;
            $user_id = $_SESSION['user_id'];
            $stmt = $db->prepare("UPDATE users SET token_validated = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            header('Location: vote.php');
            exit();
        }
    }
}

// Get school name
$stmt = $db->query("SELECT school_name FROM school_info LIMIT 1");
$school_name = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Election Access</title>
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
                <h2 class="text-center mb-4">Election Access Token</h2>
                <p class="text-center text-muted mb-4">Please enter the election access token provided by your administrator to continue voting.</p>

                <?php echo $message; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="election_token" class="form-label">Election Access Token</label>
                        <input type="text" class="form-control form-control-lg" id="election_token" name="election_token" required autofocus placeholder="Enter token...">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="submit_token" class="btn btn-primary btn-lg">Continue to Vote</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="logout.php" class="text-muted">Logout</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
