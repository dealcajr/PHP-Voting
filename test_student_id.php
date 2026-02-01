<?php
require_once 'includes/config.php';

echo "--- Testing Student ID Generation ---\n";

try {
    // Generate a few student IDs
    for ($i = 0; $i < 5; $i++) {
        $student_id = generateStudentID();
        echo "Generated ID: $student_id\n";

        // Verify the format
        if (preg_match('/^Stu\d{3}$/', $student_id)) {
            echo "✅ Format is correct\n";
        } else {
            echo "❌ Format is incorrect\n";
        }
    }

    echo "\n--- Testing ID Uniqueness ---\n";

    // Test uniqueness by generating more IDs
    $ids = [];
    for ($i = 0; $i < 10; $i++) {
        $student_id = generateStudentID();
        if (in_array($student_id, $ids)) {
            echo "❌ Duplicate ID found: $student_id\n";
        } else {
            echo "✅ Unique ID: $student_id\n";
            $ids[] = $student_id;
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
