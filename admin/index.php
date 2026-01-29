<?php
require_once '../includes/config.php';
requireRole('admin');

// Fetch data for dashboard widgets
$db = getDBConnection();
$total_voters = $db->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$total_candidates = $db->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$total_votes = $db->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$election_status = $db->query("SELECT is_open FROM election_settings LIMIT 1")->fetchColumn();

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <h2>Admin Dashboard</h2>
    <div class="row">
        <div class="col-md-3">
            <div class="widget">
                <h3>Total Voters</h3>
                <p><?php echo $total_voters; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="widget">
                <h3>Total Candidates</h3>
                <p><?php echo $total_candidates; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="widget">
                <h3>Total Votes Cast</h3>
                <p><?php echo $total_votes; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="widget">
                <h3>Election Status</h3>
                <p><?php echo $election_status ? 'Open' : 'Closed'; ?></p>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="election.php" class="btn btn-primary">Manage Election</a>
                        <a href="candidates.php" class="btn btn-secondary">Manage Candidates</a>
                        <a href="students.php" class="btn btn-info">Manage Students</a>
                        <a href="../register.php" class="btn btn-outline-success">Register New Student</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>System Settings</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="school.php" class="btn btn-outline-primary">School Info</a>
                        <a href="../results.php" class="btn btn-outline-info">View Live Results</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
include '../includes/admin_footer.php';
?>
