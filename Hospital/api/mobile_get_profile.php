<?php
// api/mobile_get_profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/db_connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit();
}

$user_id = $data['user_id'];

try {
    // Get user data from users table
    $stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            first_name,
            last_name,
            phone,
            role,
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
    
    // For mobile app, set role to "Patient" by default since it's a patient app
    $user['role'] = 'Patient'; // Force Patient role for mobile app
    
    echo json_encode([
        'success' => true,
        'profile' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Get Profile Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving profile'
    ]);
}
?>