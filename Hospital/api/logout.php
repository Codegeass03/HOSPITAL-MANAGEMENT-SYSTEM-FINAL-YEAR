<?php
// api/logout.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/db_connect.php';

// Get the token from header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization token required'
    ]);
    exit();
}

try {
    // Clear API token (set to NULL and expiry to past)
    $stmt = $pdo->prepare("
        UPDATE users SET 
            api_token = NULL,
            api_token_expiry = DATE_SUB(NOW(), INTERVAL 1 DAY)
        WHERE api_token = ?
    ");
    
    $stmt->execute([$token]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Logout Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error during logout'
    ]);
}
?>