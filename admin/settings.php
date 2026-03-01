<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

$db = getDBConnection();
$message = '';
$error = '';

// Get current election settings
$election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? 'change_password';

        // ── Change Password ──────────────────────────────────────────────────
        if ($action === 'change_password') {
            $current_password  = $_POST['current_password'] ?? '';
            $new_password      = $_POST['new_password'] ?? '';
            $confirm_password  = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New password and confirmation do not match.';
            } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                $error = "New password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
            } else {
                try {
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $error = 'User not found.';
                    } elseif (!password_verify($current_password, $user['password_hash'])) {
                        $error = 'Current password is incorrect.';
                    } else {
                        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$new_hash, $_SESSION['user_id']]);

                        logAdminAction('password_changed', 'Admin changed password');
                        $message = 'password_changed';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }

        // ── Update Theme ─────────────────────────────────────────────────────
        } elseif ($action === 'update_theme') {
            $theme_color = sanitizeInput($_POST['theme_color'] ?? '#007a1b');
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
                    $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                } elseif ($_FILES['logo']['size'] > MAX_FILE_SIZE) {
                    $error = 'File size too large. Maximum size is 2MB.';
                } else {
                    $filename = 'logo_' . time() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;

                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                        if ($logo_path && file_exists($logo_path)) {
                            unlink($logo_path);
                        }
                        $logo_path = $filepath;
                    } else {
                        $error = 'Failed to upload logo.';
                    }
                }
            }

            if (!$error) {
                $stmt = $db->prepare("UPDATE election_settings SET theme_color = ?, logo_path = ?, allowed_ips = ? WHERE id = ?");
                $stmt->execute([$theme_color, $logo_path, $allowed_ips, $election['id']]);

                logAdminAction('theme_updated', 'Updated theme settings');
                $message = 'theme_updated';

                // Refresh election data
                $election = $db->query("SELECT * FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <h2 class="mb-4">Admin Settings</h2>

    <?php if ($message === 'password_changed'): ?>
        <div class="alert alert-success">Password changed successfully. You will be logged out.</div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                alert('Password has been changed successfully!\n\nYou will now be logged out. Please log in again with your new password.');
                window.location.href = '../logout.php';
            });
        </script>
    <?php elseif ($message === 'theme_updated'): ?>
        <div class="alert alert-success">Theme settings updated successfully.</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── Change Password Card ─────────────────────────────────────── -->
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0 text-dark"><i class="bi bi-key me-2"></i>Admin Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="passwordForm" data-no-transition>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Theme Customization Card ────────────────────────────────── -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0 text-dark"><i class="bi bi-palette me-2"></i>Theme Customization</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_theme">

                        <div class="mb-3">
                            <label for="theme_color" class="form-label">Theme Color</label>
                            <input type="color" class="form-control form-control-color" id="theme_color" name="theme_color"
                                value="<?php echo htmlspecialchars($election['theme_color'] ?? '#343a40'); ?>"
                                title="Choose theme color">
                            <small class="form-text text-muted">This color will be used for primary elements throughout the system.</small>
                        </div>

                        <div class="mb-3">
                            <label for="logo" class="form-label">Election Logo</label>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                            <small class="form-text text-muted">Upload a logo image (JPG, PNG, GIF). Maximum size: 2MB.</small>
                            <?php if (!empty($election['logo_path'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($election['logo_path']); ?>" alt="Current Logo" style="max-height: 100px;">
                                    <p class="mb-0"><small>Current logo</small></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="allowed_ips" class="form-label">Allowed IP Addresses</label>
                            <textarea class="form-control" id="allowed_ips" name="allowed_ips" rows="3"
                                placeholder="Enter IP addresses, one per line (optional)"><?php echo htmlspecialchars($election['allowed_ips'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Restrict access to specific IP addresses. Leave empty to allow all IPs.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Theme Settings</button>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /.row -->
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function (e) {
                if (!confirm('Are you sure you want to change your password? You will need to log in again.')) {
                    e.preventDefault();
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
