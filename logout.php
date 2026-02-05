<?php
require_once 'includes/config.php';

// Log admin logout
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    logAdminAction('logout', 'Admin logged out');
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
$auto = isset($_GET['auto']) ? '?auto=1' : '';
header('Location: vote_login.php' . $auto);
exit();
?>
