<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

require_once 'db.php';

try {
    // Get user ID from request
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID required'
        ]);
        exit();
    }
    
    // Clear any session data
    session_unset();
    session_destroy();
    
    // In a real app, you might want to:
    // 1. Clear session from database
    // 2. Log the logout event
    // 3. Revoke tokens if using JWT
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Logout error: ' . $e->getMessage()
    ]);
}
?>