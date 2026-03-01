<?php
require_once __DIR__ . '/../includes/config.php';

// ensure admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = getDBConnection();

try {
    $stmt = $db->query(
        "SELECT c.position, c.name, c.party, c.section, c.photo, COUNT(v.id) as vote_count " .
        "FROM candidates c " .
        "LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position " .
        "WHERE c.is_active = 1 " .
        "GROUP BY c.id, c.position " .
        "ORDER BY c.position ASC, c.name ASC"
    );
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Candidates Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4 no-print">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Candidates Report</h1>
            <button onclick="window.print()" class="btn btn-primary">Print Report</button>
        </div>
    </div>

    <div class="print-container">
        <?php if (!empty($candidates)): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Name</th>
                        <th>Party</th>
                        <th>Section</th>
                        <th>Votes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $cand): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cand['position']); ?></td>
                            <td><?php echo htmlspecialchars($cand['name']); ?></td>
                            <td><?php echo htmlspecialchars($cand['party'] ?? 'Independent'); ?></td>
                            <td><?php echo htmlspecialchars($cand['section']); ?></td>
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