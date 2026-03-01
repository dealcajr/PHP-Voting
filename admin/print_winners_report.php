<?php
require_once __DIR__ . '/../includes/config.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = getDBConnection();

try {
    // fetch winners by position
    $stmt = $db->query(
        "SELECT c.position, c.name, c.party, c.section, COUNT(v.id) as vote_count " .
        "FROM candidates c " .
        "LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position " .
        "WHERE c.is_active = 1 " .
        "GROUP BY c.id, c.position " .
        "ORDER BY c.position ASC, vote_count DESC, c.name ASC"
    );
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $winners = [];
    foreach ($all as $cand) {
        $pos = $cand['position'];
        if (!isset($winners[$pos]) || $cand['vote_count'] > $winners[$pos]['vote_count']) {
            $winners[$pos] = $cand;
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
    <title><?php echo APP_NAME; ?> - Winners Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4 no-print">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Winners Report</h1>
            <button onclick="window.print()" class="btn btn-primary">Print Report</button>
        </div>
    </div>

    <div class="print-container">
        <?php if (!empty($winners)): ?>
            <table class="table table-bordered">
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
                    <?php foreach ($winners as $pos => $cand): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pos); ?></td>
                            <td><?php echo htmlspecialchars($cand['name']); ?></td>
                            <td><?php echo htmlspecialchars($cand['party'] ?? 'Independent'); ?></td>
                            <td><?php echo htmlspecialchars($cand['section']); ?></td>
                            <td><?php echo $cand['vote_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">No results available.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>