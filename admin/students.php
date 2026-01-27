<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

$db = getDBConnection();
$message = '';

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];

            try {
                $handle = fopen($file, 'r');
                if ($handle === false) {
                    throw new Exception('Could not open CSV file.');
                }

                $db->beginTransaction();
                $imported = 0;
                $skipped = 0;

                // Skip header row
                fgetcsv($handle);

                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 6) {
                        $student_id = trim($data[0]);
                        $first_name = trim($data[1]);
                        $last_name = trim($data[2]);
                        $grade = trim($data[3]);
                        $section = trim($data[4]);
                        $password = trim($data[5]);

                        if (!empty($student_id) && !empty($password)) {
                            // Check if student already exists
                            $existing = $db->prepare("SELECT id FROM users WHERE student_id = ?")->execute([$student_id])->fetch();

                            if (!$existing) {
                                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $db->prepare("INSERT INTO users (student_id, password_hash, role, first_name, last_name, grade, section) VALUES (?, ?, 'voter', ?, ?, ?, ?)");
                                $stmt->execute([$student_id, $password_hash, $first_name, $last_name, $grade, $section]);
                                $imported++;
                            } else {
                                $skipped++;
                            }
                        }
                    }
                }

                $db->commit();
                fclose($handle);

                logAdminAction('students_imported', "Imported $imported students, skipped $skipped duplicates");
                $message = "<div class='alert alert-success'>Successfully imported $imported students. Skipped $skipped duplicates.</div>";

            } catch (Exception $e) {
                $db->rollBack();
                if (isset($handle)) fclose($handle);
                $message = '<div class="alert alert-danger">Import failed: ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Please select a valid CSV file.</div>';
        }
    }
}

// Handle individual student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $action = $_POST['action'];
        $student_id = $_POST['student_id'] ?? 0;

        try {
            if ($action === 'activate') {
                $db->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'voter'")->execute([$student_id]);
                logAdminAction('student_activated', "Activated student ID: $student_id");
                $message = '<div class="alert alert-success">Student activated successfully.</div>';
            } elseif ($action === 'deactivate') {
                $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'voter'")->execute([$student_id]);
                logAdminAction('student_deactivated', "Deactivated student ID: $student_id");
                $message = '<div class="alert alert-success">Student deactivated successfully.</div>';
            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM users WHERE id = ? AND role = 'voter'")->execute([$student_id]);
                logAdminAction('student_deleted', "Deleted student ID: $student_id");
                $message = '<div class="alert alert-success">Student deleted successfully.</div>';
            } elseif ($action === 'generate_id') {
                $voter_id_card = 'VOTER-' . strtoupper(substr(md5(uniqid()), 0, 8));
                $db->prepare("UPDATE users SET voter_id_card = ? WHERE id = ? AND role = 'voter'")->execute([$voter_id_card, $student_id]);
                logAdminAction('voter_id_generated', "Generated voter ID for student ID: $student_id");
                $message = '<div class="alert alert-success">Voter ID generated successfully.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Get students
$students = $db->query("SELECT * FROM users WHERE role = 'voter' ORDER BY grade, section, last_name, first_name")->fetchAll();

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Student Management</h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload Students (CSV)</button>
            <a href="print_ids.php" target="_blank" class="btn btn-secondary">Print Voter IDs</a>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h4>Registered Students (<?php echo count($students); ?>)</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Grade & Section</th>
                            <th>Status</th>
                            <th>Voter ID Card</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo htmlspecialchars('Grade ' . $student['grade'] . '-' . $student['section']); ?></td>
                                <td>
                                    <span class="badge <?php echo $student['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($student['voter_id_card'] ?? 'Not Generated'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $student['is_active'] ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $student['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                                <?php echo $student['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <?php if (empty($student['voter_id_card'])): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="action" value="generate_id">
                                                <button type="submit" class="btn btn-outline-info btn-sm">Generate ID</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this student?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Students from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Upload a CSV file with the following columns:</p>
                <code>student_id, first_name, last_name, grade, section, password</code>
                <br><small class="text-muted">Note: Existing students will be skipped.</small>

                <div class="alert alert-info">
                    <strong>CSV Format Example:</strong><br>
                    <code>STU001,John,Doe,12,A,password123</code><br>
                    <code>STU002,Jane,Smith,12,B,password456</code>
                </div>

                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="upload_csv" value="1">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload & Import</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
