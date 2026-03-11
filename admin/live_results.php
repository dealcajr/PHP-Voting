<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

$db = getDBConnection();

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

// Fetch all active candidates with vote counts, grouped by position
$stmt = $db->query("
    SELECT c.position, c.name, c.party, c.photo, c.grade, c.section,
           COUNT(v.id) AS vote_count
    FROM candidates c
    LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position
    WHERE c.is_active = 1
    GROUP BY c.id, c.position
    ORDER BY c.position ASC, vote_count DESC, c.name ASC
");
$all_candidates = $stmt->fetchAll();

// Group by position
$results = [];
foreach ($all_candidates as $row) {
    $results[$row['position']][] = $row;
}

// Sort results by position order
uksort($results, function($a, $b) use ($position_order) {
    $pos_a = array_search($a, $position_order);
    $pos_b = array_search($b, $position_order);
    $pos_a = ($pos_a === false) ? PHP_INT_MAX : $pos_a;
    $pos_b = ($pos_b === false) ? PHP_INT_MAX : $pos_b;
    return $pos_a - $pos_b;
});

// Total voters who have voted
$total_voted = (int) $db->query("SELECT COUNT(DISTINCT voter_id) FROM votes")->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'total_voted' => $total_voted,
    'results'     => $results,
]);
