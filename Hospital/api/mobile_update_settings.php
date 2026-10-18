<?php
// api/mobile_update_settings.php
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
$username = $input['username'] ?? null;
$email = $input['email'] ?? null;
$first_name = $input['first_name'] ?? '';
$last_name = $input['last_name'] ?? '';
$phone = $input['phone'] ?? '';

// Validation
if (!$user_id || !$username || !$email) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID, username, and email are required'
    ]);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit();
}

try {
    // Check if username is already taken by another user
    $check_username = $pdo->prepare("
        SELECT id FROM users 
        WHERE username = ? AND id != ?
    ");
    $check_username->execute([$username, $user_id]);
    
    if ($check_username->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Username is already taken'
        ]);
        exit();
    }
    
    // Check if email is already taken by another user
    $check_email = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND id != ?
    ");
    $check_email->execute([$email, $user_id]);
    
    if ($check_email->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Email is already taken'
        ]);
        exit();
    }
    
    // Update user profile
    $stmt = $pdo->prepare("
        UPDATE users 
        SET username = ?, 
            email = ?, 
            first_name = ?, 
            last_name = ?, 
            phone = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $username,
        $email,
        $first_name,
        $last_name,
        $phone,
        $user_id
    ]);
    
    // Get updated user data
    $get_user = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            first_name,
            last_name,
            phone,
            role
        FROM users 
        WHERE id = ?
    ");
    $get_user->execute([$user_id]);
    $updated_user = $get_user->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $updated_user
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating profile: ' . $e->getMessage()
    ]);
}
?>