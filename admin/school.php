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

    $stmt = $db->prepare("UPDATE school_info SET school_name = ?, school_address = ?");
    $stmt->execute([$school_name, $school_address]);

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
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<?php include '../includes/admin_footer.php'; ?>
