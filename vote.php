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

// Check if election token is required and validated in session
if (!empty($election['election_token'])) {
    if (!isset($_SESSION['election_token_validated']) || $_SESSION['election_token_validated'] !== true) {
        header('Location: token_input.php');
        exit();
    }
}

// Check if user has already voted for all positions
$user_id = $_SESSION['user_id'];
$positions_stmt = $db->query("SELECT DISTINCT position FROM candidates WHERE is_active = 1 ORDER BY position");
$positions = $positions_stmt->fetchAll(PDO::FETCH_COLUMN);
$has_voted_all = true;
if ($positions) {
    foreach ($positions as $position) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND position = ?");
        $stmt->execute([$user_id, $position]);
        if ($stmt->fetchColumn() == 0) {
            $has_voted_all = false;
            break;
        }
    }
}

if ($has_voted_all && $positions) {
    echo "<div class='alert alert-success'>You have already voted for all positions. Thank you for participating!</div>";
    echo "<a href='results.php' class='btn btn-primary'>View Results</a>";
    exit();
}

// Handle vote submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_vote'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request. Please try again.</div>';
    } else {
        $votes = $_POST['votes'] ?? [];
        $confirmation = $_POST['confirmation'] ?? '';

        if ($confirmation !== 'CONFIRM') {
            $message = '<div class="alert alert-danger">Please confirm your vote by typing CONFIRM.</div>';
        } elseif (empty($votes)) {
            $message = '<div class="alert alert-danger">Please select at least one candidate.</div>';
        } else {
            try {
                $db->beginTransaction();
                $votes_cast = 0;

                foreach ($votes as $position => $candidate_id) {
                    // Check if user already voted for this position
                    $stmt = $db->prepare("SELECT id FROM votes WHERE voter_id = ? AND position = ?");
                    $stmt->execute([$user_id, $position]);
                    $existing_vote = $stmt->fetch();

                    if (!$existing_vote) {
                        // Get candidate info
                        $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ? AND is_active = 1");
                        $stmt->execute([$candidate_id]);
                        $candidate = $stmt->fetch();

                        if ($candidate) {
                            // Create vote hash for integrity
                            $vote_data = $user_id . $candidate_id . $position . time();
                            $vote_hash = hash('sha256', $vote_data);

                            // Insert vote
                            $stmt = $db->prepare("INSERT INTO votes (voter_id, candidate_id, position, vote_hash) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$user_id, $candidate_id, $position, $vote_hash]);
                            $votes_cast++;
                        }
                    }
                }

                $db->commit();

                if ($votes_cast > 0) {
                    $message = "<div class='alert alert-success'>Your vote has been recorded successfully! You voted for $votes_cast position(s).</div>";
                    // Clear the votes array to prevent re-submission
                    $votes = [];
                } else {
                    $message = '<div class="alert alert-info">You have already voted for all selected positions.</div>';
                }

            } catch (PDOException $e) {
                $db->rollBack();
                $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
                error_log("Vote submission error: " . $e->getMessage());
            }
        }
    }
}

