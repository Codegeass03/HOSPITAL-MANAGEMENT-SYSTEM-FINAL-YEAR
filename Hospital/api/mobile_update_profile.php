<?php
// api/mobile_update_profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$user_id = $data['userId'] ?? null;

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID required'
    ]);
    exit();
}

try {
    // Get user info to find matching patient record
    $user_stmt = $pdo->prepare("
        SELECT email, phone FROM users WHERE id = ? AND role = 'Patient'
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Find matching patient record
    $patient_stmt = $pdo->prepare("
        SELECT id FROM patients 
        WHERE (email = ? OR phone = ?)
        AND status = 'Active'
        LIMIT 1
    ");
    $patient_stmt->execute([$user['email'], $user['phone']]);
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        echo json_encode([
            'success' => false,
            'message' => 'Patient record not found'
        ]);
        exit();
    }
    
    $patient_id = $patient['id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update user table
    $update_user_stmt = $pdo->prepare("
        UPDATE users 
        SET phone = ?, email = ?
        WHERE id = ?
    ");
    $update_user_stmt->execute([
        $data['phone'] ?? $user['phone'],
        $data['email'] ?? $user['email'],
        $user_id
    ]);
    
    // Update patient table
    $update_patient_stmt = $pdo->prepare("
        UPDATE patients 
        SET 
            phone = ?,
            email = ?,
            address = ?,
            height = ?,
            weight = ?,
            emergency_contact_name = ?,
            emergency_contact_phone = ?,
            relationship = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $update_patient_stmt->execute([
        $data['phone'] ?? null,
        $data['email'] ?? null,
        $data['address'] ?? null,
        $data['height'] ?? null,
        $data['weight'] ?? null,
        $data['emergency_contact_name'] ?? null,
        $data['emergency_contact_phone'] ?? null,
        $data['relationship'] ?? null,
        $patient_id
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Update profile error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating profile: ' . $e->getMessage()
    ]);
}
?>