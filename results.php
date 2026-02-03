<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

$show_results = $can_view_results;

if ($show_results) {
    // Get all candidate results in a single, more efficient query
    $stmt = $db->query("
        SELECT c.position, c.name, c.party, c.section, c.photo, COUNT(v.id) as vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position
        WHERE c.is_active = 1
        GROUP BY c.id, c.position
        ORDER BY c.position ASC, vote_count DESC, c.name ASC
    ");
    $all_candidates = $stmt->fetchAll();

    // Group candidates by position for easy display
    $results = [];
    foreach ($all_candidates as $candidate) {
        $results[$candidate['position']][] = $candidate;
    }

    // Check for close elections (margin of 5 votes or less)
    $is_close = false;
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
                break; // No need to check further if any position is close
            }
        }
    }

    if ($is_close) {
        // Redirect to close election page
        header('Location: close_election.php');
        exit();
    }

    // Get total votes cast
    $total_votes_cast = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Election Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Election Results</h1>

        <?php if ($show_results): ?>
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

            <?php foreach ($results as $position => $candidates): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><?php echo htmlspecialchars($position); ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($candidates as $candidate): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <?php if ($candidate['photo']): ?>
                                                <img src="<?php echo htmlspecialchars($candidate['photo']); ?>" class="img-fluid rounded mb-2" alt="Candidate Photo" style="max-height: 150px;">
                                            <?php endif; ?>
                                            <h5><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                            <p class="mb-1"><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?></p>
                                            <p class="mb-1"><strong>Section:</strong> <?php echo htmlspecialchars($candidate['section']); ?></p>
                                            <p class="mb-0"><strong>Votes:</strong> <?php echo $candidate['vote_count']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Chart for this position -->
                        <canvas id="chart-<?php echo md5($position); ?>" width="400" height="200"></canvas>
                        <script>
                            const ctx<?php echo md5($position); ?> = document.getElementById('chart-<?php echo md5($position); ?>');
                            new Chart(ctx<?php echo md5($position); ?>, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo json_encode(array_column($candidates, 'name')); ?>,
                                    datasets: [{
                                        label: 'Votes',
                                        data: <?php echo json_encode(array_column($candidates, 'vote_count')); ?>,
                                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        </script>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">Results will be available after the election closes or after you have voted.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
