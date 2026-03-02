<?php
require_once 'includes/config.php';

// Check if user is logged in and is a voter
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'voter') {
    header('Location: vote_login.php');
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
    header('Location: election_closed.php');
    exit();
}

// Check if user has already voted for all positions
$user_id = $_SESSION['user_id'];
$voter_grade = $_SESSION['grade'] ?? null;

// Define which positions each grade can vote for
$allowed_positions = [];

if ($voter_grade === '7') {
    // Grade 7 voters can only vote for Grade 8 Representative and JHS Vice President
    $allowed_positions = ['Grade 8 Representative', 'Junior High School Vice President'];
} elseif ($voter_grade === '8') {
    // Grade 8 voters can only vote for Grade 8 Representative and JHS Vice President
    $allowed_positions = ['Grade 9 Representative', 'Junior High School Vice President'];
} elseif ($voter_grade === '9') {
    // Grade 9 voters can only vote for Grade 10 Representative and JHS Vice President
    $allowed_positions = ['Grade 10 Representative', 'Junior High School Vice President'];
} elseif ($voter_grade === '10') {
    // Grade 10 voters can only vote for Grade 11 Representative and SHS Vice President
    $allowed_positions = ['Grade 11 Representative', 'Senior High School Vice President'];
} elseif ($voter_grade === '11') {
    // Grade 11 voters can only vote for Grade 12 Representative and SHS Vice President
    $allowed_positions = ['Grade 12 Representative', 'Senior High School Vice President'];
} elseif ($voter_grade === '12') {
} else {
    // Unknown grade - allow all positions
}

// Get positions from database (filtered by grade if applicable)
if (!empty($allowed_positions)) {
    $positions_stmt = $db->prepare("SELECT DISTINCT position FROM candidates WHERE is_active = 1 AND position IN ('" . implode("','", $allowed_positions) . "') ORDER BY position");
    $positions_stmt->execute();
} else {
    $positions_stmt = $db->query("SELECT DISTINCT position FROM candidates WHERE is_active = 1 ORDER BY position");
}
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
    header('Location: results.php');
    exit();
}

$multi_vote_positions = ['Grade 8 Representative', 'Grade 9 Representative', 'Grade 10 Representative', 'Grade 11 Representative', 'Grade 12 Representative'];

