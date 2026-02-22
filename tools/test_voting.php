<?php
require_once 'includes/config.php';

// Test voting when election is closed
echo "\n--- Testing Voting When Election Closed ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'submit_vote' => '1',
    'votes' => ['President' => '1'],
    'confirmation' => 'CONFIRM'
];

// Simulate closed election
$db = getDBConnection();
$db->exec("UPDATE election_settings SET is_open = 0 WHERE id = 1");

ob_start();
include 'vote.php';
$content = ob_get_clean();

if (strpos($content, 'The election is currently closed') !== false) {
    echo "✅ Voting correctly blocked when election is closed\n";
} else {
    echo "❌ Voting not blocked when election is closed\n";
}

// Reset election status
$db->exec("UPDATE election_settings SET is_open = 1 WHERE id = 1");

// Test voting with invalid CSRF token
echo "\n--- Testing Voting with Invalid CSRF Token ---\n";
$_POST = [
    'csrf_token' => 'invalid_token',
    'submit_vote' => '1',
    'votes' => ['President' => '1'],
    'confirmation' => 'CONFIRM'
];

ob_start();
include 'vote.php';
$content = ob_get_clean();

if (strpos($content, 'Invalid request') !== false) {
    echo "✅ Invalid CSRF token correctly rejected\n";
} else {
    echo "❌ Invalid CSRF token not rejected\n";
}

// Test voting without confirmation
echo "\n--- Testing Voting Without Confirmation ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'submit_vote' => '1',
    'votes' => ['President' => '1'],
    'confirmation' => 'wrong'
];

ob_start();
include 'vote.php';
$content = ob_get_clean();

if (strpos($content, 'Please confirm your vote') !== false) {
    echo "✅ Voting correctly requires confirmation\n";
} else {
    echo "❌ Voting does not require confirmation\n";
}

// Test voting without selecting candidate
echo "\n--- Testing Voting Without Selecting Candidate ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'submit_vote' => '1',
    'votes' => [],
    'confirmation' => 'CONFIRM'
];

ob_start();
include 'vote.php';
$content = ob_get_clean();

if (strpos($content, 'Please select at least one candidate') !== false) {
    echo "✅ Voting correctly requires candidate selection\n";
} else {
    echo "❌ Voting does not require candidate selection\n";
}

echo "\n🎉 Voting tests completed!\n";
?>
