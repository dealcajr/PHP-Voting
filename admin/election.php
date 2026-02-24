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

            } elseif ($action === 'open_election') {
                $stmt = $db->prepare("UPDATE election_settings SET is_open = 1 WHERE id = ?");
                $stmt->execute([$election['id']]);

                logAdminAction('election_opened', 'Election opened');
                $message = '<div class="alert alert-success">Election opened successfully.</div>';

            } elseif ($action === 'close_election') {
                // Require three distinct, active commissioner tokens to authorize closing
                $token_chief = trim($_POST['token_chief'] ?? '');
                $token_screening = trim($_POST['token_screening'] ?? '');
                $token_electoral = trim($_POST['token_electoral'] ?? '');

                if (empty($token_chief) || empty($token_screening) || empty($token_electoral)) {
                    $message = '<div class="alert alert-danger">All three commissioner tokens are required to close the election.</div>';
                } else {
                    $tokens = [$token_chief, $token_screening, $token_electoral];
                    $foundIds = [];
                    $tokenStmt = $db->prepare("SELECT id FROM commissioners WHERE token = ? AND is_active = 1 LIMIT 1");
                    $valid = true;

                    foreach ($tokens as $t) {
                        $tokenStmt->execute([$t]);
                        $row = $tokenStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$row) {
                            $valid = false;
                            $message = '<div class="alert alert-danger">One or more tokens are invalid or belong to inactive commissioners.</div>';
                            break;
                        }
                        if (in_array($row['id'], $foundIds, true)) {
                            $valid = false;
                            $message = '<div class="alert alert-danger">Duplicate tokens provided. Each token must be from a different commissioner.</div>';
                            break;
                        }
                        $foundIds[] = $row['id'];
                    }

                    if ($valid) {
                        $stmt = $db->prepare("UPDATE election_settings SET is_open = 0, end_date = datetime('now') WHERE id = ?");
                        $stmt->execute([$election['id']]);

                        logAdminAction('election_closed', 'Election closed; authorized by commissioners: ' . implode(',', $foundIds));
                        $message = '<div class="alert alert-success">Election closed successfully.</div>';
                    }
                }

            } elseif ($action === 'reset_election') {
                if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'RESET') {
                    $db->exec("DELETE FROM votes");
                    $db->exec("DELETE FROM audit_log WHERE action NOT LIKE 'login%' AND action NOT LIKE 'election%'");
                    logAdminAction('election_reset', 'Election data reset');
                    $message = '<div class="alert alert-warning">Election data has been reset.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Reset confirmation failed.</div>';
                }
            } elseif ($action === 'generate_commissioner_token') {
                $commId = intval($_POST['commissioner_id'] ?? 0);
                if ($commId <= 0) {
                    $message = '<div class="alert alert-danger">Invalid commissioner.</div>';
                } else {
                    $cstmt = $db->prepare("SELECT id, is_active FROM commissioners WHERE id = ? LIMIT 1");
                    $cstmt->execute([$commId]);
                    $comm = $cstmt->fetch(PDO::FETCH_ASSOC);
                    if (!$comm) {
                        $message = '<div class="alert alert-danger">Commissioner not found.</div>';
                    } elseif ($comm['is_active'] != 1) {
                        $message = '<div class="alert alert-danger">Commissioner is not active.</div>';
                    } else {
                        $token = bin2hex(random_bytes(8));
                        $ustmt = $db->prepare("UPDATE commissioners SET token = ? WHERE id = ?");
                        $ustmt->execute([$token, $commId]);
                        logAdminAction('commissioner_token_generated', 'Generated token for commissioner ' . $commId);
                        $message = '<div class="alert alert-success">New token generated for commissioner.</div>';
                    }
                }
            } elseif ($action === 'clear_commissioner_token') {
                $commId = intval($_POST['commissioner_id'] ?? 0);
                if ($commId <= 0) {
                    $message = '<div class="alert alert-danger">Invalid commissioner.</div>';
                } else {
                    $ustmt = $db->prepare("UPDATE commissioners SET token = '' WHERE id = ?");
                    $ustmt->execute([$commId]);
                    logAdminAction('commissioner_token_cleared', 'Cleared token for commissioner ' . $commId);
                    $message = '<div class="alert alert-success">Commissioner token cleared.</div>';
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
<style>
:root { <?php echo getThemeCSSDeclaration(); ?> }
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
/* ... (styles unchanged, kept concise) ... */
</style>

<div class="admin-content">
       <div class="container-fluid">
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
                    <!-- Commissioners -->
                    <div class="card mt-4" id="commissionersCard">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Commission Tokens</h4>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="printCommissioners()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">These tokens are required for election control operations.</p>
                            <?php
                            $commissioners = $db->query("SELECT id, commission_type, name, token, is_active FROM commissioners ORDER BY commission_type")->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php if (!empty($commissioners)): ?>
                                <?php foreach ($commissioners as $commissioner): ?>
                                    <div class="mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($commissioner['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo ucfirst($commissioner['commission_type']); ?> Commissioner</small>
                                            </div>
                                            <div class="text-end">
                                                <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($commissioner['token']); ?></code>
                                                <div class="mt-2">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="commissioner_id" value="<?php echo (int)$commissioner['id']; ?>">
                                                        <input type="hidden" name="action" value="generate_commissioner_token">
                                                        <button type="submit" class="btn btn-sm btn-primary">Generate</button>
                                                    </form>

                                                    <?php if (!empty($commissioner['token'])): ?>
                                                        <form method="POST" class="d-inline ms-2" onsubmit="return confirm('Clear this token?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="commissioner_id" value="<?php echo (int)$commissioner['id']; ?>">
                                                            <input type="hidden" name="action" value="clear_commissioner_token">
                                                            <button type="submit" class="btn btn-sm btn-warning">Clear</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    No commissioners found in the database.
                                </div>
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

                        <?php if ($election['is_open']): ?>
                            <button type="button" class="btn btn-danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#closeModal">
                                Close Election
                            </button>
                        <?php else: ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="open_election">
                                <button type="submit" class="btn btn-success w-100 mb-2">Open Election</button>
                            </form>
                        <?php endif; ?>

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

<!-- Close Election Modal (requires 3 commissioner tokens) -->
<div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Close Election - Commissioner Tokens Required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="closeForm" method="POST">
                <div class="modal-body">
                    <p class="text-muted">Enter the three commissioner tokens to authorize closing the election.</p>

                    <div class="mb-3">
                        <label class="form-label">Chief Commissioner Token</label>
                        <input type="text" class="form-control" name="token_chief" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Commission on Screening & Validation Token</label>
                        <input type="text" class="form-control" name="token_screening" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Commission of Electoral Board Token</label>
                        <input type="text" class="form-control" name="token_electoral" required>
                    </div>

                    <div class="alert alert-info small">All tokens must belong to active commissioners and must be unique.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="close_election">
                    <button type="submit" class="btn btn-danger" id="closeSubmit">Close Election</button>
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
