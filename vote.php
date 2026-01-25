<?php
require_once 'includes/config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'voter') {
    header('Location: login.php');
    exit();
}

// Check if election is open
$db = getDBConnection();
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
if (!$election['is_open']) {
    echo "<div class='alert alert-info'>The election is currently closed. Please check back later.</div>";
    exit();
}

// Check if user has already voted
$has_voted = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ?")->execute([$_SESSION['user_id']])->fetchColumn() > 0;
if ($has_voted) {
    echo "<div class='alert alert-success'>You have already voted. <a href='results.php'>View Results</a></div>";
    exit();
}

// Handle vote submission
$vote_error = '';
$vote_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $vote_error = 'Invalid request. Please try again.';
    } else {
        $votes = $_POST['votes'] ?? [];
        if (empty($votes)) {
            $vote_error = 'Please select at least one candidate.';
        } else {
            try {
                $db->beginTransaction();
                $receipt_code = bin2hex(random_bytes(8)); // Generate receipt code

                foreach ($votes as $position => $candidate_id) {
                    // Get candidate details
                    $candidate = $db->prepare("SELECT * FROM candidates WHERE id = ? AND is_active = 1")->execute([$candidate_id])->fetch();
                    if (!$candidate) {
                        throw new Exception("Invalid candidate selected for $position.");
                    }

                    // Check if already voted for this position
                    $existing_vote = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND position = ?")->execute([$_SESSION['user_id'], $position])->fetchColumn();
                    if ($existing_vote > 0) {
                        throw new Exception("You have already voted for $position.");
                    }

                    // Insert vote
                    $vote_hash = hash('sha256', $candidate_id . $position . time() . random_bytes(16));
                    $stmt = $db->prepare("INSERT INTO votes (voter_id, candidate_id, position, vote_hash) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $candidate_id, $position, $vote_hash]);
                }

                $db->commit();
                $vote_success = "Your vote has been recorded successfully! Receipt Code: <span class='receipt-code'>$receipt_code</span><br><a href='results.php'>View Results</a>";
                logAdminAction('vote_cast', "Voter {$_SESSION['student_id']} cast vote. Receipt: $receipt_code");

                // Prevent further voting
                $has_voted = true;
            } catch (Exception $e) {
                $db->rollBack();
                $vote_error = $e->getMessage();
            }
        }
    }
}

// Get candidates grouped by position
$positions = $db->query("SELECT DISTINCT position FROM candidates WHERE is_active = 1 ORDER BY position")->fetchAll(PDO::FETCH_COLUMN);

$candidates_by_position = [];
foreach ($positions as $position) {
    $stmt = $db->prepare("SELECT * FROM candidates WHERE position = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$position]);
    $candidates_by_position[$position] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Vote</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo APP_NAME; ?></a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="results.php">Results</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Cast Your Vote</h1>
        <p class="lead"><?php echo htmlspecialchars($election['election_name']); ?></p>

        <?php if ($vote_error): ?>
            <div class="alert alert-danger"><?php echo $vote_error; ?></div>
        <?php endif; ?>
        <?php if ($vote_success): ?>
            <div class="alert alert-success vote-confirmation"><?php echo $vote_success; ?></div>
        <?php endif; ?>

        <?php if (!$has_voted && $election['is_open']): ?>
            <form method="POST" action="" id="voteForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <?php foreach ($candidates_by_position as $position => $candidates): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4><?php echo htmlspecialchars($position); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($candidates as $candidate): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card candidate-card h-100" data-position="<?php echo htmlspecialchars($position); ?>" data-candidate="<?php echo $candidate['id']; ?>">
                                            <div class="card-body text-center">
                                                <?php if ($candidate['photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($candidate['photo']); ?>" class="img-fluid rounded mb-2" alt="Candidate Photo" style="max-height: 150px;">
                                                <?php endif; ?>
                                                <h5><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                                <p class="mb-1"><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?></p>
                                                <p class="mb-0"><strong>Section:</strong> <?php echo htmlspecialchars($candidate['section']); ?></p>
                                                <input type="radio" name="votes[<?php echo htmlspecialchars($position); ?>]" value="<?php echo $candidate['id']; ?>" class="d-none">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="text-center">
                    <button type="button" class="btn btn-primary btn-lg" id="submitVoteBtn">Submit Vote</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Vote Confirmation Modal -->
    <div class="modal fade" id="confirmVoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Your Vote</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit your vote? You cannot change your vote after submission.</p>
                    <div id="voteSummary"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmVoteBtn">Confirm Vote</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
