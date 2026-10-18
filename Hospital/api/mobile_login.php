<?php
// api/mobile_login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Use the SAME database connection as mobile_dashboard.php
require_once 'db.php';  // Changed from '../includes/db_connect.php'

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Username and password required'
    ]);
    exit();
}

$username = trim($data['username']);
$password = $data['password'];

try {
    // Query for patient login - check both password_hash and password fields
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM users u 
        WHERE (u.username = :username OR u.email = :email) 
        AND u.role = 'Patient' 
        AND u.status = 'active'
    ");
    $stmt->execute([
        ':username' => $username,
        ':email' => $username
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Try to verify password - check both password_hash and password fields
        $passwordVerified = false;
        
        if (isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            $passwordVerified = true;
        } elseif (isset($user['password']) && $user['password'] === $password) {
            $passwordVerified = true;
        } elseif (isset($user['password']) && password_verify($password, $user['password'])) {
            $passwordVerified = true;
        }
        
        if ($passwordVerified) {
            // Try to find matching patient record
            $patient_stmt = $pdo->prepare("
                SELECT 
                    id as patient_db_id,
                    patient_id as patient_number,
                    gender,
                    date_of_birth,
                    height,
                    weight,
                    blood_type,
                    marital_status,
                    address,
                    emergency_contact_name,
                    emergency_contact_phone,
                    relationship,
                    insurance_provider,
                    insurance_id,
                    medical_history,
                    chronic_conditions,
                    allergies,
                    current_medications as current_medications_text
                FROM patients 
                WHERE (email = :email OR phone = :phone)
                AND status = 'Active'
                LIMIT 1
            ");
            $patient_stmt->execute([
                ':email' => $user['email'],
                ':phone' => $user['phone']
            ]);
            $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update last login
            $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $update_stmt->execute([':id' => $user['id']]);
            
            // Prepare response
            $response = [
                'success' => true,
                'user_id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'status' => $user['status']
            ];
            
            // Add patient data if found
            if ($patient) {
                $response['patient_id'] = (int)$patient['patient_db_id'];
                $response['patient_number'] = $patient['patient_number'];
                $response['gender'] = $patient['gender'];
                $response['blood_type'] = $patient['blood_type'];
                $response['marital_status'] = $patient['marital_status'];
                $response['address'] = $patient['address'];
                $response['emergency_contact_name'] = $patient['emergency_contact_name'];
                $response['emergency_contact_phone'] = $patient['emergency_contact_phone'];
                $response['relationship'] = $patient['relationship'];
                $response['insurance_provider'] = $patient['insurance_provider'];
                $response['insurance_id'] = $patient['insurance_id'];
                $response['medical_history'] = $patient['medical_history'];
                $response['chronic_conditions'] = $patient['chronic_conditions'];
                $response['allergies'] = $patient['allergies'];
                $response['current_medications_text'] = $patient['current_medications_text'];
                $response['height'] = $patient['height'];
                $response['weight'] = $patient['weight'];
                
                // Calculate age if date of birth exists
                if ($patient['date_of_birth']) {
                    $dob = new DateTime($patient['date_of_birth']);
                    $now = new DateTime();
                    $response['age'] = $dob->diff($now)->y;
                    $response['date_of_birth'] = $patient['date_of_birth'];
                } else {
                    $response['age'] = null;
                    $response['date_of_birth'] = null;
                }
            } else {
                $response['patient_id'] = null;
                $response['patient_number'] = 'PT' . str_pad($user['id'], 6, '0', STR_PAD_LEFT);
                $response['gender'] = 'Not specified';
                $response['age'] = null;
                $response['date_of_birth'] = null;
            }
            
            echo json_encode($response);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found or not a patient'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Login error: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}
?>