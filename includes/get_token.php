<?php

require_once 'config.php';

// Ensure the user is logged in to get a token, to prevent token harvesting
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Generate and return a new token
echo json_encode([
    'csrf_token' => generateCSRFToken()
]);
?>