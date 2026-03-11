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
                    
                    // Skip Grade 12 students - not eligible to vote
                    if ($grade == '12') {
                        $errors[] = "Skipped $student_id: Grade 12 students are not eligible to register or vote";
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
                    // First delete all votes by this user to prevent orphaned votes
                    $db->prepare("DELETE FROM votes WHERE voter_id = ?")->execute([$student_id]);
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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $bulk_action = $_POST['bulk_action'];
        $selected_ids = $_POST['selected_ids'] ?? [];

        // Sanitize: keep only integers
        $selected_ids = array_filter(array_map('intval', $selected_ids));

        if (empty($selected_ids)) {
            $message = '<div class="alert alert-warning">No students selected.</div>';
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            try {
                if ($bulk_action === 'bulk_activate') {
                    $db->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders) AND role = 'voter'")->execute($selected_ids);
                    logAdminAction('bulk_activate', "Bulk activated " . count($selected_ids) . " students");
                    $message = '<div class="alert alert-success">Selected students activated successfully.</div>';
                } 
                    if ($bulk_action === 'bulk_delete') {
                        // Only delete voters, never admins
                        // First delete all votes by these users to prevent orphaned votes
                        $db->prepare("DELETE FROM votes WHERE voter_id IN ($placeholders)")->execute($selected_ids);
                        $db->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'voter'")->execute($selected_ids);
                        logAdminAction('bulk_delete', "Bulk deleted " . count($selected_ids) . " students");
                        $message = '<div class="alert alert-success">Selected students deleted successfully.</div>';
                    }
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Get all students (excluding admins)
$filter_grade = $_GET['filter_grade'] ?? '';
$filter_section = $_GET['filter_section'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_active = $_GET['filter_active'] ?? '';
$search_query = $_GET['search'] ?? '';

$query = "SELECT u.*, CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as has_voted FROM users u LEFT JOIN votes v ON u.id = v.voter_id WHERE u.role = 'voter'";
$params = [];

// Apply filters
if (!empty($filter_grade)) {
    $query .= " AND u.grade = ?";
    $params[] = $filter_grade;
}

if (!empty($filter_section)) {
    $query .= " AND u.section = ?";
    $params[] = $filter_section;
}

if ($filter_status === 'voted') {
    $query .= " AND v.id IS NOT NULL";
} elseif ($filter_status === 'not_voted') {
    $query .= " AND v.id IS NULL";
}

if ($filter_active === 'active') {
    $query .= " AND u.is_active = 1";
} elseif ($filter_active === 'inactive') {
    $query .= " AND u.is_active = 0";
}

if (!empty($search_query)) {
    $query .= " AND (u.student_id LIKE ? OR u.lrn LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$query .= " GROUP BY u.id ORDER BY u.grade, u.section, u.last_name, u.first_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get all grades and sections for filter dropdowns
$grades_query = "SELECT DISTINCT grade FROM users WHERE role = 'voter' ORDER BY CAST(grade AS UNSIGNED)";
$all_grades = $db->query($grades_query)->fetchAll(PDO::FETCH_COLUMN);

$sections_query = "SELECT DISTINCT section FROM users WHERE role = 'voter' ORDER BY section";
$all_sections = $db->query($sections_query)->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$total_students = count($students);
$voted_students = count(array_filter($students, fn($s) => $s['has_voted']));
$not_voted_students = $total_students - $voted_students;

// Get total stats (all students, not just filtered)
$all_students_query = $db->query("SELECT COUNT(DISTINCT u.id) as total, COUNT(DISTINCT v.voter_id) as voted FROM users u LEFT JOIN votes v ON u.id = v.voter_id WHERE u.role = 'voter'")->fetch();
$all_total = $all_students_query['total'];
$all_voted = $all_students_query['voted'];
$all_not_voted = $all_total - $all_voted;

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

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Student ID, LRN, or Name" value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-2">
                    <label for="filter_grade" class="form-label">Grade</label>
                    <select class="form-select" id="filter_grade" name="filter_grade">
                        <option value="">All Grades</option>
                        <?php foreach ($all_grades as $grade): ?>
                            <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo $filter_grade === $grade ? 'selected' : ''; ?>>Grade <?php echo htmlspecialchars($grade); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_section" class="form-label">Section</label>
                    <select class="form-select" id="filter_section" name="filter_section">
                        <option value="">All Sections</option>
                        <?php foreach ($all_sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $filter_section === $section ? 'selected' : ''; ?>><?php echo htmlspecialchars($section); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_status" class="form-label">Vote Status</label>
                    <select class="form-select" id="filter_status" name="filter_status">
                        <option value="">All Status</option>
                        <option value="voted" <?php echo $filter_status === 'voted' ? 'selected' : ''; ?>>Voted</option>
                        <option value="not_voted" <?php echo $filter_status === 'not_voted' ? 'selected' : ''; ?>>Not Voted</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_active" class="form-label">Account Status</label>
                    <select class="form-select" id="filter_active" name="filter_active">
                        <option value="">All Accounts</option>
                        <option value="active" <?php echo $filter_active === 'active' ? 'selected' : ''; ?>>Activated</option>
                        <option value="inactive" <?php echo $filter_active === 'inactive' ? 'selected' : ''; ?>>Not Activated</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                    <a href="students.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h4><?php echo $total_students; ?>/<?php echo $all_total; ?></h4>
                    <p class="mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $voted_students; ?>/<?php echo $all_voted; ?></h4>
                    <p class="mb-0">Voted</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h4><?php echo $not_voted_students; ?>/<?php echo $all_not_voted; ?></h4>
                    <p class="mb-0">Not Voted</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <form method="POST" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="bulk_action" id="bulkActionInput" value="">

        <!-- Bulk Action Toolbar (hidden until rows are selected) -->
        <div id="bulkToolbar" class="card mb-3 border-primary d-none">
            <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
                <span id="selectedCount" class="fw-bold text-primary">0 selected</span>
                <button type="button" class="btn btn-success btn-sm" onclick="submitBulkAction('bulk_activate')">
                    <i class="bi bi-check-circle me-1"></i> Activate Selected
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitBulkAction('bulk_delete')">
                    <i class="bi bi-trash me-1"></i> Delete Selected
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                    <i class="bi bi-x-circle me-1"></i> Clear Selection
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Students (<?php echo $total_students; ?>)</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="studentsTable">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input" title="Select All">
                                </th>
                                <th>Student ID</th>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Grade & Section</th>
                                <th>Voter ID</th>
                                <th>Vote Status</th>
                                <th>Account Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?php echo $student['id']; ?>" class="form-check-input row-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['lrn'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars('Grade ' . $student['grade'] . '-' . $student['section']); ?></td>
                                    <td><code><?php echo htmlspecialchars($student['voter_id_card']); ?></code></td>
                                    <td>
                                        <span class="badge <?php echo $student['has_voted'] ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $student['has_voted'] ? 'VOTED' : 'NOT VOTE'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $student['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $student['is_active'] ? 'ACTIVATED' : 'NOT ACTIVATED'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$student['is_active']): ?>
                                                <form method="POST" class="d-inline" data-no-transition onsubmit="return confirm('Are you sure you want to activate this student account?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-outline-success btn-sm">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" data-no-transition onsubmit="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
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
    </form>
</div>

<script>
(function () {
    var selectAll = document.getElementById('selectAll');
    var bulkToolbar = document.getElementById('bulkToolbar');
    var selectedCount = document.getElementById('selectedCount');

    function getCheckboxes() {
        return document.querySelectorAll('.row-checkbox');
    }

    function updateToolbar() {
        var checked = document.querySelectorAll('.row-checkbox:checked');
        var count = checked.length;
        if (count > 0) {
            bulkToolbar.classList.remove('d-none');
            selectedCount.textContent = count + ' student' + (count !== 1 ? 's' : '') + ' selected';
        } else {
            bulkToolbar.classList.add('d-none');
        }
        // Update select-all state
        var all = getCheckboxes();
        selectAll.indeterminate = count > 0 && count < all.length;
        selectAll.checked = all.length > 0 && count === all.length;
    }

    selectAll.addEventListener('change', function () {
        getCheckboxes().forEach(function (cb) { cb.checked = selectAll.checked; });
        updateToolbar();
    });

    document.getElementById('studentsTable').addEventListener('change', function (e) {
        if (e.target.classList.contains('row-checkbox')) updateToolbar();
    });

    window.submitBulkAction = function (action) {
        var checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length === 0) { alert('Please select at least one student.'); return; }
        var label = action === 'bulk_delete'
            ? 'delete ' + checked.length + ' student(s)? This cannot be undone.'
            : 'activate ' + checked.length + ' student(s)?';
        if (!confirm('Are you sure you want to ' + label)) return;
        document.getElementById('bulkActionInput').value = action;
        document.getElementById('bulkForm').submit();
    };

    window.clearSelection = function () {
        getCheckboxes().forEach(function (cb) { cb.checked = false; });
        selectAll.checked = false;
        selectAll.indeterminate = false;
        bulkToolbar.classList.add('d-none');
    };
})();
</script>

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
