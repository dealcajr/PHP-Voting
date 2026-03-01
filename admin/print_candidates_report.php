<?php
require_once __DIR__ . '/../includes/config.php';

// ensure admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = getDBConnection();

// Fetch school info
$school_info = $db->query("SELECT school_name, logo_path FROM school_info LIMIT 1")->fetch();
$school_name = $school_info['school_name'] ?? APP_NAME;
$school_logo = !empty($school_info['logo_path']) ? '../admin/assets/images/logo_1770187955.png' . $school_info['logo_path'] : '../admin/assets/images/logo_1770187955.png';

try {
    $stmt = $db->query(
        "SELECT c.position, c.name, c.party, c.section, c.photo, c.grade, COUNT(v.id) as vote_count " .
        "FROM candidates c " .
        "LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position " .
        "WHERE c.is_active = 1 " .
        "GROUP BY c.id, c.position " .
        "ORDER BY c.position ASC, c.name ASC"
    );
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Prefix photo paths with ../ for correct URL from admin folder
    foreach ($candidates as &$c) {
        if (!empty($c['photo'])) {
            $c['photo'] = '../' . $c['photo'];
        }
    }
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates Report - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; }
        .report-header { text-align: center; margin-bottom: 30px; }
        .report-header img { height: 70px; object-fit: contain; margin-bottom: 10px; }
        .report-header h1 { font-size: 1.5rem; margin-bottom: 4px; color: #1a1a1a; }
        .report-header h2 { font-size: 1rem; color: #555; font-weight: normal; margin-bottom: 0; }
        .table { font-size: 0.9rem; }
        .table th { background: #f0f0f0; }
        .candidate-photo { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 1px solid #ddd; }
        .no-photo { width: 40px; height: 40px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #999; }
        .print-btn { position: fixed; top: 20px; right: 20px; }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn btn-primary no-print print-btn">🖨 Print Report</button>

    <div class="report-header">
        <?php if ($school_logo): ?>
            <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($school_name); ?></h1>
        <h2>SSLG Election Candidates Report</h2>
    </div>

    <div class="Print-container">
        <?php if (!empty($candidates)): ?>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Position</th>
                        <th>Name</th>
                        <th>Party</th>
                        <th>Grade & Section</th>
                        <th>Votes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $cand): ?>
                        <tr>
                            <td class="text-center">
                                <?php if (!empty($cand['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($cand['photo']); ?>" alt="" class="candidate-photo">
                                <?php else: ?>
                                    <div class="no-photo">👤</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($cand['position']); ?></td>
                            <td><?php echo htmlspecialchars($cand['name']); ?></td>
                            <td><?php echo htmlspecialchars($cand['party'] ?? 'Independent'); ?></td>
                            <td><?php echo htmlspecialchars('Grade ' . $cand['grade'] . ' - ' . $cand['section']); ?></td>
                            <td><?php echo $cand['vote_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">No candidates found.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
