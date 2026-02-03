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
    <title><?php echo $school_name ?? APP_NAME; ?> - Election Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
            <h1 class="display-4 fw-bold">Election Control</h1>
            <p class="lead">Student Voting Portal</p>
            <p class="mt-3">Access your voting options below</p>
        </div>
    </div>

    <div class="container">
        <div class="row g-4 justify-content-center">
            <!-- Vote Now Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card option-card border-primary h-100" onclick="window.location.href='vote_login.php'">
                    <div class="card-body text-center p-5">
                        <div class="card-icon text-primary">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <h3 class="card-title text-primary fw-bold">Vote Now</h3>
                        <p class="card-text text-muted">
                            Enter your Student ID to cast your vote in the current election.
                        </p>
                        <div class="mt-4">
                            <span class="badge bg-primary">Quick Vote</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Login Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card option-card border-success h-100" onclick="window.location.href='login.php'">
                    <div class="card-body text-center p-5">
                        <div class="card-icon text-success">
                            <i class="bi bi-box-arrow-in-right"></i>
                        </div>
                        <h3 class="card-title text-success fw-bold">Student Login</h3>
                        <p class="card-text text-muted">
                            Sign in with your Student ID and password to access your account and view results.
                        </p>
                        <div class="mt-4">
                            <span class="badge bg-success">Full Access</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Registration Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card option-card border-info h-100" onclick="window.location.href='student_register.php'">
                    <div class="card-body text-center p-5">
                        <div class="card-icon text-info">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <h3 class="card-title text-info fw-bold">Student Registration</h3>
                        <p class="card-text text-muted">
                            New to the system? Register your account to participate in school elections.
                        </p>
                        <div class="mt-4">
                            <span class="badge bg-info">Get Started</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 text-center">
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Ready to Vote:</strong> Make sure you have your Student ID and are registered to participate in elections.
                </div>
                <a href="index.php" class="btn btn-outline-secondary mt-3">
                    <i class="bi bi-arrow-left me-2"></i>Back to Main Menu
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
