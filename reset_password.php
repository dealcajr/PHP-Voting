<?php
require_once 'includes/config.php';

echo "This script will reset the password for a user. Enter the student ID and the new password.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $new_password = $_POST['password'];

    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE student_id = ?");
    $stmt->execute([$password_hash, $student_id]);

    echo "Password for student ID " . $student_id . " has been reset.";
}
?>

<form method="POST" action="">
    <label for="student_id">Student ID:</label>
    <input type="text" name="student_id" id="student_id" required>
    <br>
    <label for="password">New Password:</label>
    <input type="password" name="password" id="password" required>
    <br>
    <button type="submit">Reset Password</button>
</form>