// Get candidates grouped by position
$candidates_by_position = [];
if($positions) {
    foreach ($positions as $position) {
        $stmt = $db->prepare("SELECT * FROM candidates WHERE position = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$position]);
        $candidates_by_position[$position] = $stmt->fetchAll();
    }
}


// Check which positions user has already voted for
$voted_positions = [];
if ($positions) {
    foreach ($positions as $position) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND position = ?");
        $stmt->execute([$user_id, $position]);
        if ($stmt->fetchColumn() > 0) {
            $voted_positions[] = $position;
        }
    }
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
                <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['student_id']); ?></span>
                <a class="nav-link" href="results.php">Results</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <h1>Cast Your Vote</h1>
                <p class="lead"><?php echo htmlspecialchars($election['election_name'] ?? 'SSLG Election'); ?></p>

                <?php echo $message; ?>

                <?php if (!$has_voted_all): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="submit_vote" value="1">

                        <?php foreach ($candidates_by_position as $position => $candidates): ?>
                            <?php if (!in_array($position, $voted_positions)): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h4><?php echo htmlspecialchars($position); ?></h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($candidates as $candidate): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card h-100 candidate-card <?php echo (isset($votes[$position]) && $votes[$position] == $candidate['id']) ? 'border-primary' : ''; ?>">
                                                        <div class="card-body">
                                                            <?php if ($candidate['photo']): ?>
                                                                <img src="<?php echo htmlspecialchars($candidate['photo']); ?>" class="img-fluid rounded mb-2" alt="Candidate Photo" style="max-height: 150px;">
                                                            <?php endif; ?>
                                                            <h5><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                                            <p class="mb-1"><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?></p>
                                                            <p class="mb-1"><strong>Grade & Section:</strong> <?php echo htmlspecialchars('Grade ' . $candidate['grade'] . '-' . $candidate['section']); ?></p>
                                                            <?php if (!empty($candidate['manifesto'])): ?>
                                                                <p class="mb-2"><small><strong>Platform:</strong> <?php echo htmlspecialchars(substr($candidate['manifesto'], 0, 100)); ?><?php echo strlen($candidate['manifesto']) > 100 ? '...' : ''; ?></small></p>
                                                            <?php endif; ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="votes[<?php echo htmlspecialchars($position); ?>]" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>" required>
                                                                <label class="form-check-label" for="candidate_<?php echo $candidate['id']; ?>">
                                                                    Select <?php echo htmlspecialchars($candidate['name']); ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card mb-4 border-success">
                                    <div class="card-header bg-success text-white">
                                        <h4><?php echo htmlspecialchars($position); ?> - Already Voted ✓</h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-success">You have already cast your vote for this position.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (count($candidates_by_position) > count($voted_positions)): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-warning">
                                    <h4>Confirm Your Vote</h4>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning">
                                        <strong>Important:</strong> Once submitted, your vote cannot be changed. Please review your selections carefully.
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirmation" class="form-label">Type "CONFIRM" to submit your vote:</label>
                                        <input type="text" class="form-control" id="confirmation" name="confirmation" required>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-lg">Submit Vote</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php if ($has_voted_all): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <h4 class="text-success">Voting Complete!</h4>
                            <p>You have successfully voted for all available positions. Thank you for participating in the election.</p>
                            <a href="results.php" class="btn btn-primary">View Election Results</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Voting Progress</h4>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3" style="height: 20px;">
                            <?php $progress = count($positions) > 0 ? (count($voted_positions) / count($positions)) * 100 : 0; ?>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($progress, 1); ?>%
                            </div>
                        </div>
                        <p><strong><?php echo count($voted_positions); ?>/<?php echo count($positions); ?> positions voted</strong></p>
                        <ul class="list-unstyled">
                            <?php foreach ($positions as $position): ?>
                                <li>
                                    <i class="bi <?php echo in_array($position, $voted_positions) ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'; ?>"></i>
                                    <?php echo htmlspecialchars($position); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Voting Rules</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">✓ One vote per position</li>
                            <li class="mb-2">✓ Votes are anonymous</li>
                            <li class="mb-2">✓ Cannot change vote once submitted</li>
                            <li class="mb-2">✓ Must confirm vote before submission</li>
                            <li class="mb-2">✓ Session expires after 30 minutes</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight selected candidate cards
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove highlight from all cards in this position
                const position = this.name.match(/votes\[(.*?)\]/)[1];
                document.querySelectorAll(`input[name="votes[${position}]"]`).forEach(r => {
                    r.closest('.candidate-card').classList.remove('border-primary');
                });
                // Add highlight to selected card
                this.closest('.candidate-card').classList.add('border-primary');
            });
        });
    </script>
</body>
</html>
