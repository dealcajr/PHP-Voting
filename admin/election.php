<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

$db = getDBConnection();
$message = '';

// Get current election settings
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'update_settings') {
                $election_name = sanitizeInput($_POST['election_name'] ?? '');
                $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

                $stmt = $db->prepare("UPDATE election_settings SET election_name = ?, start_date = ?, end_date = ?, updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$election_name, $start_date, $end_date, $election['id']]);

                logAdminAction('election_settings_updated', 'Updated election settings');
                $message = '<div class="alert alert-success">Election settings updated successfully.</div>';

            } elseif ($action === 'generate_token') {
                $token = bin2hex(random_bytes(16)); // 32 character token
                $stmt = $db->prepare("UPDATE election_settings SET election_token = ? WHERE id = ?");
                $stmt->execute([$token, $election['id']]);

                logAdminAction('election_token_generated', 'Generated new election token');
                $message = '<div class="alert alert-success">Election token generated successfully.</div>';

            } elseif ($action === 'clear_token') {
                $stmt = $db->prepare("UPDATE election_settings SET election_token = NULL WHERE id = ?");
                $stmt->execute([$election['id']]);

                logAdminAction('election_token_cleared', 'Cleared election token');
                $message = '<div class="alert alert-success">Election token cleared successfully.</div>';

            } elseif ($action === 'open_election') {
                $stmt = $db->prepare("UPDATE election_settings SET is_open = 1 WHERE id = ?");
                $stmt->execute([$election['id']]);

                logAdminAction('election_opened', 'Election opened');
                $message = '<div class="alert alert-success">Election opened successfully.</div>';

            } elseif ($action === 'close_election') {
                $stmt = $db->prepare("UPDATE election_settings SET is_open = 0, end_date = datetime('now') WHERE id = ?");
                $stmt->execute([$election['id']]);

                logAdminAction('election_closed', 'Election closed');
                $message = '<div class="alert alert-success">Election closed successfully.</div>';

            } elseif ($action === 'reset_election') {
                if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'RESET') {
                    $db->exec("DELETE FROM votes");
                    $db->exec("DELETE FROM audit_log WHERE action NOT LIKE 'login%' AND action NOT LIKE 'election%'");
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

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <h1>Election Settings</h1>
    <?php echo $message; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Election Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Election Configuration</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_settings">

                        <div class="mb-3">
                            <label for="election_name" class="form-label">Election Name</label>
                            <input type="text" class="form-control" id="election_name" name="election_name" value="<?php echo htmlspecialchars($election['election_name'] ?? ''); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?php echo $election['start_date'] ? date('Y-m-d\TH:i', strtotime($election['start_date'])) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?php echo $election['end_date'] ? date('Y-m-d\TH:i', strtotime($election['end_date'])) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Settings</button>
                    </form>
                </div>
            </div>



            <!-- Election Token -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Election Access Token</h4>
                </div>
                <div class="card-body">
                    <p>The election token is required for voters to access the voting system. Generate a new token or clear the existing one.</p>

                    <?php if (!empty($election['election_token'])): ?>
                        <div class="alert alert-info">
                            <strong>Current Token:</strong> code><?php echo htmlspecialchars($election['election_token']); ?></code>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            No election token is currently set. Voters will not be able to access the voting system.
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="generate_token">
                        <button type="submit" class="btn btn-success me-2">Generate New Token</button>
                    </form>

                    <?php if (!empty($election['election_token'])): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear the election token? This will prevent voters from accessing the system.')">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="clear_token">
                            <button type="submit" class="btn btn-warning">Clear Token</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Election Status & Controls -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Election Status</h4>
                </div>
                <div class="card-body text-center">
                    <span class="badge <?php echo $election['is_open'] ? 'bg-success fs-5' : 'bg-danger fs-5'; ?> mb-3">
                        <?php echo $election['is_open'] ? 'OPEN' : 'CLOSED'; ?>
                    </span>

                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $election['is_open'] ? 'close_election' : 'open_election'; ?>">
                        <button type="submit" class="btn btn-<?php echo $election['is_open'] ? 'danger' : 'success'; ?> w-100 mb-2">
                            <?php echo $election['is_open'] ? 'Close Election' : 'Open Election'; ?>
                        </button>
                    </form>

                    <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#resetModal">
                        Reset Election Data
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h4>Quick Stats</h4>
                </div>
                <div class="card-body">
                    <?php
                    $total_voters = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_active = 1")->fetchColumn();
                    $total_votes = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
                    $total_candidates = $db->query("SELECT COUNT(*) FROM candidates WHERE is_active = 1")->fetchColumn();
                    ?>
                    <div class="row text-center">
                        <div class="col-12 mb-2">
                            <div class="border rounded p-2">
                                <div class="h5 mb-0"><?php echo $total_voters; ?></div>
                                <small class="text-muted">Active Voters</small>
                            </div>
                        </div>
                        <div class="col-12 mb-2">
                            <div class="border rounded p-2">
                                <div class="h5 mb-0"><?php echo $total_votes; ?></div>
                                <small class="text-muted">Votes Cast</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-2">
                                <div class="h5 mb-0"><?php echo $total_candidates; ?></div>
                                <small class="text-muted">Active Candidates</small>
                            </div>
                        </div>
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
                <h5 class="modal-title">Reset Election Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="resetForm" method="POST">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This will permanently delete all votes and most audit logs. This action cannot be undone.
                    </div>
                    <p>Type "RESET" in the box below to confirm:</p>
                    <input type="text" class="form-control" id="resetConfirm" placeholder="Type RESET">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reset_election">
                    <input type="hidden" name="confirm_reset" id="confirmResetInput" value="">
                    <button type="submit" class="btn btn-danger" id="resetBtn" disabled>Reset Election Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Reset confirmation
    const resetConfirmInput = document.getElementById('resetConfirm');
    const resetBtn = document.getElementById('resetBtn');
    const confirmResetInput = document.getElementById('confirmResetInput');

    if(resetConfirmInput) {
        resetConfirmInput.addEventListener('input', function() {
            if (this.value === 'RESET') {
                resetBtn.disabled = false;
                confirmResetInput.value = 'RESET';
            } else {
                resetBtn.disabled = true;
                confirmResetInput.value = '';
            }
        });
    }
</script>

<?php include '../includes/admin_footer.php'; ?>
