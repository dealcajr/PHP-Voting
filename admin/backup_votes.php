<?php
require_once __DIR__ . '/../includes/config.php';

// ensure admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

try {
    $db = getDBConnection();
    // 'votes' table uses created_at for record time
    // instead of raw vote rows we'll export vote counts per candidate
    $stmt = $db->query(
        "SELECT candidate_id, position, COUNT(*) as vote_count " .
        "FROM votes " .
        "GROUP BY candidate_id, position " .
        "ORDER BY position ASC, candidate_id ASC"
    );
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="votes_backup_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    // column headers representing aggregated counts
    fputcsv($output, ['candidate_id', 'position', 'vote_count']);
    foreach ($votes as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}
