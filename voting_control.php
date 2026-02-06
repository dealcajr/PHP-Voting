<?php
require_once 'includes/config.php';

// No login required - commission tokens only

// Get database connection
$db = getDBConnection();

// Get current election settings
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

// Handle form submission
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_control']))
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request. Please try again.</div>';
    } else {
        $chief_token = trim($_POST['chief_token'] ?? '');
        $screening_token = trim($_POST['screening_token'] ?? '');
        $electoral_token = trim($_POST['electoral_token'] ?? '');
        $action = $_POST['action'] ?? '';

        if (empty($chief_token) || empty($screening_token) || empty($electoral_token)) {
            $message = '<div class="alert alert-danger">All three commission tokens are required.</div>';
        } elseif (!in_array($action, ['open', 'close'])) {
            $message = '<div class="alert alert-danger">Invalid action selected.</div>';
        } else {
            // Verify tokens from database
            $stmt = $db->prepare("SELECT commission_type, token FROM commissioners WHERE is_active = 1");
            $stmt->execute();
            $commissioners = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // commission_type => token

            if ($chief_token !== ($commissioners['chief'] ?? '') ||
                $screening_token !== ($commissioners['screening'] ?? '') ||
                $electoral_token !== ($commissioners['electoral'] ?? '')) {
                $message = '<div class="alert alert-danger">Invalid commission tokens. Please check with the commission chairs.</div>';
            } else {
                try {
                    $db->beginTransaction();

                    // Update election status
                    $is_open = ($action === 'open') ? 1 : 0;
                    $stmt = $db->prepare("UPDATE election_settings SET is_open = ?, updated_at = datetime('now') WHERE id = ?");
                    $stmt->execute([$is_open, $election['id']]);

                    // Log the action
                    $action_text = $action === 'open' ? 'Election opened' : 'Election closed';
                    logAdminAction('election_control', $action_text . ' using commission tokens');

                    $db->commit();

                    $success = true;
                    $message = '<div class="alert alert-success">Election has been ' . ($action === 'open' ? 'opened' : 'closed') . ' successfully!</div>';

                    // Refresh election data
                    $election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

                } catch (PDOException $e) {
                    $db->rollBack();
                    $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
                    error_log("Election control error: " . $e->getMessage());
                }
            }
        }
    }
    

// Get current voting statistics
$total_voters = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_active = 1")->fetchColumn();
$votes_cast = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
$completion_rate = $total_voters > 0 ? round(($votes_cast / $total_voters) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Voting Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><?php echo APP_NAME; ?> - Voting Control</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <h1>Voting Control</h1>
                <p class="lead">Manage election status using commission authorization</p>

                <?php echo $message; ?>

                <!-- Current Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Current Election Status</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5>Election Status</h5>
                                    <span class="badge fs-6 <?php echo $election['is_open'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $election['is_open'] ? 'OPEN' : 'CLOSED'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5>Total Voters</h5>
                                    <span class="h4 text-primary"><?php echo $total_voters; ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5>Votes Cast</h5>
                                    <span class="h4 text-success"><?php echo $votes_cast; ?> (<?php echo $completion_rate; ?>%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Control Form -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4>Election Control Authorization</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Authorization Required:</strong> All three commission tokens must be provided to open or close voting.
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="submit_control" value="1">

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="chief_token" class="form-label">
                                        <strong>Chief Commissioner Token</strong>
                                    </label>
                                    <input type="password" class="form-control" id="chief_token" name="chief_token" required placeholder="Enter token...">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="screening_token" class="form-label">
                                        <strong>Commission on Screening & Validation Token</strong>
                                    </label>
                                    <input type="password" class="form-control" id="screening_token" name="screening_token" required placeholder="Enter token...">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="electoral_token" class="form-label">
                                        <strong>Commission of Electoral Board Token</strong>
                                    </label>
                                    <input type="password" class="form-control" id="electoral_token" name="electoral_token" required placeholder="Enter token...">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label"><strong>Action</strong></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="action" id="action_open" value="open" <?php echo (!$election['is_open']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="action_open">
                                        <span class="badge bg-success">Open Voting</span> - Allow students to cast their votes
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="action" id="action_close" value="close" <?php echo $election['is_open'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="action_close">
                                        <span class="badge bg-danger">Close Voting</span> - Stop accepting new votes
                                    </label>
                                </div>
                            </div>

                            <div class="alert alert-warning">
                                <strong>Important:</strong> This action requires authorization from all three commissions. Ensure you have verified the tokens with the respective commission chairs.
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="bi bi-shield-check"></i> Execute Election Control
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Commission Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><i class="bi bi-person-badge text-primary"></i> Chief Commissioner</h6>
                            <small class="text-muted">Overall election authority and final decision maker.</small>
                        </div>
                        <div class="mb-3">
                            <h6><i class="bi bi-search text-success"></i> Commission on Screening & Validation</h6>
                            <small class="text-muted">Validates voter eligibility and candidate qualifications.</small>
                        </div>
                        <div class="mb-3">
                            <h6><i class="bi bi-bar-chart text-info"></i> Commission of Electoral Board</h6>
                            <small class="text-muted">Manages voting process and result tabulation.</small>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Quick Actions</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-outline-primary">Home</a>
                            <a href="results.php" class="btn btn-outline-success">View Results</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>