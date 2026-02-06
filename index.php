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

// Get school name and logo
$db = getDBConnection();
$stmt = $db->query("SELECT school_name, logo_path FROM school_info LIMIT 1");
$school_info = $stmt->fetch();
$school_name = $school_info['school_name'] ?? APP_NAME;
$school_logo_path = $school_info['logo_path'] ?? null;

// Get theme settings
$election = $db->query( "SELECT theme_color, logo_path FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
$theme_color = $election['theme_color'] ?? '#343a40';
$logo_path = $election['logo_path'] ?? 'assets/images/logo_1770105233.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --theme-color: <?php echo htmlspecialchars($theme_color); ?>;
        }
        .option-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            height: 100%;
        }
        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .welcome-section {
            background: linear-gradient(135deg, var(--theme-color) 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 50px;
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="welcome-section">
        <div class="container text-center">
            <?php if ($school_logo_path): ?>
                <img src="<?php echo htmlspecialchars($school_logo_path); ?>" alt="School Logo" class="mb-3" style="max-height: 100px;">
            <?php endif; ?>
            <h1 class="display-4 fw-bold"><?php echo htmlspecialchars($school_name ?? APP_NAME); ?></h1>
            <p class="lead">Student Supreme Learner Government Voting System</p>
            <p class="mt-3">Choose your access option below</p>
        </div>
    </div>

    <div class="container">
        <div class="row g-4 justify-content-center">
            <!-- Election Control Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card option-card border-success h-100" onclick="window.location.href='election_control.php'">
                    <div class="card-body text-center p-5">
                        <div class="card-icon text-primary">
                            <i class="bi bi-ballot-box"></i>
                        </div>
                        <h3 class="card-title text-primary fw-bold">Election Control</h3>
                        <p class="card-text text-muted">
                            Student voting portal for participating in elections and viewing results.
                        </p>
                        <div class="mt-4">
                            <span class="badge bg-success">Student Access</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Voting Control Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card option-card border-warning h-100" onclick="window.location.href='voting_control.php'">
                    <div class="card-body text-center p-5">
                        <div class="card-icon text-warning">
                            <i class="bi bi-gear"></i>
                        </div>
                        <h3 class="card-title text-warning fw-bold">Voting Control</h3>
                        <p class="card-text text-muted mb-3">
                            Administrative access for election management.
                        </p>
                        <div class="alert alert-warning text-start small">
                            <strong>Voting Control Instructions:</strong><br>
                            Use this section to start or close voting<br>
                            You need all 3 tokens from the commissions:<br>
                            • Chief Commissioner Token<br>
                            • Commission on Screening and Validation Token<br>
                            • Commission of Electoral Board Token
                        </div>
                        <div class="mt-4">
                            <span class="badge bg-warning text-dark">Admin Access</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Login Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card option-card border-danger h-100" onclick="window.location.href='admin_login.php'">
                    <div class="card-body text-center p-5">
                        <div class="card-icon text-danger">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h3 class="card-title text-danger fw-bold">Admin Login</h3>
                        <p class="card-text text-muted">
                            System administration and management access.
                        </p>
                        <div class="mt-4">
                            <span class="badge bg-danger">Administrator</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 text-center">
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>System Information:</strong> This is a secure voting system. All activities are logged and monitored.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
