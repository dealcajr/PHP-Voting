<?php
require_once 'includes/config.php';

echo "Testing SSLG Voting System Admin Functions...\n\n";

// Simulate admin login
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['student_id'] = 'ADMIN001';

// Test school info update
echo "--- Testing School Info Update ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'save_school' => '1',
    'school_name' => 'Test School',
    'school_address' => '123 Test Street',
    'contact_email' => 'test@school.edu'
];

ob_start();
include 'admin/school.php';
$content = ob_get_clean();

$db = getDBConnection();
$school = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();
if ($school['school_name'] === 'Test School') {
    echo "✅ School info update successful\n";
} else {
    echo "❌ School info update failed\n";
}

// Test candidate addition
echo "\n--- Testing Candidate Addition ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'save_candidate' => '1',
    'name' => 'Test Candidate',
    'position' => 'President',
    'party' => 'Test Party',
    'section' => 'Grade 12-A',
    'grade' => '12',
    'manifesto' => 'Test manifesto'
];

ob_start();
include 'admin/candidates.php';
$content = ob_get_clean();

$candidate = $db->query("SELECT * FROM candidates WHERE name = 'Test Candidate' LIMIT 1")->fetch();
if ($candidate) {
    echo "✅ Candidate addition successful\n";
} else {
    echo "❌ Candidate addition failed\n";
}

// Test election token generation
echo "\n--- Testing Election Token Generation ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'action' => 'generate_token'
];

ob_start();
include 'admin/election.php';
$content = ob_get_clean();

$election = $db->query("SELECT * FROM election_settings LIMIT 1")->fetch();
if (!empty($election['election_token'])) {
    echo "✅ Election token generation successful\n";
} else {
    echo "❌ Election token generation failed\n";
}

// Test election opening
echo "\n--- Testing Election Opening ---\n";
$_POST = [
    'csrf_token' => generateCSRFToken(),
    'action' => 'open_election'
];

ob_start();
include 'admin/election.php';
$content = ob_get_clean();

$election = $db->query("SELECT * FROM election_settings LIMIT 1")->fetch();
if ($election['is_open']) {
    echo "✅ Election opening successful\n";
} else {
    echo "❌ Election opening failed\n";
}

echo "\n🎉 Admin function tests completed!\n";
?>
