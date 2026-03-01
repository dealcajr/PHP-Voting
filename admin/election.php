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
                        $token = bin2hex(random_bytes(4));
                        $ustmt = $db->prepare("UPDATE commissioners SET token = ? WHERE id = ?");
                        $ustmt->execute([$token, $commId]);
                        logAdminAction('commissioner_token_generated', 'Generated token for commissioner ' . $commId);
                        $message = '<div class="alert alert-success">New token generated for commissioner.</div>';
                    }
                }
            } elseif ($action === 'generate_all_tokens') {
                // Generate new tokens for all active commissioners
                $allComm = $db->query("SELECT id FROM commissioners WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($allComm)) {
                    $message = '<div class="alert alert-warning">No active commissioners found.</div>';
                } else {
                    $ustmt = $db->prepare("UPDATE commissioners SET token = ? WHERE id = ?");
                    foreach ($allComm as $cid) {
                        $token = bin2hex(random_bytes(4));
                        $ustmt->execute([$token, $cid]);
                    }
                    logAdminAction('all_tokens_generated', 'Generated new tokens for all commissioners');
                    $message = '<div class="alert alert-success">New tokens generated for all commissioners.</div>';
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
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="require_open_tokens" name="require_open_tokens" value="1" <?php echo !empty($election['require_open_tokens']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_open_tokens">
                                    Require commissioner tokens to open election
                                </label>
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

                <!-- Generate All Tokens button -->
                <div class="mb-3">
                    <form method="POST" class="d-inline" onsubmit="return confirm('Generate new tokens for ALL commissioners? Their current tokens will be replaced.');">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="generate_all_tokens">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-arrow-repeat me-1"></i>Generate New Tokens for All
                        </button>
                    </form>
                </div>

                <!-- Commissioner token rows -->
                <div id="commissionerTokensSection">
                        <?php if (!empty($commissioners)): ?>
                            <?php foreach ($commissioners as $idx => $commissioner): ?>
                                <div class="mb-3 p-3 border rounded commissioner-row"
                                     data-name="<?php echo htmlspecialchars($commissioner['name']); ?>"
                                     data-type="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $commissioner['commission_type']))); ?>"
                                     data-token="<?php echo htmlspecialchars($commissioner['token'] ?? ''); ?>">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($commissioner['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $commissioner['commission_type']))); ?> Commissioner</small>
                                        </div>
                                        <div class="text-end">
                                            <!-- Token status indicator (no token value shown) -->
                                            <div class="mb-2">
                                                <?php if (!empty($commissioner['token'])): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Token Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-dash-circle me-1"></i>No Token
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Actions -->
                                            <div class="d-flex gap-1 justify-content-end">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="commissioner_id" value="<?php echo (int)$commissioner['id']; ?>">
                                                    <input type="hidden" name="action" value="generate_commissioner_token">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-arrow-repeat me-1"></i>Generate New Token
                                                    </button>
                                                </form>

                                                <?php if (!empty($commissioner['token'])): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Clear this token?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="commissioner_id" value="<?php echo (int)$commissioner['id']; ?>">
                                                        <input type="hidden" name="action" value="clear_commissioner_token">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="bi bi-x-circle me-1"></i>Clear Token
                                                        </button>
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
                </div><!-- /#commissionerTokensSection -->
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

                                <?php if (!empty($election['require_open_tokens'])): ?>
                                    <div class="mb-2 text-start">
                                        <small class="text-muted">Enter commissioner tokens to open:</small>
                                        <input type="password" class="form-control mb-1" name="token_chief" placeholder="Chief Commissioner token" required>
                                        <input type="password" class="form-control mb-1" name="token_screening" placeholder="Screening &amp; Validation token" required>
                                        <input type="password" class="form-control" name="token_electoral" placeholder="Electoral Board token" required>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-success w-100 mb-2">Open Election</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Election Reports moved here -->
                <div class="card mb-4" id="reportsCard">
                    <div class="card-header">
                        <h4>Election Reports</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Generate printable reports for the election.</p>
                        <a href="print_winners_report.php" class="btn btn-primary mb-2">Print Winners Report</a>
                        <a href="print_candidates_report.php" class="btn btn-secondary mb-2">Print Candidate Report</a>
                        <a href="backup_votes.php" class="btn btn-warning mb-2">Backup Votes</a>
                    </div>
                </div>

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

    // ── Print commissioner tokens ───────────────────────────────────────
    function printCommissioners() {
        var rows = document.querySelectorAll('.commissioner-row');
        var html = '<!DOCTYPE html><html><head><title>Commissioner Tokens</title>';
        html += '<style>';
        html += 'body{font-family:Arial,sans-serif;padding:30px;color:#222;}';
        html += 'h2{text-align:center;margin-bottom:8px;}';
        html += '.subtitle{text-align:center;color:#666;margin-bottom:28px;font-size:.9rem;}';
        html += '.token-row{border:1px solid #ccc;border-radius:8px;padding:16px 20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;}';
        html += '.comm-name{font-weight:bold;font-size:1.05rem;}';
        html += '.comm-type{color:#666;font-size:.9rem;margin-top:2px;}';
        html += '.token-val{font-family:monospace;font-size:1.1rem;background:#f4f4f4;padding:8px 16px;border-radius:4px;letter-spacing:3px;border:1px solid #ddd;}';
        html += '.no-token{color:#aaa;font-style:italic;}';
        html += '.footer{text-align:center;margin-top:30px;font-size:.8rem;color:#888;border-top:1px solid #eee;padding-top:12px;}';
        html += '@media print{body{padding:10px;}}';
        html += '</style></head><body>';
        html += '<h2>Commission Tokens</h2>';
        html += '<p class="subtitle">Printed: ' + new Date().toLocaleString() + '</p>';

        rows.forEach(function (row) {
            var name  = row.dataset.name  || '—';
            var type  = row.dataset.type  || '';
            var token = row.dataset.token || '';

            html += '<div class="token-row">';
            html += '<div><div class="comm-name">' + escHtml(name) + '</div><div class="comm-type">' + escHtml(type) + ' Commissioner</div></div>';
            if (token) {
                html += '<div class="token-val">' + escHtml(token) + '</div>';
            } else {
                html += '<div class="no-token">(no token generated)</div>';
            }
            html += '</div>';
        });

        html += '<div class="footer">⚠ Keep this document confidential. Do not share tokens with unauthorized persons.</div>';
        html += '</body></html>';

        var win = window.open('', '_blank', 'width=720,height=520');
        win.document.write(html);
        win.document.close();
        win.focus();
        win.print();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
</script>

<?php include '../includes/admin_footer.php'; ?>
