<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: ../admin_login.php");
    exit;
}

// Check election status from database
$db = getDBConnection();
$election_status = $db->query("SELECT is_open FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
$voting_active = $election_status['is_open'] == 1;
$voting_closed = $election_status['is_open'] == 0;

// Load school settings from database
$school_info      = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();
$election_settings = $db->query("SELECT logo_path, theme_color FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

$settings = [
    'school_name'           => $school_info['school_name'] ?? 'Sample High School',
    'school_id'             => $school_info['school_id_no'] ?? 'SHS-2026',
    'principal'             => $school_info['principal_name'] ?? 'Dr. Juan Santos',
    'logo_path'             => $election_settings['logo_path'] ?? '../assets/images/logo_1770105233.png',
    'theme_color'           => $election_settings['theme_color'] ?? '#007a1b',
    'school_classification' => 'Small',
];

// Determine system title based on school level
$school_level = $settings['school_level'] ?? 'Junior High School';
$system_title = $school_level === 'Elementary'
    ? "Supreme Elementary Learner Government Election System"
    : "Supreme Secondary Learner Government Election System";

// Fetch data for dashboard widgets
$total_students  = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$voted_students  = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
$turnout         = $total_students > 0 ? round(($voted_students / $total_students) * 100, 2) : 0;
$total_candidates = $db->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$total_votes     = $db->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$active_students = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_active = 1")->fetchColumn();

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<style>
:root {
    --theme-color: <?php echo htmlspecialchars($settings['theme_color']); ?>;
}
.dashboard-header {
    background: var(--theme-color, #007a1b);
    color: #ffffff;
    padding: 2.5rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.dashboard-header .school-logo {
    width: 90px;
    height: 90px;
    object-fit: cover;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    border: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.13);
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    background: var(--theme-color);
}
.stat-card .stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
}
.stat-card.students .stat-icon  { background: #667eea; color: white; }
.stat-card.voted .stat-icon     { background: #28a745; color: white; }
.stat-card.turnout .stat-icon   { background: #ffc107; color: white; }
.stat-card.candidates .stat-icon{ background: #e83e8c; color: white; }
.stat-card.votes .stat-icon     { background: #6f42c1; color: white; }
.stat-card.active .stat-icon    { background: #17a2b8; color: white; }
.stat-card .stat-value {
    font-size: 2rem; font-weight: bold; color: #2d3748; margin-bottom: 0.25rem;
}
.stat-card .stat-label {
    color: #718096; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.8rem;
}
.quick-actions {
    background: white; border-radius: 12px; padding: 1.5rem;
    margin-bottom: 1rem; box-shadow: 0 3px 12px rgba(0,0,0,0.08);
}
.quick-actions h5 { color: #2d3748; margin-bottom: 1rem; font-weight: 600; }
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
}
.action-btn {
    display: flex; align-items: center;
    padding: 0.75rem 1rem;
    background: #f8f9fa; border: none; border-radius: 8px;
    text-decoration: none; color: #495057;
    transition: all 0.25s ease; font-weight: 500; font-size: 0.9rem;
}
.action-btn:hover {
    background: var(--theme-color, #007a1b);
    color: white; transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.action-btn i { font-size: 1.2rem; margin-right: 0.6rem; width: 24px; }
.voting-status-box {
    background: white; border-radius: 12px; padding: 1.25rem;
    margin-bottom: 1rem; box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    text-align: center;
}
.status-indicator {
    display: inline-flex; align-items: center;
    padding: 0.6rem 1.25rem; border-radius: 25px;
    font-weight: 600; font-size: 1rem;
}
.status-active   { background: #28a745; color: white; }
.status-inactive { background: #ffc107; color: white; }
.status-closed   { background: #aa0606; color: white; }

/* Live Results Panel */
.live-results-panel {
    background: white; border-radius: 12px; padding: 1.5rem;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    height: 100%;
}
.live-results-panel h5 {
    color: #2d3748; font-weight: 600; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.live-results-panel .refresh-badge {
    font-size: 0.75rem; background: #28a745; color: white;
    padding: 2px 8px; border-radius: 12px; font-weight: 400;
}
.position-block { margin-bottom: 1.5rem; }
.position-block h6 {
    font-weight: 700; color: #495057;
    border-bottom: 2px solid var(--theme-color, #007a1b);
    padding-bottom: 0.4rem; margin-bottom: 0.75rem;
}
.candidate-row .progress { height: 7px; border-radius: 4px; margin-top: 3px; }
.candidate-row.leader .name { color: var(--theme-color, #007a1b); }
#liveResultsContainer .no-data {
    text-align: center; color: #aaa; padding: 2rem 0; font-size: 0.95rem;
}
</style>

<div class="admin-content">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container text-center">
            <?php if (!empty($settings['logo_path']) && file_exists($settings['logo_path'])): ?>
                <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="School Logo" class="school-logo mb-3">
            <?php else: ?>
                <div class="school-logo bg-white d-inline-flex align-items-center justify-content-center mb-3 rounded-circle" style="color: #667eea; font-weight: bold; font-size: 1.2em;">LOGO</div>
            <?php endif; ?>
            <h1 class="display-6 mb-1 fw-bold"><?= htmlspecialchars($system_title) ?></h1>
            <h2 class="h5 text-white-50 mb-1"><?= htmlspecialchars($settings['school_name']) ?></h2>
            <p class="mb-0 opacity-75 small">School ID: <?= htmlspecialchars($settings['school_id']) ?> &nbsp;|&nbsp; Principal: <?= htmlspecialchars($settings['principal']) ?></p>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row g-4">

            <!-- ── LEFT COLUMN ─────────────────────────────────────────── -->
            <div class="col-xl-6 col-lg-7">

                <!-- Statistics Cards (3 per row) -->
                <div class="row g-3 mb-2">
                    <div class="col-4">
                        <div class="stat-card students">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-value"><?= $total_students ?></div>
                            <div class="stat-label">Students</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card voted">
                            <div class="stat-icon"><i class="fas fa-vote-yea"></i></div>
                            <div class="stat-value"><?= $voted_students ?></div>
                            <div class="stat-label">Voted</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card turnout">
                            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-value"><?= $turnout ?>%</div>
                            <div class="stat-label">Turnout</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card candidates">
                            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="stat-value"><?= $total_candidates ?></div>
                            <div class="stat-label">Candidates</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card votes">
                            <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                            <div class="stat-value"><?= $total_votes ?></div>
                            <div class="stat-label">Total Votes</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card active">
                            <div class="stat-icon"><i class="fas fa-cog"></i></div>
                            <div class="stat-value"><?= $active_students ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                </div>

                <!-- Voting Status -->
                <div class="voting-status-box">
                    <h6 class="mb-2 fw-semibold text-muted text-uppercase" style="font-size:.8rem; letter-spacing:.5px;">Election Status</h6>
                    <?php if ($voting_closed): ?>
                        <div class="status-indicator status-closed">
                            <i class="fas fa-lock me-2"></i>Voting is CLOSED
                        </div>
                    <?php elseif ($voting_active): ?>
                        <div class="status-indicator status-active">
                            <i class="fas fa-play me-2"></i>Voting is ACTIVE
                        </div>
                    <?php else: ?>
                        <div class="status-indicator status-inactive">
                            <i class="fas fa-pause me-2"></i>Voting is INACTIVE
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    <div class="action-grid">
                        <a href="school.php" class="action-btn"><i class="fas fa-school"></i><span>Edit School Info</span></a>
                        <a href="students.php" class="action-btn"><i class="fas fa-users"></i><span>Manage Students</span></a>
                        <a href="print_ids.php" class="action-btn"><i class="fas fa-print"></i><span>Print Voter IDs</span></a>
                        <a href="candidates.php" class="action-btn"><i class="fas fa-user-tie"></i><span>View Candidates</span></a>
                        <a href="election.php" class="action-btn"><i class="fas fa-cog"></i><span>Manage Election</span></a>
                        <a href="settings.php" class="action-btn"><i class="fas fa-toggle-on"></i><span>Admin Settings</span></a>
                    </div>
                </div>

            </div><!-- /left column -->

            <!-- ── RIGHT COLUMN — Live Election Results ────────────────── -->
            <div class="col-xl-6 col-lg-5">
                <div class="live-results-panel">
                    <h5>
                        <i class="fas fa-chart-bar"></i>
                        Live Election Results
                        <span class="refresh-badge" id="refreshBadge">● Live</span>
                    </h5>
                    <div id="liveResultsContainer">
                        <div class="no-data">Loading results…</div>
                    </div>
                    <p class="text-muted small mb-0 mt-2" id="lastUpdated"></p>
                </div>
            </div><!-- /right column -->

        </div><!-- /.row -->

        <!-- Footer -->
        <div class="text-center mt-4 text-muted">
            <p class="mb-1 small">Powered by <?= htmlspecialchars($system_title) ?></p>
            <p class="mb-0 small">Developed by: <a href="https://www.facebook.com/Dealca27" target="_blank" class="text-primary text-decoration-none">Norman A'l Dump</a></p>
        </div>
    </div>
</div>

<script>
(function () {
    var container   = document.getElementById('liveResultsContainer');
    var lastUpdated = document.getElementById('lastUpdated');
    var badge       = document.getElementById('refreshBadge');
    var INTERVAL    = 10000; // refresh every 10 seconds

    function pad(n) { return n < 10 ? '0' + n : n; }

    function timeStr() {
        var d = new Date();
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function buildBar(voteCount, maxVotes, isLeader) {
        var pct = maxVotes > 0 ? Math.round((voteCount / maxVotes) * 100) : 0;
        var barClass = isLeader ? 'progress-bar bg-success' : 'progress-bar bg-secondary';
        return '<div class="progress mt-1">' +
               '<div class="' + barClass + '" role="progressbar" style="width:' + pct + '%" ' +
               'aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"></div>' +
               '</div>';
    }

    function renderResults(data) {
        var results = data.results;
        var positions = Object.keys(results);

        if (positions.length === 0) {
            container.innerHTML = '<div class="no-data">No candidates found.</div>';
            return;
        }

        var html = '';
        positions.forEach(function (position) {
            var candidates = results[position];
            // Already sorted desc by vote_count from server
            var maxVotes = candidates.length > 0 ? parseInt(candidates[0].vote_count) : 0;

            html += '<div class="position-block">';
            html += '<h6>' + escHtml(position) + '</h6>';

            candidates.forEach(function (c, idx) {
                    var votes    = parseInt(c.vote_count);
                    var isLeader = idx === 0 && votes > 0;
                    var rowClass = isLeader ? 'candidate-row leader' : 'candidate-row';
    
                    html += '<div class="' + rowClass + ' d-flex align-items-center gap-2 mb-2">';
    
                    // Circle photo — paths are stored relative to root (e.g. assets/images/...)
                    // Admin pages are in /admin/, so prefix with ../
                    var photoSrc = c.photo ? ('../' + c.photo) : '';
                    if (photoSrc) {
                        html += '<img src="' + escHtml(photoSrc) + '" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid ' + (isLeader ? 'var(--theme-color,#007a1b)' : '#dee2e6') + ';flex-shrink:0;">';
                    } else {
                        html += '<div style="width:40px;height:40px;border-radius:50%;background:#dee2e6;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem;color:#adb5bd;">👤</div>';
                    }
    
                    // Info block
                    html += '<div style="flex:1;min-width:0;">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<span class="name fw-semibold" style="font-size:.88rem;">' + (isLeader ? '🏆 ' : '') + escHtml(c.name) + '</span>';
                    html += '<span class="votes-label fw-bold" style="font-size:.85rem;white-space:nowrap;">' + votes + ' vote' + (votes !== 1 ? 's' : '') + '</span>';
                    html += '</div>';
    
                    // Party, grade, section
                    var meta = [];
                    if (c.party) meta.push(escHtml(c.party));
                    if (c.grade && c.section) meta.push('Grade ' + escHtml(c.grade) + '-' + escHtml(c.section));
                    if (meta.length) {
                        html += '<div style="font-size:.75rem;color:#718096;">' + meta.join(' &nbsp;·&nbsp; ') + '</div>';
                    }
    
                    html += buildBar(votes, maxVotes, isLeader);
                    html += '</div>'; // info block
                    html += '</div>'; // candidate-row
                });

            html += '</div>';
        });

        container.innerHTML = html;
        lastUpdated.textContent = 'Last updated: ' + timeStr();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fetchResults() {
        badge.textContent = '↻ Updating…';
        fetch('live_results.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderResults(data);
                badge.textContent = '● Live';
                badge.style.background = '#28a745';
            })
            .catch(function () {
                badge.textContent = '✕ Error';
                badge.style.background = '#dc3545';
            });
    }

    // Initial load + periodic refresh
    fetchResults();
    setInterval(fetchResults, INTERVAL);
})();
</script>

<?php include '../includes/admin_footer.php'; ?>
