<?php
require_once '../includes/config.php';
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
            if ($action === 'update_theme') {
                $theme_color = sanitizeInput($_POST['theme_color'] ?? '#343a40');
                $allowed_ips = sanitizeInput($_POST['allowed_ips'] ?? '');

                // Handle logo upload
                $logo_path = $election['logo_path'];
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = UPLOAD_DIR;
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
                        $message = '<div class="alert alert-danger">Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.</div>';
                    } elseif ($_FILES['logo']['size'] > MAX_FILE_SIZE) {
                        $message = '<div class="alert alert-danger">File size too large. Maximum size is 2MB.</div>';
                    } else {
                        $filename = 'logo_' . time() . '.' . $file_extension;
                        $filepath = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                            // Delete old logo if exists
                            if ($logo_path && file_exists($logo_path)) {
                                unlink($logo_path);
                            }
                            $logo_path = $filepath;
                        } else {
                            $message = '<div class="alert alert-danger">Failed to upload logo.</div>';
                        }
                    }
                }

                if (!$message) {
                    $stmt = $db->prepare("UPDATE election_settings SET theme_color = ?, logo_path = ?, allowed_ips = ? WHERE id = ?");
                    $stmt->execute([$theme_color, $logo_path, $allowed_ips, $election['id']]);

                    logAdminAction('theme_updated', 'Updated theme settings');
                    $message = '<div class="alert alert-success">Theme settings updated successfully.</div>';
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
    <h1>Theme Customization</h1>
    <?php echo $message; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Theme Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Theme Customization</h4>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_theme">

                        <div class="mb-3">
                            <label for="theme_color" class="form-label">Theme Color</label>
                            <input type="color" class="form-control form-control-color" id="theme_color" name="theme_color" value="<?php echo htmlspecialchars($election['theme_color'] ?? '#343a40'); ?>" title="Choose theme color">
                            <small class="form-text text-muted">This color will be used for primary elements throughout the system.</small>
                        </div>

                        <div class="mb-3">
                            <label for="logo" class="form-label">Election Logo</label>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                            <small class="form-text text-muted">Upload a logo image (JPG, PNG, GIF). Maximum size: 2MB.</small>
                            <?php if ($election['logo_path']): ?>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($election['logo_path']); ?>" alt="Current Logo" style="max-height: 100px;">
                                    <p class="mb-0"><small>Current logo</small></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="allowed_ips" class="form-label">Allowed IP Addresses</label>
                            <textarea class="form-control" id="allowed_ips" name="allowed_ips" rows="3" placeholder="Enter IP addresses, one per line (optional)"><?php echo htmlspecialchars($election['allowed_ips'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Restrict access to specific IP addresses. Leave empty to allow all IPs.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Theme Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
