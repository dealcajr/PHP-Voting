<?php
require_once 'includes/config.php';

echo "Fixing SSLG Voting System Passwords...\n\n";

try {
    $db = getDBConnection();

    // Generate correct hashes
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $studentHash = password_hash('admin123', PASSWORD_DEFAULT); // Same password for simplicity

    // Update admin password
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE student_id = 'ADMIN001'");
    $stmt->execute([$adminHash]);
    echo "✅ Updated admin password for ADMIN001\n";

    // Update student passwords
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE role = 'voter'");
    $stmt->execute([$studentHash]);
    echo "✅ Updated student passwords\n";

    echo "\n🎉 Passwords fixed! You can now login with:\n";
    echo "- Admin: ADMIN001 / admin123\n";
    echo "- Students: STU001 / admin123, STU002 / admin123\n";

} catch (Exception $e) {
    echo "❌ Error fixing passwords: " . $e->getMessage() . "\n";
}
?>
