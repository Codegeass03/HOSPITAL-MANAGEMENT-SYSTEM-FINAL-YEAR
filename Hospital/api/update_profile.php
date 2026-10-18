<?php
// api/mobile_update_profile.php
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

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit();
}

// Validate required fields
if (empty($data['user_id']) || empty($data['username']) || empty($data['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID, username, and email are required'
    ]);
    exit();
}

$user_id = $data['user_id'];
$username = $data['username'];
$email = $data['email'];
$first_name = $data['first_name'] ?? '';
$last_name = $data['last_name'] ?? '';
$phone = $data['phone'] ?? '';

try {
    // Check if username already exists (excluding current user)
    $check_username = $pdo->prepare("
        SELECT id FROM users 
        WHERE username = ? AND id != ?
    ");
    $check_username->execute([$username, $user_id]);
    if ($check_username->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Username already taken'
        ]);
        exit();
    }
    
    // Check if email already exists (excluding current user)
    $check_email = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND id != ?
    ");
    $check_email->execute([$email, $user_id]);
    if ($check_email->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already registered'
        ]);
        exit();
    }
    
    // Update user profile (same as your web version)
    $update_stmt = $pdo->prepare("
        UPDATE users SET 
            username = ?,
            email = ?,
            first_name = ?,
            last_name = ?,
            phone = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $update_stmt->execute([
        $username,
        $email,
        $first_name,
        $last_name,
        $phone,
        $user_id
    ]);
    
    // Get updated profile
    $get_updated = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $get_updated->execute([$user_id]);
    $updated_user = $get_updated->fetch(PDO::FETCH_ASSOC);
    
    $profile = [
        'id' => $updated_user['id'],
        'username' => $updated_user['username'],
        'email' => $updated_user['email'],
        'first_name' => $updated_user['first_name'],
        'last_name' => $updated_user['last_name'],
        'phone' => $updated_user['phone'],
        'role' => $updated_user['role'],
        'last_login' => $updated_user['last_login']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'profile' => $profile
    ]);
    
} catch (Exception $e) {
    error_log("Update Profile Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating profile'
    ]);
}
?>