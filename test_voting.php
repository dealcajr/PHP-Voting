<?php
require_once 'includes/config.php';
require_once 'includes/config.php';

echo "Testing SSLG Voting System Voting Process...\n\n";

// Simulate student login
$_SESSION['user_id'] = 2; // STU001
$_SESSION['role'] = 'voter';
$_SESSION['student_id'] = 'STU001';

// Test voting when election is closed
echo "--- Testing Voting When Election Closed ---\n";
$election = $db->query("SELECT * FROM election_settings LIMIT 1")->fetch();
if (!$election['is_open']) {
    ob_start();
    include 'vote.php';
    $content = ob_get_clean();

    if (strpos($content, 'The election is currently closed') !== false) {
        echo "✅ Voting properly blocked when election closed\n";
    } else {
        echo "❌ Voting not blocked when election closed\n";
    }
}

// Open election for testing
$db->exec("UPDATE election_settings SET is_open = 1");

// Test voting access
echo "\n--- Testing Voting Access ---\n";
ob_start();
include 'vote.php';
$content = ob_get_clean();

if (strpos($content, 'Cast Your Vote') !== false) {
    echo "✅ Voting page accessible\n";
} else {
    echo "❌ Voting page not accessible\n";
}

// Test vote submission
echo "\n--- Testing Vote Submission ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'submit_vote' => '1',
    'votes' => [
        'President' => '1' // Assuming candidate ID 1 exists
    ],
    'confirmation' => 'CONFIRM'
];

ob_start();
include 'vote.php';
$content = ob_get_clean();

$vote_count = $db->query("SELECT COUNT(*) FROM votes WHERE voter_id = 2")->fetchColumn();
if ($vote_count > 0) {
    echo "✅ Vote submission successful\n";
} else {
    echo "❌ Vote submission failed\n";
}

// Test double voting prevention
echo "\n--- Testing Double Voting Prevention ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'submit_vote' => '1',
    'votes' => [
        'President' => '1'
    ],
    'confirmation' => 'CONFIRM'
];

ob_start();
include 'vote.php';
$content = ob_get_clean();

$vote_count_after = $db->query("SELECT COUNT(*) FROM votes WHERE voter_id = 2")->fetchColumn();
if ($vote_count_after === $vote_count) {
    echo "✅ Double voting prevented\n";
} else {
    echo "❌ Double voting not prevented\n";
}

// Close election
$db->exec("UPDATE election_settings SET is_open = 0");

echo "\n🎉 Voting process tests completed!\n";
?>
