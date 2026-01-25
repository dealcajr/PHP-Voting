<?php
require_once 'includes/config.php';

// Check if user is logged in (voters can view results after voting)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get election settings
$db = getDBConnection();
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

// Only show results if election is closed or user has voted
$can_view_results = !$election['is_open'];
if (!$can_view_results) {
    $has_voted = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ?")->execute([$_SESSION['user_id']])->fetchColumn() > 0;
    $can_view_results = $has_voted;
}

if (!$can_view_results) {
    echo "<div class='alert alert-info'>Results will be available after the election closes or after you have voted.</div>";
    exit();
}

// Get results by position
$positions = $db->query("SELECT DISTINCT position FROM candidates WHERE is_active = 1 ORDER BY position")->fetchAll(PDO::FETCH_COLUMN);

$results = [];
foreach ($positions as $position) {
    $stmt = $db->prepare("
        SELECT c.name, c.party, c.section, c.photo, COUNT(v.id) as vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position
        WHERE c.position = ? AND c.is_active = 1
        GROUP BY c.id
        ORDER BY vote_count DESC, c.name ASC
    ");
    $stmt->execute([$position]);
    $results[$position] = $stmt->fetchAll();
}

// Get total votes cast
$total_votes_cast = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
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
                    <a class="nav-link" href="admin/dashboard.php">Admin Dashboard</a>
                <?php else: ?>
                    <a class="nav-link" href="vote.php">Vote</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Election Results</h1>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
