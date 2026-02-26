<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

$db = getDBConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $error = "New password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
        } else {
            try {
                // Get current admin password hash
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'User not found.';
                } elseif (!password_verify($current_password, $user['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    // Update password
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $_SESSION['user_id']]);
                    
                    logAdminAction('password_changed', 'Admin changed password');
                    $message = 'Password changed successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <h2>Change Password</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                alert('Password has been changed successfully!\n\nYou will now be logged out. Please log in again with your new password.');
                window.location.href = '../logout.php';
            });
        </script>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 500px;">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password *</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password *</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                    <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to change your password? You will need to log in again.')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    });
</script>
