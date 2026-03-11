<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

$db = getDBConnection();
$message = '';

// Define position order for consistent sorting
$position_order = [
    'President',
    'Junior High School Vice President',
    'Senior High School Vice President',
    'Secretary',
    'Treasurer',
    'Auditor',
    'Public Information Officer',
    'Peace Officer',
    'Grade 8 Representative',
    'Grade 9 Representative',
    'Grade 10 Representative',
    'Grade 11 Representative',
    'Grade 12 Representative'
];

$default_positions = $position_order;

// Handle form submission for adding/editing candidates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_candidate'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $candidate_id = $_POST['candidate_id'] ?? null;
        $name = sanitizeInput($_POST['name'] ?? '');
        $position = sanitizeInput($_POST['position'] ?? '');
        $party = sanitizeInput($_POST['party'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $grade = sanitizeInput($_POST['grade'] ?? '');
        $manifesto = sanitizeInput($_POST['manifesto'] ?? '');

        // Disallow Grade 12 from being candidates. Normalize digits and check.
        $grade_digits = preg_replace('/\D+/', '', $grade);
        if ($grade_digits !== '' && intval($grade_digits) === 12) {
            $message = '<div class="alert alert-danger">Students in Grade 12 are not eligible to run as candidates.</div>';
        }

        // Handle photo upload
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['photo']['type'];
            $file_size = $_FILES['photo']['size'];

            if (in_array($file_type, $allowed_types) && $file_size <= MAX_FILE_SIZE) {
                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('candidate_') . '.' . $extension;
                $photo_path = UPLOAD_DIR . $filename;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path)) {
                    // Success
                } else {
                    $message = '<div class="alert alert-danger">Failed to upload photo.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Invalid photo file. Must be JPG, PNG, or GIF under 2MB.</div>';
            }
        }

        if (empty($message)) {
            try {
                if ($candidate_id) {
                    // Update existing candidate
                    if ($photo_path) {
                        $stmt = $db->prepare("UPDATE candidates SET name = ?, position = ?, party = ?, section = ?, grade = ?, manifesto = ?, photo = ? WHERE id = ?");
                        $stmt->execute([$name, $position, $party, $section, $grade, $manifesto, $photo_path, $candidate_id]);
                    } else {
                        $stmt = $db->prepare("UPDATE candidates SET name = ?, position = ?, party = ?, section = ?, grade = ?, manifesto = ? WHERE id = ?");
                        $stmt->execute([$name, $position, $party, $section, $grade, $manifesto, $candidate_id]);
                    }
                    logAdminAction('candidate_updated', "Updated candidate: $name");
                    $message = '<div class="alert alert-success">Candidate updated successfully.</div>';
                } else {
                    // Add new candidate
                    $stmt = $db->prepare("INSERT INTO candidates (name, position, party, section, grade, manifesto, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $position, $party, $section, $grade, $manifesto, $photo_path]);
                    logAdminAction('candidate_added', "Added candidate: $name");
                    $message = '<div class="alert alert-success">Candidate added successfully.</div>';
                }
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Handle candidate actions (activate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $action = $_POST['action'];
        $candidate_id = $_POST['candidate_id'] ?? 0;

        try {
            if ($action === 'activate') {
                $db->prepare("UPDATE candidates SET is_active = 1 WHERE id = ?")->execute([$candidate_id]);
                logAdminAction('candidate_activated', "Activated candidate ID: $candidate_id");
                $message = '<div class="alert alert-success">Candidate activated successfully.</div>';
            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM candidates WHERE id = ?")->execute([$candidate_id]);
                logAdminAction('candidate_deleted', "Deleted candidate ID: $candidate_id");
                $message = '<div class="alert alert-success">Candidate deleted successfully.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Get candidates with filters
$filter_position = $_GET['filter_position'] ?? '';
$filter_grade = $_GET['filter_grade'] ?? '';
$filter_party = $_GET['filter_party'] ?? '';
$search_query = $_GET['search'] ?? '';

$query = "SELECT * FROM candidates WHERE 1=1";
$params = [];

// Apply filters
if (!empty($filter_position)) {
    $query .= " AND position = ?";
    $params[] = $filter_position;
}

if (!empty($filter_grade)) {
    $query .= " AND grade = ?";
    $params[] = $filter_grade;
}

if (!empty($filter_party)) {
    $query .= " AND party = ?";
    $params[] = $filter_party;
}

if (!empty($search_query)) {
    $query .= " AND (name LIKE ? OR section LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params = array_merge($params, [$search_param, $search_param]);
}

$query .= " ORDER BY position, name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Sort candidates by position order
usort($candidates, function($a, $b) use ($position_order) {
    $pos_a = array_search($a['position'], $position_order);
    $pos_b = array_search($b['position'], $position_order);
    $pos_a = ($pos_a === false) ? PHP_INT_MAX : $pos_a;
    $pos_b = ($pos_b === false) ? PHP_INT_MAX : $pos_b;
    if ($pos_a === $pos_b) {
        return strcmp($a['name'], $b['name']);
    }
    return $pos_a - $pos_b;
});

// Get all distinct values for filter dropdowns
$all_positions = $db->query("SELECT DISTINCT position FROM candidates WHERE position IS NOT NULL AND position != '' ORDER BY position")->fetchAll(PDO::FETCH_COLUMN);
$all_positions = array_unique(array_merge($default_positions, $all_positions));

// Sort positions according to the defined order (positions not in order list go to the end)
usort($all_positions, function($a, $b) use ($position_order) {
    $pos_a = array_search($a, $position_order);
    $pos_b = array_search($b, $position_order);
    $pos_a = ($pos_a === false) ? PHP_INT_MAX : $pos_a;
    $pos_b = ($pos_b === false) ? PHP_INT_MAX : $pos_b;
    return $pos_a - $pos_b;
});

$all_grades = $db->query("SELECT DISTINCT grade FROM candidates ORDER BY CAST(grade AS UNSIGNED)")->fetchAll(PDO::FETCH_COLUMN);

$all_parties = $db->query("SELECT DISTINCT party FROM candidates WHERE party IS NOT NULL AND party != '' ORDER BY party")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <h1 class="mb-4">Candidate Management</h1>

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
                    <input type="text" class="form-control" id="search" name="search" placeholder="Candidate name" value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-2">
                    <label for="filter_position" class="form-label">Position</label>
                    <select class="form-select" id="filter_position" name="filter_position">
                        <option value="">All Positions</option>
                        <?php foreach ($all_positions as $position): ?>
                            <option value="<?php echo htmlspecialchars($position); ?>" <?php echo $filter_position === $position ? 'selected' : ''; ?>><?php echo htmlspecialchars($position); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <label for="filter_party" class="form-label">Party</label>
                    <select class="form-select" id="filter_party" name="filter_party">
                        <option value="">All Parties</option>
                        <option value="Independent" <?php echo $filter_party === 'Independent' ? 'selected' : ''; ?>>Independent</option>
                        <?php foreach ($all_parties as $party): ?>
                            <option value="<?php echo htmlspecialchars($party); ?>" <?php echo $filter_party === $party ? 'selected' : ''; ?>><?php echo htmlspecialchars($party); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                    <a href="candidates.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Candidates Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Candidates (<?php echo count($candidates); ?>)</h4>
            <button type="button" class="btn btn-primary btn-sm" style="font-size:.7rem;padding:.15rem .9rem;width:9.2rem;" data-bs-toggle="modal" data-bs-target="#candidateModal" title="Making New Candidate">Add New Candidate</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Party</th>
                            <th>Grade & Section</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td>
                                    <?php if ($candidate['photo']): ?>
                                        <img src="../<?php echo htmlspecialchars($candidate['photo']); ?>" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light text-center" style="width: 50px; height: 50px; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                            <span class="text-muted">N/A</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['position']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?></td>
                                <td><?php echo htmlspecialchars(($candidate['grade'] ? 'Grade ' . $candidate['grade'] : 'N/A') . ($candidate['section'] ? '-' . $candidate['section'] : '')); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-sm edit-candidate-btn" data-candidate='<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES); ?>'>Edit</button>
                                        <?php if (!$candidate['is_active']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-outline-success btn-sm">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this candidate?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
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

<!-- Candidate Modal -->
<div class="modal fade" id="candidateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Candidate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="candidateForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="save_candidate" value="1">
                    <input type="hidden" name="candidate_id" id="candidate_id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="position" class="form-label">Position *</label>
                                <select class="form-control" id="position" name="position" required>
                                    <option value="">Select Position</option>
                                    <?php foreach ($all_positions as $pos): ?>
                                        <option value="<?php echo htmlspecialchars($pos); ?>"><?php echo htmlspecialchars($pos); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="party" class="form-label">Party</label>
                                <input type="text" class="form-control" id="party" name="party"
                                       list="partyList"
                                       placeholder="Select existing or type a new party name"
                                       autocomplete="off">
                                <datalist id="partyList">
                                    <option value="Independent">
                                    <?php foreach ($all_parties as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small class="text-muted">Choose an existing party or type a new one. Leave empty for Independent.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="grade" class="form-label">Grade *</label>
                                <input type="text" class="form-control" id="grade" name="grade" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="manifesto" class="form-label">Manifesto</label>
                        <textarea class="form-control" id="manifesto" name="manifesto" rows="3" placeholder="Candidate's platform and promises..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="photo" class="form-label">Photo</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        <small class="text-muted">Accepted formats: JPG, PNG, GIF. Max size: 2MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Candidate</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

<script>
    // run after bootstrap bundle loads
    document.addEventListener('DOMContentLoaded', function () {
        const candidateModal = new bootstrap.Modal(document.getElementById('candidateModal'));
        const modalTitle = document.getElementById('modalTitle');
        const candidateForm = document.getElementById('candidateForm');

        function editCandidate(candidate) {
            modalTitle.textContent = 'Edit Candidate';
            candidateForm.reset();
            document.getElementById('candidate_id').value = candidate.id;
            document.getElementById('name').value = candidate.name || '';
            document.getElementById('position').value = candidate.position || '';
            document.getElementById('party').value = candidate.party || '';
            document.getElementById('grade').value = candidate.grade || '';
            document.getElementById('section').value = candidate.section || '';
            document.getElementById('manifesto').value = candidate.manifesto || '';
            candidateModal.show();
        }

        // Delegate edit button clicks
        document.addEventListener('click', function (e) {
            const btn = e.target.closest && e.target.closest('.edit-candidate-btn');
            if (!btn) return;
            const data = btn.getAttribute('data-candidate');
            if (!data) return;
            try {
                const candidate = JSON.parse(data);
                editCandidate(candidate);
            } catch (err) {
                console.error('Failed to parse candidate data', err);
            }
        });

        document.getElementById('candidateModal').addEventListener('hidden.bs.modal', function () {
            modalTitle.textContent = 'Add New Candidate';
            candidateForm.reset();
            document.getElementById('candidate_id').value = '';
        });

        // validation logic copied here too
        candidateForm.addEventListener('submit', function (e) {
            const gradeVal = document.getElementById('grade').value || '';
            const digits = gradeVal.replace(/\D+/g, '');
            if (digits && parseInt(digits, 10) === 12) {
                e.preventDefault();
                alert('Students in Grade 12 are not eligible to run as candidates.');
                return false;
            }
        });

        (function() {
            const gradeInput = document.getElementById('grade');
            if (!gradeInput) return;
            let lastWas12 = false;
            gradeInput.addEventListener('input', function () {
                const digits = (this.value || '').replace(/\D+/g, '');
                const is12 = digits !== '' && parseInt(digits, 10) === 12;
                if (is12 && !lastWas12) {
                    lastWas12 = true;
                    alert('Students in Grade 12 are not eligible to run as candidates.');
                    this.value = '';
                } else if (!is12) {
                    lastWas12 = false;
                }
            });
        })();
    });
</script>
