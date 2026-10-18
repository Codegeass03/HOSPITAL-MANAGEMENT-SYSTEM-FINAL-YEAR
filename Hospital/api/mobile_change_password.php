<?php
// api/mobile_change_password.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
$current_password = $input['current_password'] ?? null;
$new_password = $input['new_password'] ?? null;
$confirm_password = $input['confirm_password'] ?? null;

// Validation
if (!$user_id || !$current_password || !$new_password || !$confirm_password) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required'
    ]);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode([
        'success' => false,
        'message' => 'New passwords do not match'
    ]);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'New password must be at least 6 characters long'
    ]);
    exit();
}

try {
    // Get current user data
    $stmt = $pdo->prepare("
        SELECT id, password_hash 
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
    
    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect'
        ]);
        exit();
    }
    
    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $update_stmt->execute([$new_password_hash, $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error changing password: ' . $e->getMessage()
    ]);
}
?>