<?php
require_once 'includes/config.php';

// Get school name
$db = getDBConnection();
$stmt = $db->query("SELECT school_name FROM school_info LIMIT 1");
$school_name = $stmt->fetchColumn();
$election = getElectionStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Election In Progress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .blocked-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .blocked-card {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            text-align: center;
        }
        .blocked-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        .blocked-title {
            color: #2d3748;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .blocked-message {
            color: #718096;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .blocked-action {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .blocked-action:hover {
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        .status-badge {
            display: inline-block;
            background: #ffc107;
            color: #333;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="blocked-container">
        <div class="blocked-card">
            <div class="blocked-icon">
                <i class="fas fa-lock"></i>
            </div>
            <div class="status-badge">
                <i class="fas fa-hourglass-half me-2"></i>Election In Progress
            </div>
            <h1 class="blocked-title">Registration Closed</h1>
            <p class="blocked-message">
                The election is currently in progress. New student registrations are temporarily disabled to ensure election integrity.
            </p>
            <p class="blocked-message">
                <strong>Election Status:</strong> Active
            </p>
            <?php if ($election && $election['end_date']): ?>
                <p class="blocked-message">
                    <strong>Expected End Time:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($election['end_date'])); ?>
                </p>
            <?php endif; ?>
            <p class="blocked-message">
                If you are an existing student, please proceed to log in and cast your vote. New students can register after the election concludes.
            </p>
            <div>
                <a href="vote_login.php" class="blocked-action me-3">
                    <i class="fas fa-vote-yea me-2"></i>Cast Your Vote
                </a>
                <a href="index.php" class="blocked-action" style="background: #6c757d;">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
