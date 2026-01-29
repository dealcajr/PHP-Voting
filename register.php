<?php
require_once 'includes/config.php';
requireRole('admin');

$message = '';

function generateNextStudentID($db) {
    $stmt = $db->query("SELECT student_id FROM users WHERE student_id LIKE 'STU%' ORDER BY CAST(SUBSTRING(student_id, 4) AS UNSIGNED) DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = (int)substr($last, 3);
        $next_num = $num + 1;
    } else {
        $next_num = 1;
    }
    return 'STU' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

$db = getDBConnection();
$generated_student_id = generateNextStudentID($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $student_id = $generated_student_id; // Use the auto-generated ID
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $grade = sanitizeInput($_POST['grade'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($student_id) || empty($first_name) || empty($last_name) || empty($grade) || empty($section) || empty($password)) {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif ($password !== $confirm_password) {
            $message = '<div class="alert alert-danger">Passwords do not match.</div>';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $message = '<div class="alert alert-danger">Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.</div>';
        } else {
            try {
                $db = getDBConnection();

                // Check if student_id already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ?");
                $stmt->execute([$student_id]);
                if ($stmt->fetch()) {
                    $message = '<div class="alert alert-danger">Student ID already exists.</div>';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $voter_id_card = 'VOTER-' . strtoupper(substr(md5(uniqid()), 0, 8));

                    $stmt = $db->prepare("INSERT INTO users (student_id, password_hash, role, first_name, last_name, grade, section, voter_id_card) VALUES (?, ?, 'voter', ?, ?, ?, ?, ?)");
                    $stmt->execute([$student_id, $password_hash, $first_name, $last_name, $grade, $section, $voter_id_card]);

                    logAdminAction('student_registered', "Registered new student: $student_id");
                    $message = '<div class="alert alert-success">Student registered successfully. Student ID: ' . $student_id . ', Voter ID: ' . $voter_id_card . '</div>';
                }
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Get school name
$db = getDBConnection();
$stmt = $db->query("SELECT school_name FROM school_info LIMIT 1");
$school_name = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Register Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-center">Register New Student</h2>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="grade" class="form-label">Grade</label>
                                <input type="text" class="form-control" id="grade" name="grade" required>
                            </div>
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register Student</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <a href="admin/index.php" class="btn btn-secondary">Back to Admin Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
