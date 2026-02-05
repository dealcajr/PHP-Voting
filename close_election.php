<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: vote_login.php');
    exit();
}

$db = getDBConnection();

// Get election settings
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

// Determine if the user can view the results.
$can_view_results = false;
// Scenario 1: Election is closed, so results are public.
if (!$election['is_open']) {
    $can_view_results = true;
} else {
    // Scenario 2: Election is open, check if the current user has already voted.
    $stmt = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        $can_view_results = true;
    }
}

$is_close = false;
$close_positions = [];

if ($can_view_results) {
    // Get all candidate results
    $stmt = $db->query("
        SELECT c.position, c.name, c.party, c.section, c.photo, COUNT(v.id) as vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position
        WHERE c.is_active = 1
        GROUP BY c.id, c.position
        ORDER BY c.position ASC, vote_count DESC, c.name ASC
    ");
    $all_candidates = $stmt->fetchAll();

    // Group candidates by position
    $results = [];
    foreach ($all_candidates as $candidate) {
        $results[$candidate['position']][] = $candidate;
    }

    // Check for close elections (margin of 5 votes or less)
    foreach ($results as $position => $candidates) {
        if (count($candidates) >= 2) {
            // Sort by vote_count desc
            usort($candidates, function($a, $b) {
                return $b['vote_count'] - $a['vote_count'];
            });
            $top1 = $candidates[0]['vote_count'];
            $top2 = $candidates[1]['vote_count'];
            $margin = $top1 - $top2;
            if ($margin <= 5) {
                $is_close = true;
                $close_positions[] = [
                    'position' => $position,
                    'top1' => $candidates[0],
                    'top2' => $candidates[1],
                    'margin' => $margin
                ];
            }
        }
    }

    // Get total votes cast
    $total_votes_cast = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
}

if (!$is_close) {
    // If not close, redirect to normal results
    header('Location: results.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Close Election Alert</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .alert-close {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
        }
        .close-icon {
            font-size: 4rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo APP_NAME; ?></a>
            <div class="navbar-nav ms-auto">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a class="nav-link" href="admin/index.php">Admin Dashboard</a>
                <?php else: ?>
                    <a class="nav-link" href="vote.php">Vote</a>
                <?php endif; ?>
                <a class="nav-link" href="results.php">Full Results</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="alert alert-close text-center mb-4" role="alert">
            <div class="close-icon mb-3">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h1 class="alert-heading">Election Too Close to Call!</h1>
            <p class="mb-0">The following positions have extremely close results with a margin of 5 votes or less.</p>
        </div>

        <p class="lead"><?php echo htmlspecialchars($election['election_name']); ?></p>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>Total Votes Cast</h5>
                        <h2 class="text-primary"><?php echo $total_votes_cast; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>Election Status</h5>
                        <span class="badge <?php echo $election['is_open'] ? 'bg-success' : 'bg-danger'; ?> fs-5">
                            <?php echo $election['is_open'] ? 'Open' : 'Closed'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($close_positions as $close): ?>
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h4><?php echo htmlspecialchars($close['position']); ?> - Margin: <?php echo $close['margin']; ?> votes</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100 border-primary">
                                <div class="card-body text-center">
                                    <?php if ($close['top1']['photo']): ?>
                                        <img src="<?php echo htmlspecialchars($close['top1']['photo']); ?>" class="img-fluid rounded mb-2" alt="Candidate Photo" style="max-height: 150px;">
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($close['top1']['name']); ?> (Leading)</h5>
                                    <p class="mb-1"><strong>Party:</strong> <?php echo htmlspecialchars($close['top1']['party'] ?? 'Independent'); ?></p>
                                    <p class="mb-1"><strong>Section:</strong> <?php echo htmlspecialchars($close['top1']['section']); ?></p>
                                    <p class="mb-0"><strong>Votes:</strong> <?php echo $close['top1']['vote_count']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 border-secondary">
                                <div class="card-body text-center">
                                    <?php if ($close['top2']['photo']): ?>
                                        <img src="<?php echo htmlspecialchars($close['top2']['photo']); ?>" class="img-fluid rounded mb-2" alt="Candidate Photo" style="max-height: 150px;">
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($close['top2']['name']); ?> (Runner-up)</h5>
                                    <p class="mb-1"><strong>Party:</strong> <?php echo htmlspecialchars($close['top2']['party'] ?? 'Independent'); ?></p>
                                    <p class="mb-1"><strong>Section:</strong> <?php echo htmlspecialchars($close['top2']['section']); ?></p>
                                    <p class="mb-0"><strong>Votes:</strong> <?php echo $close['top2']['vote_count']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="text-center">
            <a href="results.php" class="btn btn-primary btn-lg">View Full Results</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
