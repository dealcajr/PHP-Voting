<?php
require_once '../includes/config.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

try {
    $db = getDBConnection();

    // Get school info using prepared statement
    $schoolStmt = $db->prepare("SELECT * FROM school_info ORDER BY id DESC LIMIT 1");
    $schoolStmt->execute();
    $school = $schoolStmt->fetch();

    // Get students with voter ID cards using prepared statement
    $studentsStmt = $db->prepare("
        SELECT id, first_name, last_name, student_id, grade, section, voter_id_card
        FROM users 
        WHERE role = 'voter' AND voter_id_card IS NOT NULL AND voter_id_card != ''
        ORDER BY grade ASC, section ASC, last_name ASC, first_name ASC
    ");
    $studentsStmt->execute();
    $students = $studentsStmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching data: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Voter ID Cards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-container, .print-container * {
                visibility: visible;
            }
            .print-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .id-card {
                page-break-inside: avoid;
                margin-bottom: 20px;
            }
        }

        .id-card {
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 20px;
            margin: 10px;
            width: 300px;
            display: inline-block;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .school-header {
            text-align: center;
            border-bottom: 1px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .student-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f8f9fa;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #6c757d;
            font-weight: bold;
        }

        .voter-id {
            font-family: monospace;
            font-size: 14px;
            font-weight: bold;
            color: #007bff;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 3px;
            text-align: center;
        }

        .no-students-message {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }
    </style>
</head>
<body>
    <div class="no-print container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Voter ID Cards</h1>
            <div>
                <button onclick="window.print()" class="btn btn-primary">Print All Cards</button>
                <a href="students.php" class="btn btn-secondary">Back to Students</a>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Total Cards:</strong> <?php echo count($students); ?><br>
            <small>Cards will be printed with one per page for easy cutting.</small>
        </div>
    </div>

    <div class="print-container">
        <?php foreach ($students as $student): ?>
            <div class="id-card">
                <div class="school-header">
                    <h5><?php echo htmlspecialchars($school['school_name'] ?? APP_NAME); ?></h5>
                    <small><?php echo htmlspecialchars($school['school_address'] ?? ''); ?></small>
                </div>

                <div class="text-center">
                    <div class="student-photo">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                    </div>

                    <h6><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                    <p class="mb-1"><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                    <p class="mb-1"><strong>Grade & Section:</strong> <?php echo htmlspecialchars('Grade ' . $student['grade'] . '-' . $student['section']); ?></p>

                    <div class="voter-id">
                        <?php echo htmlspecialchars($student['voter_id_card']); ?>
                    </div>

                    <small class="text-muted mt-2 d-block">SSLG Voter ID Card</small>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($students)): ?>
            <div class="alert alert-warning text-center">
                No voter ID cards have been generated yet. Please generate voter IDs for students first.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
