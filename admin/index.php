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
$db = getDBConnection();
$school_info = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();
$election_settings = $db->query("SELECT logo_path FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

$settings = [
    'school_name' => $school_info['school_name'] ?? 'Sample High School',
    'school_id' => $school_info['school_id_no'] ?? 'SHS-2026',
    'principal' => $school_info['principal_name'] ?? 'Dr. Juan Santos',
    'logo_path' => $election_settings['logo_path'] ?? '../assets/images/logo_1770105233.png',
    'school_classification' => 'Small'
];

// Determine system title based on school level
$school_level = $settings['school_level'] ?? 'Junior High School';
if ($school_level === 'Elementary') {
    $system_title = "Supreme Elementary Learner Government Election System";
} else {
    $system_title = "Supreme Secondary Learner Government Election System";
}

// Fetch data for dashboard widgets
$db = getDBConnection();
$total_students = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$voted_students = $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();
$turnout = $total_students > 0 ? round(($voted_students / $total_students) * 100, 2) : 0;
$total_candidates = $db->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$total_votes = $db->query("SELECT COUNT(*) FROM votes")->fetchColumn();

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.dashboard-header .school-logo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3);
    object-fit: cover;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.stat-card.students .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.stat-card.voted .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.stat-card.turnout .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
.stat-card.candidates .stat-icon { background: linear-gradient(135deg, #e83e8c, #dc3545); color: white; }
.stat-card.votes .stat-icon { background: linear-gradient(135deg, #6f42c1, #6610f2); color: white; }

.stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    color: #718096;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9rem;
}

.quick-actions {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.quick-actions h4 {
    color: #2d3748;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.action-btn {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
    font-weight: 500;
}

.action-btn:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.action-btn i {
    font-size: 1.5rem;
    margin-right: 0.75rem;
    width: 30px;
}

.voting-status {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-align: center;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 1.1rem;
}

.status-active { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.status-inactive { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
.status-closed { background: linear-gradient(135deg, #6c757d, #495057); color: white; }

.results-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.results-section h4 {
    color: #2d3748;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .dashboard-header {
        padding: 2rem 0;
    }

    .action-grid {
        grid-template-columns: 1fr;
    }

    .stat-card .stat-value {
        font-size: 2rem;
    }
}
</style>

<div class="admin-content">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container text-center">
            <?php if (file_exists($settings['logo_path'])): ?>
                <img src="<?= $settings['logo_path'] ?>" alt="School Logo" class="school-logo mb-3">
            <?php else: ?>
                <div class="school-logo bg-white d-inline-flex align-items-center justify-content-center mb-3" style="color: #667eea; font-weight: bold; font-size: 1.5em;">LOGO</div>
            <?php endif; ?>

            <h1 class="display-5 mb-2 font-weight-bold"><?= htmlspecialchars($system_title) ?></h1>
            <h2 class="h5 text-white-50 mb-2"><?= htmlspecialchars($settings['school_name']) ?></h2>
            <p class="mb-0 opacity-75">School ID: <?= htmlspecialchars($settings['school_id']) ?> | Principal: <?= htmlspecialchars($settings['principal']) ?> | Classification: <?= htmlspecialchars($settings['school_classification']) ?></p>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card students">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $total_students ?></div>
                    <div class="stat-label">Students</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card voted">
                    <div class="stat-icon">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="stat-value"><?= $voted_students ?></div>
                    <div class="stat-label">Voted</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card turnout">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?= $turnout ?>%</div>
                    <div class="stat-label">Turnout</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card candidates">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-value"><?= $total_candidates ?></div>
                    <div class="stat-label">Candidates</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card votes">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-value"><?= $total_votes ?></div>
                    <div class="stat-label">Total Votes</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-value">
                        <?php
                        $active_students = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_active = 1")->fetchColumn();
                        echo $active_students;
                        ?>
                    </div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>

        <!-- Voting Status -->
        <div class="voting-status">
            <h4 class="mb-3">Election Status</h4>
            <?php if ($voting_closed): ?>
                <div class="status-indicator status-closed">
                    <i class="fas fa-lock me-2"></i>
                    Voting is CLOSED
                </div>
            <?php elseif ($voting_active): ?>
                <div class="status-indicator status-active">
                    <i class="fas fa-play me-2"></i>
                    Voting is ACTIVE
                </div>
            <?php else: ?>
                <div class="status-indicator status-inactive">
                    <i class="fas fa-pause me-2"></i>
                    Voting is INACTIVE
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h4><i class="fas fa-bolt me-2"></i>Quick Actions</h4>
            <div class="action-grid">
                <a href="school.php" class="action-btn">
                    <i class="fas fa-school"></i>
                    <span>Edit School Info</span>
                </a>
                <a href="students.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <span>Manage Students</span>
                </a>
                <a href="print_ids.php" class="action-btn">
                    <i class="fas fa-print"></i>
                    <span>Print Voter IDs</span>
                </a>
                <a href="candidates.php" class="action-btn">
                    <i class="fas fa-user-tie"></i>
                    <span>View Candidates</span>
                </a>
                <a href="election.php" class="action-btn">
                    <i class="fas fa-cog"></i>
                    <span>Manage Election</span>
                </a>
                <a href="../admin_login.php" class="action-btn">
                    <i class="fas fa-toggle-on"></i>
                    <span>Voting Control</span>
                </a>
            </div>
        </div>

        <!-- Live Results -->
        <div class="results-section">
            <h4><i class="fas fa-chart-bar me-2"></i>Live Election Results</h4>
            <div id="results"></div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-5 text-muted">
            <p class="mb-2">Powered by <?= htmlspecialchars($system_title) ?></p>
            <p class="mb-0">Developed by: <a href="https://www.facebook.com/Dealca27" target="_blank" class="text-primary text-decoration-none">Norman A'l Dump</a></p>
        </div>
    </div>
</div>

<?php
include '../includes/admin_footer.php';
?>
