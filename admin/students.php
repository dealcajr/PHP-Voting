<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

$db = getDBConnection();
$message = '';

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if ($handle !== false) {
            $headers = fgetcsv($handle);
            $expected_headers = ['student_id', 'lrn', 'first_name', 'last_name', 'grade', 'section', 'password'];
            
            if ($headers !== $expected_headers) {
                $message = '<div class="alert alert-danger">Invalid CSV format. Expected columns: ' . implode(', ', $expected_headers) . '</div>';
            } else {
                $imported = 0;
                $errors = [];
                
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) !== 7) {
                        $errors[] = "Invalid row data";
                        continue;
                    }
                    
                    list($student_id, $lrn, $first_name, $last_name, $grade, $section, $password) = $data;
                    
                    // Skip empty rows
                    if (empty($student_id) || empty($lrn)) {
                        continue;
                    }
                    
                    // Validate LRN is exactly 12 digits
                    if (!preg_match('/^\d{12}$/', $lrn)) {
                        $errors[] = "Invalid LRN for $student_id: LRN must be exactly 12 digits";
                        continue;
                    }
                    
                    try {
                        // Check if student_id already exists
                        $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        if ($stmt->fetch()) {
                            $errors[] = "Student ID $student_id already exists";
                            continue;
                        }
                        
                        // Check if LRN already exists
                        $stmt = $db->prepare("SELECT id FROM users WHERE lrn = ?");
                        $stmt->execute([$lrn]);
                        if ($stmt->fetch()) {
                            $errors[] = "LRN $lrn already exists";
                            continue;
                        }
                        
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $voter_id_card = 'VOTER-' . strtoupper(substr(md5(uniqid()), 0, 8));
                        
                        $stmt = $db->prepare("INSERT INTO users (student_id, lrn, password_hash, role, first_name, last_name, grade, section, voter_id_card, is_active) VALUES (?, ?, ?, 'voter', ?, ?, ?, ?, ?, 1)");
                        $stmt->execute([$student_id, $lrn, $password_hash, $first_name, $last_name, $grade, $section, $voter_id_card]);
                        $imported++;
                    } catch (PDOException $e) {
                        $errors[] = "Error importing $student_id: " . $e->getMessage();
                    }
                }
                
                fclose($handle);
                
                if ($imported > 0) {
                    logAdminAction('students_imported', "Imported $imported students via CSV");
                    $message = '<div class="alert alert-success">Successfully imported ' . $imported . ' students.</div>';
                }
                
                if (!empty($errors)) {
                    $message .= '<div class="alert alert-warning"><strong>Errors:</strong><br>' . implode('<br>', array_slice($errors, 0, 10)) . (count($errors) > 10 ? '<br>... and ' . (count($errors) - 10) . ' more errors' : '') . '</div>';
                }
            }
        } else {
            $message = '<div class="alert alert-danger">Failed to read CSV file.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Please upload a valid CSV file.</div>';
    }
}

// Handle student actions (activate/deactivate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $action = $_POST['action'];
        $student_id = $_POST['student_id'] ?? 0;

        try {
            if ($action === 'activate') {
                $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$student_id]);
                logAdminAction('student_activated', "Activated student ID: $student_id");
                $message = '<div class="alert alert-success">Student activated successfully.</div>';
            } elseif ($action === 'deactivate') {
                $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$student_id]);
                logAdminAction('student_deactivated', "Deactivated student ID: $student_id");
                $message = '<div class="alert alert-success">Student deactivated successfully.</div>';
            } elseif ($action === 'delete') {
                // Don't allow deleting admins
                $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$student_id]);
                $role = $stmt->fetchColumn();
                
                if ($role === 'admin') {
                    $message = '<div class="alert alert-danger">Cannot delete admin users.</div>';
                } else {
                    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$student_id]);
                    logAdminAction('student_deleted', "Deleted student ID: $student_id");
                    $message = '<div class="alert alert-success">Student deleted successfully.</div>';
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Get all students (excluding admins)
$students = $db->query("SELECT * FROM users WHERE role = 'voter' ORDER BY grade, section, last_name, first_name")->fetchAll();

// Get statistics
$total_students = count($students);
$active_students = count(array_filter($students, fn($s) => $s['is_active']));
$inactive_students = $total_students - $active_students;

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Student Management</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-upload"></i> Import CSV
        </button>
    </div>

    <?php echo $message; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h4><?php echo $total_students; ?></h4>
                    <p class="mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $active_students; ?></h4>
                    <p class="mb-0">Active Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h4><?php echo $inactive_students; ?></h4>
                    <p class="mb-0">Inactive Students</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h4>Students (<?php echo $total_students; ?>)</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Grade & Section</th>
                            <th>Voter ID</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['lrn'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                <td><?php echo htmlspecialchars('Grade ' . $student['grade'] . '-' . $student['section']); ?></td>
                                <td><code><?php echo htmlspecialchars($student['voter_id_card']); ?></code></td>
                                <td>
                                    <span class="badge <?php echo $student['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
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
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
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

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Students from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="import_csv" value="1">
                    
                    <p>Upload a CSV file with the following columns:</p>
                    <code>student_id, lrn, first_name, last_name, grade, section, password</code>
                    <p class="text-muted small">Note: LRN must be exactly 12 digits</p>
                    
                    <div class="mb-3 mt-3">
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Maximum file size: 2MB</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Sample CSV format:</strong><br>
                        <code>
                        student_id,lrn,first_name,last_name,grade,section,password<br>
                        STU001,123456789012,Juan,Dela Cruz,10,A,password123<br>
                        STU002,123456789013,Maria,Santos,10,B,password123
                        </code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Students</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
