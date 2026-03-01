<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check session timeout
checkSessionTimeout();

// Get school info and logo
$db = getDBConnection();
$stmt = $db->query("SELECT school_name, logo_path FROM school_info LIMIT 1");
$school_info = $stmt ? $stmt->fetch() : null;
$school_name = $school_info['school_name'] ?? APP_NAME;
$school_logo_path = $school_info['logo_path'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Election Closed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .logo-navbar {
            max-height: 50px;
            margin-right: 15px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <?php if ($school_logo_path): ?>
                <img src="<?php echo htmlspecialchars($school_logo_path); ?>" alt="School Logo" class="logo-navbar">
            <?php endif; ?>
            <span class="navbar-text text-light"><strong><?php echo htmlspecialchars($school_name); ?></strong></span>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card text-center">
                    <div class="card-header bg-info text-white">
                        <h2>Election Closed</h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h4>The election is currently closed.</h4>
                            <p>Please check back later or view the election results.</p>
                        </div>
                        <div class="mt-4">
                            <a href="results.php" class="btn btn-primary btn-lg me-3">View Election Results</a>
                            <a href="logout.php" class="btn btn-secondary btn-lg">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
