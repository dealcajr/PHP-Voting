<?php
require_once __DIR__ . '/../includes/config.php';
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

    // Handle logo upload
    $logo_path = $settings['logo_path'] ?? null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
            die('Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.');
        } elseif ($_FILES['logo']['size'] > MAX_FILE_SIZE) {
            die('File size too large. Maximum size is 2MB.');
        } else {
            $filename = 'school_logo_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                // Delete old logo if exists
                if ($logo_path && file_exists('../' . $logo_path)) {
                    unlink('../' . $logo_path);
                }
                $logo_path = 'assets/images/' . $filename;
            } else {
                die('Failed to upload logo.');
            }
        }
    }

    $stmt = $db->prepare("UPDATE school_info SET school_name = ?, school_address = ?, school_id_no = ?, principal_name = ?, logo_path = ?");
    $stmt->execute([$school_name, $school_address, $school_id_no, $principal_name, $logo_path]);

    header('Location: school.php?success=1');
    exit();
}

$stmt = $db->query("SELECT * FROM school_info LIMIT 1");
$settings = $stmt->fetch();

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
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

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
