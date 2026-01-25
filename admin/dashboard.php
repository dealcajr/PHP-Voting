<?php
require_once '../includes/config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get election settings
$db = getDBConnection();
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

// Get statistics
$total_voters = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_active = 1")->fetchColumn();
$total_votes_cast = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
$total_candidates = $db->query("SELECT COUNT(*) FROM candidates WHERE is_active = 1")->fetchColumn();
$voting_progress = $total_voters > 0 ? round(($total_votes_cast / $total_voters) * 100, 1) : 0;

// Get recent audit log
$audit_log = $db->query("
    SELECT al.action, al.details, al.created_at, u.student_id
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

// Handle POST requests
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'open_election') {
                $db->prepare("UPDATE election_settings SET is_open = 1, start_date = NOW() WHERE id = ?")->execute([$election['id']]);
                logAdminAction('election_opened', 'Election opened');
                $message = '<div class="alert alert-success">Election opened successfully.</div>';
            } elseif ($action === 'close_election') {
                $db->prepare("UPDATE election_settings SET is_open = 0, end_date = NOW() WHERE id = ?")->execute([$election['id']]);
                logAdminAction('election_closed', 'Election closed');
                $message = '<div class="alert alert-success">Election closed successfully.</div>';
            } elseif ($action === 'reset_election') {
                if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'RESET') {
                    $db->exec("DELETE FROM votes");
                    $db->exec("DELETE FROM audit_log WHERE action NOT LIKE 'login%'");
                    logAdminAction('election_reset', 'Election data reset');
                    $message = '<div class="alert alert-warning">Election data has been reset.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Reset confirmation failed.</div>';
                }
            }
            // Refresh election data
            $election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo APP_NAME; ?> - Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../results.php">View Results</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Admin Dashboard</h1>
        <?php echo $message; ?>

        <!-- Election Status -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card admin-card">
                    <div class="card-body text-center">
                        <h4>Election Status</h4>
                        <span class="badge <?php echo $election['is_open'] ? 'bg-success' : 'bg-danger'; ?> fs-4">
                            <?php echo $election['is_open'] ? 'OPEN' : 'CLOSED'; ?>
                        </span>
                        <p class="mt-2"><?php echo htmlspecialchars($election['election_name']); ?></p>
                        <?php if ($election['start_date']): ?>
                            <p>Started: <?php echo date('M j, Y g:i A', strtotime($election['start_date'])); ?></p>
                        <?php endif; ?>
                        <?php if ($election['end_date']): ?>
                            <p>Ended: <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-number"><?php echo $total_voters; ?></div>
                        <div class="stat-label">Registered Voters</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-number"><?php echo $total_votes_cast; ?></div>
                        <div class="stat-label">Votes Cast</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-number"><?php echo $total_candidates; ?></div>
                        <div class="stat-label">Candidates</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-number"><?php echo $voting_progress; ?>%</div>
                        <div class="stat-label">Voting Progress</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Election Controls -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Election Controls</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $election['is_open'] ? 'close_election' : 'open_election'; ?>">
                            <button type="submit" class="btn btn-<?php echo $election['is_open'] ? 'danger' : 'success'; ?> me-2">
                                <?php echo $election['is_open'] ? 'Close Election' : 'Open Election'; ?>
                            </button>
                        </form>

                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resetModal">
                            Reset Election
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Log -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Recent Activity</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Admin</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_log as $log): ?>
                                        <tr>
                                            <td><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['student_id'] ?? 'System'); ?></td>
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Election</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger"><strong>Warning:</strong> This will permanently delete all votes and most audit logs. This action cannot be undone.</p>
                    <p>Type "RESET" in the box below to confirm:</p>
                    <input type="text" class="form-control" id="resetConfirm" placeholder="Type RESET">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="reset_election">
                        <input type="hidden" name="confirm_reset" id="confirmResetInput" value="">
                        <button type="submit" class="btn btn-danger" id="resetBtn" disabled>Reset Election</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reset confirmation
        document.getElementById('resetConfirm').addEventListener('input', function() {
            const btn = document.getElementById('resetBtn');
            const input = document.getElementById('confirmResetInput');
            if (this.value === 'RESET') {
                btn.disabled = false;
                input.value = 'RESET';
            } else {
                btn.disabled = true;
                input.value = '';
            }
        });
    </script>
</body>
</html>
