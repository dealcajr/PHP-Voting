<?php
require_once '../includes/config.php';
requireRole('admin');

$db = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $school_name = sanitizeInput($_POST['school_name']);
    $school_address = sanitizeInput($_POST['school_address']);
    $school_id_no = sanitizeInput($_POST['school_id_no']);
    $principal_name = sanitizeInput($_POST['principal_name']);

    $stmt = $db->prepare("UPDATE school_info SET school_name = ?, school_address = ?, school_id_no = ?, principal_name = ?");
    $stmt->execute([$school_name, $school_address, $school_id_no, $principal_name]);

    header('Location: school.php?success=1');
    exit();
}

$stmt = $db->query("SELECT * FROM school_info LIMIT 1");
$settings = $stmt->fetch();

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <h2>School Information</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">School information updated successfully.</div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="mb-3">
            <label for="school_name" class="form-label">School Name</label>
            <input type="text" class="form-control" id="school_name" name="school_name" value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label for="school_address" class="form-label">School Address</label>
            <textarea class="form-control" id="school_address" name="school_address" rows="3" required><?php echo htmlspecialchars($settings['school_address'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="school_id_no" class="form-label">School ID No.</label>
            <input type="text" class="form-control" id="school_id_no" name="school_id_no" value="<?php echo htmlspecialchars($settings['school_id_no'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label for="principal_name" class="form-label">Principal Name</label>
            <input type="text" class="form-control" id="principal_name" name="principal_name" value="<?php echo htmlspecialchars($settings['principal_name'] ?? ''); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<?php include '../includes/admin_footer.php'; ?>
