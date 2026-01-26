<?php
require_once 'includes/config.php';

echo "Testing SSLG Voting System Database Connection...\n\n";

try {
    $db = getDBConnection();
    echo "✅ Database connection successful!\n";

    // Test basic queries
    $tables = ['users', 'candidates', 'votes', 'election_settings', 'audit_log', 'school_info'];
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        echo "✅ Table '$table': {$result['count']} records\n";
    }

    // Test sample data
    echo "\n--- Sample Data Check ---\n";
    $admin = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if ($admin) {
        echo "✅ Admin user found: {$admin['student_id']}\n";
    }

    $students = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'voter'")->fetch();
    echo "✅ Total students: {$students['count']}\n";

    $candidates = $db->query("SELECT COUNT(*) as count FROM candidates")->fetch();
    echo "✅ Total candidates: {$candidates['count']}\n";

    $election = $db->query("SELECT * FROM election_settings LIMIT 1")->fetch();
    echo "✅ Election status: " . ($election['is_open'] ? 'OPEN' : 'CLOSED') . "\n";

    echo "\n🎉 All database tests passed!\n";

} catch (Exception $e) {
    echo "❌ Database test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
