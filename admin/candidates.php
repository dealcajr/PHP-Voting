<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

$db = getDBConnection();
$message = '';

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

// Handle candidate actions (activate/deactivate/delete)
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
            } elseif ($action === 'deactivate') {
                $db->prepare("UPDATE candidates SET is_active = 0 WHERE id = ?")->execute([$candidate_id]);
                logAdminAction('candidate_deactivated', "Deactivated candidate ID: $candidate_id");
                $message = '<div class="alert alert-success">Candidate deactivated successfully.</div>';
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

// Get candidates
$candidates = $db->query("SELECT * FROM candidates ORDER BY position, name")->fetchAll();

// Get positions for dropdown
$positions = $db->query("SELECT DISTINCT position FROM candidates WHERE position IS NOT NULL AND position != '' ORDER BY position")->fetchAll(PDO::FETCH_COLUMN);
$default_positions = ['President', 'Vice President', 'Secretary', 'Treasurer', 'Auditor', 'PRO'];
$positions = array_unique(array_merge($default_positions, $positions));

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Candidate Management</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#candidateModal">Add New Candidate</button>
    </div>

    <?php echo $message; ?>

    <!-- Candidates Table -->
    <div class="card">
        <div class="card-header">
            <h4>Candidates (<?php echo count($candidates); ?>)</h4>
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
                            <th>Status</th>
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
                                <td><?php echo htmlspecialchars('Grade ' . $candidate['grade'] . '-' . $candidate['section']); ?></td>
                                <td>
                                    <span class="badge <?php echo $candidate['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="editCandidate(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">Edit</button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $candidate['is_active'] ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $candidate['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                                <?php echo $candidate['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
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
                                    <?php foreach ($positions as $pos): ?>
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
                                <input type="text" class="form-control" id="party" name="party" placeholder="Leave empty for Independent">
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
                                <label for="section" class="form-label">Section *</label>
                                <input type="text" class="form-control" id="section" name="section" required>
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

<script>
    const candidateModal = new bootstrap.Modal(document.getElementById('candidateModal'));
    const modalTitle = document.getElementById('modalTitle');
    const candidateForm = document.getElementById('candidateForm');

    function editCandidate(candidate) {
        modalTitle.textContent = 'Edit Candidate';
        candidateForm.reset();
        document.getElementById('candidate_id').value = candidate.id;
        document.getElementById('name').value = candidate.name;
        document.getElementById('position').value = candidate.position;
        document.getElementById('party').value = candidate.party;
        document.getElementById('grade').value = candidate.grade;
        document.getElementById('section').value = candidate.section;
        document.getElementById('manifesto').value = candidate.manifesto;
        candidateModal.show();
    }

    document.getElementById('candidateModal').addEventListener('hidden.bs.modal', function () {
        modalTitle.textContent = 'Add New Candidate';
        candidateForm.reset();
        document.getElementById('candidate_id').value = '';
    });
</script>

<?php include '../includes/admin_footer.php'; ?>
