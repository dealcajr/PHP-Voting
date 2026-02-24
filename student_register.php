<?php
require_once 'includes/config.php';

// Block registration if election is open
if (isElectionOpen()) {
    header('Location: election_blocked.php');
    exit();
}

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
$registration_success = false;
$student_details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $lrn = sanitizeInput($_POST['lrn'] ?? '');
        $grade = sanitizeInput($_POST['grade'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $track = sanitizeInput($_POST['track'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($lrn) || empty($grade) || empty($section)) {
            $message = '<div class="alert alert-danger">Name, LRN, grade, and section are required.</div>';
        } elseif (!preg_match('/^\d{12}$/', $lrn)) {
            $message = '<div class="alert alert-danger">LRN must be exactly 12 digits.</div>';
        } elseif (($grade == '11' || $grade == '12') && empty($track)) {

            $message = '<div class="alert alert-danger">Track is required for Senior High School students (Grades 11-12).</div>';
        } else {
            try {
                $db = getDBConnection();

                // Check if LRN already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE lrn = ?");
                $stmt->execute([$lrn]);
                if ($stmt->fetch()) {
                    $message = '<div class="alert alert-danger">A student with this LRN already exists in the system. Please use a different LRN or contact an administrator.</div>';
                } else {
                    // Check if student with same profile (name, grade, section) already exists
                    $stmt = $db->prepare("SELECT id FROM users WHERE first_name = ? AND last_name = ? AND grade = ? AND section = ?");
                    $stmt->execute([$first_name, $last_name, $grade, $section]);
                    if ($stmt->fetch()) {
                        $message = '<div class="alert alert-danger">A student with the same name, grade, and section already exists. Please verify your information or contact an administrator.</div>';
                    } else {
                        // Generate unique student ID automatically
                        $student_id = generateStudentID();

                        // Generate a 6-character random token instead of password
                        $token = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                        $password_hash = password_hash($token, PASSWORD_DEFAULT);
                        $voter_id_card = 'VOTER-' . strtoupper(substr(md5(uniqid()), 0, 8));

                        $stmt = $db->prepare("INSERT INTO users (student_id, lrn, password_hash, role, first_name, last_name, grade, section, track, voter_id_card, is_active) VALUES (?, ?, ?, 'voter', ?, ?, ?, ?, ?, ?, 0)");
                        $stmt->execute([$student_id, $lrn, $password_hash, $first_name, $last_name, $grade, $section, $track, $voter_id_card]);

                        // Store details for success modal
                        $registration_success = true;
                        $student_details = [
                            'student_id' => $student_id,
                            'token' => $token,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'lrn' => $lrn,
                            'grade' => $grade,
                            'section' => $section,
                            'voter_id_card' => $voter_id_card
                        ];
                    }
                }
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

// Get theme settings
$election = $db->query("SELECT theme_color, logo_path FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
$theme_color = $election['theme_color'] ?? '#343a40';
$logo_path = $election['logo_path'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name ?? APP_NAME; ?> - Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root { <?php echo getThemeCSSDeclaration(); ?> }
        .login-branding {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-branding img {
            max-height: 80px;
            margin-bottom: 1rem;
        }
        .login-branding h1 {
            color: var(--theme-color);
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .btn-primary {
            background-color: var(--theme-color);
            border-color: var(--theme-color);
        }
        .btn-primary:hover {
            background-color: var(--theme-color);
            border-color: var(--theme-color);
            filter: brightness(0.9);
        }
        .modal-header {
            background-color: var(--theme-color);
        }
        .alert-info {
            border-left: 4px solid var(--theme-color);
        }
    </style>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-branding">
                <?php if ($logo_path && file_exists($logo_path)): ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="School Logo">
                <?php endif; ?>
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
                    <div class="mb-3">
                        <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                        <input type="text" class="form-control form-control-lg" id="lrn" name="lrn" maxlength="12" pattern="\d{12}" title="LRN must be exactly 12 digits" required>
                        <small class="text-muted">Enter exactly 12 digits</small>
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
                            <option value="Technical-Vocational-Livelihood">(TVL) Technical Vocational Livelihood</option>                       
                        </select>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="button" class="btn btn-primary btn-lg" id="registerBtn" data-bs-toggle="modal" data-bs-target="#confirmModal">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-success text-white border-0">
                    <div class="col">
                        <h5 class="modal-title" id="successModalLabel">
                            <i class="fas fa-check-circle me-2"></i>Registration Successful!
                        </h5>
                    </div>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Your account has been created successfully!</strong> Your account is pending approval by an administrator.
                    </div>

                    <!-- Student Details Card -->
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Your Registration Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <label class="text-muted small">Full Name</label>
                                        <p class="fs-6 fw-bold mb-0" id="successName"></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <label class="text-muted small">Student ID</label>
                                        <p class="fs-6 fw-bold mb-0" id="successStudentId"></p>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <label class="text-muted small">LRN (Learner Reference Number)</label>
                                        <p class="fs-6 fw-bold mb-0" id="successLRN"></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <label class="text-muted small">Voter ID Card</label>
                                        <p class="fs-6 fw-bold mb-0 text-primary" id="successVoterId"></p>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <label class="text-muted small">Grade</label>
                                        <p class="fs-6 fw-bold mb-0" id="successGrade"></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <label class="text-muted small">Section</label>
                                        <p class="fs-6 fw-bold mb-0" id="successSection"></p>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="detail-item bg-light p-3 rounded border border-warning">
                                <label class="text-muted small mb-2 d-block">
                                    <i class="fas fa-key me-2 text-warning"></i>Your Login Token
                                </label>
                                <p class="fs-5 fw-bold text-center mb-0 text-warning font-monospace" id="successToken" style="letter-spacing: 2px;"></p>
                                <small class="text-muted d-block text-center mt-2">Save this token - you'll need it to log in</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Your account is pending admin approval. You will receive your Voter ID Card once approved. Bookmark this page or save your details for future reference.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-primary" onclick="window.location.href='student_register.php'">
                        <i class="fas fa-home me-1"></i>Register New Student
                    </button>
                    <button type="button" class="btn btn-success" onclick="printDetails()">
                        <i class="fas fa-print me-2"></i>Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Your Registration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="lead">Please review your information before confirming:</p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td class="fw-bold">First Name:</td>
                                    <td id="confirmFirstName"></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Last Name:</td>
                                    <td id="confirmLastName"></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">LRN:</td>
                                    <td id="confirmLRN"></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Grade:</td>
                                    <td id="confirmGrade"></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Section:</td>
                                    <td id="confirmSection"></td>
                                </tr>
                                <tr id="trackRow" style="display: none;">
                                    <td class="fw-bold">Track:</td>
                                    <td id="confirmTrack"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Your account will be pending approval by an administrator after registration.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmSubmitBtn">Confirm & Register</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // PHP data for success modal
        const registrationSuccess = <?php echo $registration_success ? 'true' : 'false'; ?>;
        const studentDetails = <?php echo json_encode($student_details); ?>;

        // Show success modal if registration was successful
        document.addEventListener('DOMContentLoaded', function() {
            if (registrationSuccess) {
                // Populate success modal with student details
                document.getElementById('successName').textContent = studentDetails.first_name + ' ' + studentDetails.last_name;
                document.getElementById('successStudentId').textContent = studentDetails.student_id;
                document.getElementById('successLRN').textContent = studentDetails.lrn;
                document.getElementById('successVoterId').textContent = studentDetails.voter_id_card;
                document.getElementById('successGrade').textContent = studentDetails.grade;
                document.getElementById('successSection').textContent = studentDetails.section;
                document.getElementById('successToken').textContent = studentDetails.token;

                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            }

            // Grade change handler for track field
            const gradeInput = document.getElementById('grade');
            if (gradeInput) {
                gradeInput.addEventListener('input', function() {
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

                if (gradeInput.value) {
                    gradeInput.dispatchEvent(new Event('input'));
                }
            }
        });

        // Print function for student details
        function printDetails() {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Student Registration Details</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h1 { color: #28a745; margin: 0; }
                        .details { border: 2px solid #28a745; padding: 20px; border-radius: 8px; }
                        .detail-row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
                        .detail-label { font-weight: bold; color: #555; }
                        .detail-value { color: #333; }
                        .token-section { background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
                        .token-label { color: #666; font-size: 12px; }
                        .token-value { font-size: 20px; font-weight: bold; color: #ffc107; letter-spacing: 2px; text-align: center; font-family: monospace; }
                        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #999; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>✓ Registration Successful</h1>
                            <p>Your Student Registration Details</p>
                        </div>
                        <div class="details">
                            <div class="detail-row">
                                <span class="detail-label">Full Name:</span>
                                <span class="detail-value">${studentDetails.first_name} ${studentDetails.last_name}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Student ID:</span>
                                <span class="detail-value">${studentDetails.student_id}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">LRN:</span>
                                <span class="detail-value">${studentDetails.lrn}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Voter ID:</span>
                                <span class="detail-value">${studentDetails.voter_id_card}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Grade:</span>
                                <span class="detail-value">${studentDetails.grade}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Section:</span>
                                <span class="detail-value">${studentDetails.section}</span>
                            </div>
                        </div>
                        <div class="token-section">
                            <div class="token-label">YOUR LOGIN TOKEN (Save This)</div>
                            <div class="token-value">${studentDetails.token}</div>
                        </div>
                        <div class="footer">
                            <p>Your account is pending administrator approval.</p>
                            <p>Please keep this document for your records.</p>
                        </div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        const registrationForm = document.querySelector('form[method="POST"]');
        if (registrationForm && !registrationSuccess) {
            const registerBtn = document.getElementById('registerBtn');
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');

            // Show modal and populate data when Register button is clicked
            registerBtn.addEventListener('click', function(e) {
                e.preventDefault();

                // Validate form before showing modal
                if (!registrationForm.checkValidity()) {
                    registrationForm.reportValidity();
                    return;
                }

                // Get form values
                const firstName = document.getElementById('first_name').value.trim();
                const lastName = document.getElementById('last_name').value.trim();
                const lrn = document.getElementById('lrn').value.trim();
                const grade = document.getElementById('grade').value.trim();
                const section = document.getElementById('section').value.trim();
                const track = document.getElementById('track').value.trim();

                // Populate modal with form data
                document.getElementById('confirmFirstName').textContent = firstName;
                document.getElementById('confirmLastName').textContent = lastName;
                document.getElementById('confirmLRN').textContent = lrn;
                document.getElementById('confirmGrade').textContent = grade;
                document.getElementById('confirmSection').textContent = section;

                // Show/hide track row based on grade
                const trackRow = document.getElementById('trackRow');
                if (grade === '11' || grade === '12') {
                    trackRow.style.display = 'table-row';
                    document.getElementById('confirmTrack').textContent = track || 'Not selected';
                } else {
                    trackRow.style.display = 'none';
                }

                // Show modal
                confirmModal.show();
            });

            // Submit form when Confirm button is clicked
            confirmSubmitBtn.addEventListener('click', function() {
                registrationForm.submit();
            });
        }
    </script>
</body>
</html>
