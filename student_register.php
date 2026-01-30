<?php
require_once 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: vote.php');
    }
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $grade = sanitizeInput($_POST['grade'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $track = sanitizeInput($_POST['track'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($grade) || empty($section)) {
            $message = '<div class="alert alert-danger">Name, grade, and section are required.</div>';
        } elseif (($grade == '11' || $grade == '12') && empty($track)) {
            $message = '<div class="alert alert-danger">Track is required for Senior High School students (Grades 11-12).</div>';
        } else {
            try {
                $db = getDBConnection();

                // Generate unique student ID automatically
                $student_id = generateStudentID();

                // Generate a default password for the student
                $default_password = 'password123'; // You might want to generate a random password
                $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
                $voter_id_card = 'VOTER-' . strtoupper(substr(md5(uniqid()), 0, 8));

                $stmt = $db->prepare("INSERT INTO users (student_id, password_hash, role, first_name, last_name, grade, section, track, voter_id_card, is_active) VALUES (?, ?, 'voter', ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$student_id, $password_hash, $first_name, $last_name, $grade, $section, $track, $voter_id_card]);

                $message = '<div class="alert alert-success">Registration successful! Your Student ID is: <strong>' . $student_id . '</strong>. Your default password is: <strong>' . $default_password . '</strong>. Your account is pending approval by an administrator. You will receive your Voter ID Card once approved. Please check back later.</div>';
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Registration failed: ' . $e->getMessage() . '</div>';
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
    <title><?php echo $school_name ?? APP_NAME; ?> - Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-branding">
                <h1><?php echo htmlspecialchars($school_name ?? APP_NAME); ?></h1>
                <p class="lead">Student Registration</p>
            </div>
            <div class="login-form">
                <h2 class="text-center mb-4">Register for Voting</h2>
                <p class="text-center text-muted mb-4">Create your account to participate in school elections.</p>

                <?php echo $message; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Student ID will be automatically generated</strong> for you upon successful registration.
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control form-control-lg" id="first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control form-control-lg" id="last_name" name="last_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="grade" class="form-label">Grade</label>
                            <input type="text" class="form-control" id="grade" name="grade" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="section" class="form-label">Section</label>
                            <input type="text" class="form-control" id="section" name="section" required>
                        </div>
                    </div>
                    <div class="mb-3" id="track-container" style="display: none;">
                        <label for="track" class="form-label">Track (Senior High School)</label>
                        <select class="form-control" id="track" name="track">
                            <option value="">Select Track</option>
                            <option value="Academic">Academic</option>
                            <option value="Technical-Vocational-Livelihood">Technical-Vocational-Livelihood</option>
                            <option value="Sports">Sports</option>
                            <option value="Arts and Design">Arts and Design</option>
                        </select>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p class="text-muted small">Note: Your account must be approved by an administrator before you can vote.</p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grade change handler to show/hide track field
        document.getElementById('grade').addEventListener('input', function() {
            const grade = this.value.trim();
            const trackContainer = document.getElementById('track-container');
            const trackSelect = document.getElementById('track');

            if (grade === '11' || grade === '12') {
                trackContainer.style.display = 'block';
                trackSelect.required = true;
            } else {
                trackContainer.style.display = 'none';
                trackSelect.required = false;
                trackSelect.value = '';
            }
        });

        // Trigger the grade change handler on page load in case of form repopulation
        document.addEventListener('DOMContentLoaded', function() {
            const gradeInput = document.getElementById('grade');
            if (gradeInput.value) {
                gradeInput.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>