// Handle vote submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_vote'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request. Please try again.</div>';
    } else {
        $votes = $_POST['votes'] ?? [];

        if (empty($votes)) {
            $message = '<div class="alert alert-danger">Please select at least one candidate.</div>';
        } else {
            // Validate that voter is only voting for allowed positions based on their grade
            if (!empty($allowed_positions)) {
                foreach ($votes as $position => $candidate_data) {
                    if (!in_array($position, $allowed_positions)) {
                        $message = '<div class="alert alert-danger">You are not allowed to vote for this position.</div>';
                        $has_error = true;
                        break;
                    }
                }
            }
            
            if (!$has_error) {
                try {
                    $db->beginTransaction();
                    $votes_cast = 0;
                    $has_error  = false;

                    foreach ($votes as $position => $candidate_data) {
                        if (is_array($candidate_data)) {
                            if (in_array($position, $multi_vote_positions) && count($candidate_data) > 2) {
                                $message = "<div class='alert alert-danger'>You can vote for a maximum of 2 candidates for $position.</div>";
                                $has_error = true;
                                break;
                            }
                            foreach ($candidate_data as $candidate_id) {
                                $stmt = $db->prepare("SELECT id FROM votes WHERE voter_id = ? AND position = ? AND candidate_id = ?");
                                $stmt->execute([$user_id, $position, $candidate_id]);
                                if (!$stmt->fetch()) {
                                    $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ? AND is_active = 1");
                                    $stmt->execute([$candidate_id]);
                                    $candidate = $stmt->fetch();
                                    if ($candidate) {
                                        $vote_hash = hash('sha256', $user_id . $candidate_id . $position . time());
                                        $db->prepare("INSERT INTO votes (voter_id, candidate_id, position, vote_hash) VALUES (?, ?, ?, ?)")
                                           ->execute([$user_id, $candidate_id, $position, $vote_hash]);
                                        $votes_cast++;
                                    }
                                }
                            }
                        } else {
                            $candidate_id = $candidate_data;
                            $stmt = $db->prepare("SELECT id FROM votes WHERE voter_id = ? AND position = ?");
                            $stmt->execute([$user_id, $position]);
                            if (!$stmt->fetch()) {
                                $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ? AND is_active = 1");
                                $stmt->execute([$candidate_id]);
                                $candidate = $stmt->fetch();
                                if ($candidate) {
                                    $vote_hash = hash('sha256', $user_id . $candidate_id . $position . time());
                                    $db->prepare("INSERT INTO votes (voter_id, candidate_id, position, vote_hash) VALUES (?, ?, ?, ?)")
                                       ->execute([$user_id, $candidate_id, $position, $vote_hash]);
                                    $votes_cast++;
                                }
                            }
                        }
                    }

                    if (!$has_error) {
                        $db->commit();
                        if ($votes_cast > 0) {
                            // Mark voter as having voted (one-time vote only)
                            $db->prepare("UPDATE users SET has_voted = 1 WHERE id = ?")->execute([$user_id]);

                            // Set one-time success flag and voter name for the success page
                            $_SESSION['vote_success'] = true;
                            $_SESSION['voter_display_name'] = $_SESSION['first_name'] ?? '';
                            header('Location: vote_success.php');
                            exit();
                        } else {
                            $message = '<div class="alert alert-info">You have already voted for all selected positions.</div>';
                        }
                    } else {
                        $db->rollBack();
                    }

                } catch (PDOException $e) {
                    $db->rollBack();
                    $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
                    error_log("Vote submission error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get candidates grouped by position (filtered by voter grade)
$candidates_by_position = [];
if ($positions) {
    foreach ($positions as $position) {
        // For Grade 7, only show candidates that match allowed positions
        if ($voter_grade === '7') {
            $stmt = $db->prepare("SELECT * FROM candidates WHERE position = ? AND is_active = 1 ORDER BY name");
        } else {
            $stmt = $db->prepare("SELECT * FROM candidates WHERE position = ? AND is_active = 1 ORDER BY name");
        }
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

// Get school info and logo
$stmt_school  = $db->query("SELECT school_name, logo_path FROM school_info LIMIT 1");
$school_info  = $stmt_school ? $stmt_school->fetch() : null;
$school_name  = $school_info['school_name'] ?? APP_NAME;
$school_logo  = $school_info['logo_path'] ?? 'admin/assets/images/logo_1770187955.png';

// Get theme color
$theme_row   = $db->query("SELECT theme_color FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
$theme_color = $theme_row['theme_color'] ?? '#007a1b';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Vote</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root { --theme: <?php echo htmlspecialchars($theme_color); ?>; }

        body { background: #f4f6f9; }

        /* ── Navbar ── */
        .vote-navbar {
            background: var(--theme);
            padding: 0.75rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .vote-navbar .brand { display: flex; align-items: center; gap: 12px; }
        .vote-navbar .brand img { height: 44px; object-fit: contain; border-radius: 4px; }
        .vote-navbar .brand span { color: #fff; font-weight: 700; font-size: 1.1rem; }
        .vote-navbar .nav-links a {
            color: rgba(255,255,255,0.85); text-decoration: none;
            margin-left: 1.25rem; font-size: 0.9rem;
            transition: color 0.2s;
        }
        .vote-navbar .nav-links a:hover { color: #fff; }

        /* ── Layout ── */
        .vote-wrapper { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        .vote-main { display: flex; gap: 1.5rem; align-items: flex-start; }
        .vote-form-col { flex: 1 1 0; min-width: 0; }
        .vote-sidebar { width: 260px; flex-shrink: 0; position: sticky; top: 1.5rem; }

        /* ── Position Card ── */
        .position-card {
            background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 1.5rem; overflow: hidden;
        }
        .position-card .pos-header {
            background: var(--theme); color: #fff;
            padding: 0.9rem 1.25rem;
        }
        .position-card .pos-header h5 { margin: 0; font-weight: 700; font-size: 1rem; }
        .position-card .pos-header small { opacity: 0.85; font-size: 0.8rem; }
        .position-card .pos-body { padding: 1.25rem; }

        /* ── Candidate Card ── */
        .candidate-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
        .candidate-item {
            background: #f8f9fa; border: 2px solid #e9ecef;
            border-radius: 12px; padding: 1rem 0.75rem;
            text-align: center; cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            position: relative;
        }
        .candidate-item:hover { border-color: var(--theme); background: #fff; }
        .candidate-item.selected {
            border-color: var(--theme);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,122,27,0.15);
        }
        .candidate-item .check-badge {
            position: absolute; top: 8px; right: 8px;
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--theme); color: #fff;
            display: none; align-items: center; justify-content: center;
            font-size: 0.75rem;
        }
        .candidate-item.selected .check-badge { display: flex; }

        /* Circle photo */
        .candidate-photo {
            width: 80px; height: 80px; border-radius: 50%;
            object-fit: cover; border: 3px solid #dee2e6;
            margin: 0 auto 0.6rem; display: block;
            transition: border-color 0.2s;
        }
        .candidate-item.selected .candidate-photo { border-color: var(--theme); }
        .candidate-photo-placeholder {
            width: 80px; height: 80px; border-radius: 50%;
            background: #dee2e6; margin: 0 auto 0.6rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #adb5bd;
            border: 3px solid #dee2e6;
        }
        .candidate-item.selected .candidate-photo-placeholder { border-color: var(--theme); }

        .candidate-name { font-weight: 600; font-size: 0.88rem; color: #2d3748; margin-bottom: 0.2rem; }
        .candidate-party { font-size: 0.78rem; color: #718096; }

        /* Hidden radio/checkbox */
        .candidate-item input[type="radio"],
        .candidate-item input[type="checkbox"] { display: none; }

        /* Already voted */
        .voted-card {
            background: #d4edda; border: 1px solid #c3e6cb;
            border-radius: 14px; padding: 1rem 1.25rem;
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;
        }
        .voted-card i { font-size: 1.5rem; color: #28a745; }
        .voted-card .pos-name { font-weight: 600; color: #155724; }
        .voted-card .pos-sub  { font-size: 0.82rem; color: #1e7e34; }

        /* Submit button */
        .submit-bar {
            background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 1.25rem; margin-bottom: 1.5rem;
            text-align: center;
        }
        .btn-submit-vote {
            background: var(--theme); color: #fff; border: none;
            padding: 0.75rem 2.5rem; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn-submit-vote:hover { opacity: 0.9; transform: translateY(-1px); }

        /* ── Sidebar ── */
        .sidebar-card {
            background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 1.25rem; margin-bottom: 1rem;
        }
        .sidebar-card h6 { font-weight: 700; color: #2d3748; margin-bottom: 0.75rem; font-size: 0.9rem; text-transform: uppercase; letter-spacing: .5px; }
        .progress-list { list-style: none; padding: 0; margin: 0; }
        .progress-list li {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.85rem; padding: 0.3rem 0; color: #495057;
        }
        .progress-list li.done { color: #28a745; }
        .rules-list { list-style: none; padding: 0; margin: 0; }
        .rules-list li { font-size: 0.83rem; color: #495057; padding: 0.25rem 0; display: flex; gap: 0.5rem; }

        /* ── Review Modal ── */
        .review-candidate-row {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.6rem 0; border-bottom: 1px solid #f0f0f0;
        }
        .review-candidate-row:last-child { border-bottom: none; }
        .review-photo {
            width: 44px; height: 44px; border-radius: 50%;
            object-fit: cover; border: 2px solid #dee2e6; flex-shrink: 0;
        }
        .review-photo-placeholder {
            width: 44px; height: 44px; border-radius: 50%;
            background: #dee2e6; display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: #adb5bd; flex-shrink: 0;
        }
        .review-position-block { margin-bottom: 1rem; }
        .review-position-label {
            font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--theme); margin-bottom: 0.4rem;
        }

        @media (max-width: 768px) {
            .vote-main { flex-direction: column; }
            .vote-sidebar { width: 100%; position: static; }
            .candidate-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="vote-navbar">
    <div class="brand">
        <?php if ($school_logo): ?>
            <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="Logo">
        <?php endif; ?>
        <span><?php echo htmlspecialchars($school_name); ?></span>
    </div>
</nav>

<div class="vote-wrapper">

    <?php if ($message): ?>
        <div class="mb-3"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="vote-main">

        <!-- ── Voting Form ─────────────────────────────────────────────── -->
        <div class="vote-form-col">
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($election['election_name'] ?? 'Cast Your Vote'); ?></h4>
            <p class="text-muted mb-3" style="font-size:.9rem;">Select your candidates for each position, then click <strong>Review &amp; Submit</strong>.</p>

            <form method="POST" action="" id="voteForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="submit_vote" value="1">

                <?php foreach ($candidates_by_position as $position => $candidates): ?>

                    <?php if (in_array($position, $voted_positions)): ?>
                        <!-- Already voted -->
                        <div class="voted-card">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <div class="pos-name"><?php echo htmlspecialchars($position); ?></div>
                                <div class="pos-sub">You have already voted for this position.</div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Voting card -->
                        <div class="position-card" data-position="<?php echo htmlspecialchars($position); ?>">
                            <div class="pos-header">
                                <h5><?php echo htmlspecialchars($position); ?></h5>
                                <?php if (in_array($position, $multi_vote_positions)): ?>
                                    <small><i class="bi bi-info-circle me-1"></i>You may select up to 2 candidates.</small>
                                <?php endif; ?>
                            </div>
                            <div class="pos-body">
                                <div class="candidate-grid">
                                    <?php foreach ($candidates as $candidate): ?>
                                        <?php
                                        $inputType = in_array($position, $multi_vote_positions) ? 'checkbox' : 'radio';
                                        $inputName = in_array($position, $multi_vote_positions)
                                            ? 'votes[' . htmlspecialchars($position) . '][]'
                                            : 'votes[' . htmlspecialchars($position) . ']';
                                        $inputId = 'cand_' . $candidate['id'];
                                        ?>
                                        <label class="candidate-item" for="<?php echo $inputId; ?>"
                                               data-name="<?php echo htmlspecialchars($candidate['name']); ?>"
                                               data-party="<?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?>"
                                               data-photo="<?php echo htmlspecialchars($candidate['photo'] ?? ''); ?>"
                                               data-position="<?php echo htmlspecialchars($position); ?>">
                                            <input type="<?php echo $inputType; ?>"
                                                   name="<?php echo $inputName; ?>"
                                                   id="<?php echo $inputId; ?>"
                                                   value="<?php echo $candidate['id']; ?>">
                                            <div class="check-badge"><i class="bi bi-check"></i></div>
                                            <?php if (!empty($candidate['photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($candidate['photo']); ?>"
                                                     alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                                                     class="candidate-photo">
                                            <?php else: ?>
                                                <div class="candidate-photo-placeholder"><i class="bi bi-person-fill"></i></div>
                                            <?php endif; ?>
                                            <div class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></div>
                                            <div class="candidate-party"><?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?></div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>

                <!-- Submit bar -->
                <?php if (count($candidates_by_position) > count($voted_positions)): ?>
                    <div class="submit-bar">
                        <button type="button" class="btn-submit-vote" id="reviewBtn">
                            <i class="bi bi-eye me-2"></i>Review &amp; Submit
                        </button>
                        <p class="text-muted small mt-2 mb-0">You will be able to review your selections before casting your vote.</p>
                    </div>
                <?php endif; ?>

            </form>
        </div><!-- /form col -->

        <!-- ── Sidebar ─────────────────────────────────────────────────── -->
        <div class="vote-sidebar">
            <!-- Progress -->
            <div class="sidebar-card">
                <h6><i class="bi bi-list-check me-1"></i>Voting Progress</h6>
                <?php $progress = count($positions) > 0 ? (count($voted_positions) / count($positions)) * 100 : 0; ?>
                <div class="progress mb-2" style="height:8px; border-radius:4px;">
                    <div class="progress-bar" role="progressbar"
                         style="width:<?php echo $progress; ?>%; background:var(--theme);"
                         aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="text-muted small mb-2"><?php echo count($voted_positions); ?>/<?php echo count($positions); ?> positions voted</p>
                <ul class="progress-list">
                    <?php foreach ($positions as $pos): ?>
                        <li class="<?php echo in_array($pos, $voted_positions) ? 'done' : ''; ?>">
                            <i class="bi <?php echo in_array($pos, $voted_positions) ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                            <?php echo htmlspecialchars($pos); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Rules -->
            <div class="sidebar-card">
                <h6><i class="bi bi-shield-check me-1"></i>Voting Rules</h6>
                <ul class="rules-list">
                    <li><i class="bi bi-check2 text-success"></i>One vote per position</li>
                    <li><i class="bi bi-check2 text-success"></i>Up to 2 votes for Representatives</li>
                    <li><i class="bi bi-check2 text-success"></i>Votes are anonymous</li>
                    <li><i class="bi bi-check2 text-success"></i>Cannot change vote once submitted</li>
                    <li><i class="bi bi-check2 text-success"></i>Review before final submission</li>
                    <li><i class="bi bi-check2 text-success"></i>Session expires after 30 minutes</li>
                </ul>
            </div>
        </div><!-- /sidebar -->

    </div><!-- /vote-main -->
</div><!-- /vote-wrapper -->

<!-- ── Review & Confirm Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--theme); color:#fff;">
                <h5 class="modal-title" id="reviewModalLabel">
                    <i class="bi bi-eye me-2"></i>Review Your Selections
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reviewBody">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer flex-column align-items-stretch">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmCheck">
                    <label class="form-check-label fw-semibold" for="confirmCheck">
                        I have reviewed my selections and confirm that they are correct. I understand that my vote cannot be changed after submission.
                    </label>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-pencil me-1"></i>Go Back &amp; Edit
                    </button>
                    <button type="button" class="btn btn-success" id="castVoteBtn" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Cast My Vote
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // ── Candidate selection highlight ──────────────────────────────────
    document.querySelectorAll('.candidate-item').forEach(function (item) {
        var input = item.querySelector('input');
        if (!input) return;

        item.addEventListener('click', function () {
            var position = item.dataset.position;

            if (input.type === 'radio') {
                // Deselect siblings
                document.querySelectorAll('.candidate-item[data-position="' + CSS.escape(position) + '"]')
                    .forEach(function (s) { s.classList.remove('selected'); });
                input.checked = true;
                item.classList.add('selected');

            } else {
                // Checkbox: toggle, max 2
                if (!input.checked) {
                    var checked = document.querySelectorAll(
                        '.candidate-item[data-position="' + CSS.escape(position) + '"] input:checked'
                    );
                    if (checked.length >= 2) {
                        alert('You can only select up to 2 candidates for this position.');
                        return;
                    }
                    input.checked = true;
                    item.classList.add('selected');
                } else {
                    input.checked = false;
                    item.classList.remove('selected');
                }
            }
        });
    });

    // ── Review button ──────────────────────────────────────────────────
    var reviewBtn  = document.getElementById('reviewBtn');
    var reviewBody = document.getElementById('reviewBody');
    var confirmChk = document.getElementById('confirmCheck');
    var castBtn    = document.getElementById('castVoteBtn');
    var voteForm   = document.getElementById('voteForm');
    var modal      = new bootstrap.Modal(document.getElementById('reviewModal'));

    if (reviewBtn) {
        reviewBtn.addEventListener('click', function () {
            // Collect selections
            var positionCards = document.querySelectorAll('.position-card');
            var html = '';
            var hasAny = false;

            positionCards.forEach(function (card) {
                var position = card.dataset.position;
                var selected = card.querySelectorAll('input:checked');
                if (selected.length === 0) return;

                hasAny = true;
                html += '<div class="review-position-block">';
                html += '<div class="review-position-label">' + escHtml(position) + '</div>';

                selected.forEach(function (inp) {
                    var label = document.querySelector('label[for="' + inp.id + '"]');
                    var name  = label ? label.dataset.name  : '—';
                    var party = label ? label.dataset.party : '';
                    var photo = label ? label.dataset.photo : '';

                    html += '<div class="review-candidate-row">';
                    if (photo) {
                        html += '<img src="' + escHtml(photo) + '" class="review-photo" alt="">';
                    } else {
                        html += '<div class="review-photo-placeholder"><i class="bi bi-person-fill"></i></div>';
                    }
                    html += '<div>';
                    html += '<div class="fw-semibold">' + escHtml(name) + '</div>';
                    html += '<div class="text-muted small">' + escHtml(party) + '</div>';
                    html += '</div></div>';
                });

                html += '</div>';
            });

            if (!hasAny) {
                alert('Please select at least one candidate before reviewing.');
                return;
            }

            reviewBody.innerHTML = html;
            confirmChk.checked = false;
            castBtn.disabled = true;
            modal.show();
        });
    }

    // Enable Cast Vote button only when checkbox is checked
    if (confirmChk) {
        confirmChk.addEventListener('change', function () {
            castBtn.disabled = !this.checked;
        });
    }

    // Cast Vote — submit the form
    if (castBtn) {
        castBtn.addEventListener('click', function () {
            if (!confirmChk.checked) return;
            modal.hide();
            voteForm.submit();
        });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>
</body>
</html>
