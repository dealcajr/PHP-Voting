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
    <title><?php echo $school_name ?? APP_NAME; ?> - Election Not Open</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .blocked-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #007a1b;
        }
        .blocked-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            text-align: center;
        }
        .blocked-icon {
            font-size: 4rem;
            color: #ffc107;
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
            background: #007a1b;
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
            background: #e9ecef;
            color: #495057;
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
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="status-badge">
                <i class="fas fa-info-circle me-2"></i>Election Not Open
            </div>
            <h1 class="blocked-title">Voting Not Available</h1>
            <p class="blocked-message">
                The election is not currently open. Voting will be available during the scheduled election period.
            </p>
            <p class="blocked-message">
                <strong>Election Status:</strong> Closed
            </p>
            <?php if ($election && $election['start_date']): ?>
                <p class="blocked-message">
                    <strong>Expected Start Time:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($election['start_date'])); ?>
                </p>
            <?php endif; ?>
            <p class="blocked-message">
                Please check back during the election period to cast your vote. You can also register as a new student if you have not done so already.
            </p>
            <div>
                <a href="election_control.php" class="blocked-action me-3">
                    <i class="fas fa-home me-2"></i>Back to Election Portal
                </a>
                <a href="index.php" class="blocked-action" style="background: #6c757d;">
                    <i class="fas fa-house me-2"></i>Home
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
