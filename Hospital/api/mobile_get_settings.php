<?php
// api/mobile_get_settings.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID required'
    ]);
    exit();
}

try {
    // Get user data
    $stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            first_name,
            last_name,
            phone,
            role,
            status,
            last_login,
            created_at
        FROM users 
        WHERE id = ?
    ");
    
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Format the response
    $formatted_user = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'phone' => $user['phone'] ?? '',
        'role' => $user['role'],
        'status' => $user['status'],
        'last_login' => $user['last_login'],
        'created_at' => $user['created_at'],
        'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
    ];
    
    echo json_encode([
        'success' => true,
        'user' => $formatted_user
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>