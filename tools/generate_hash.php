<?php
echo "Admin password 'admin123' hash: " . password_hash('admin123', PASSWORD_DEFAULT) . "\n";
echo "Student password 'password123' hash: " . password_hash('password123', PASSWORD_DEFAULT) . "\n";
?>
