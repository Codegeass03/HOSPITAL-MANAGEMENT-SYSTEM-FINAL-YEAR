<?php
// api/mobile_patient_profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Get patient ID from query parameter or user session
$patient_id = $_GET['patient_id'] ?? null;
$user_id = $_GET['user_id'] ?? null; // For patient viewing their own profile

if (!$patient_id && !$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID or User ID required'
    ]);
    exit();
}

try {
    // Determine if we're getting data by patient_id or user_id
    if ($patient_id) {
        // Direct patient ID lookup
        $patient_stmt = $pdo->prepare("
            SELECT * FROM patients 
            WHERE id = ? OR patient_id = ?
            AND status = 'Active'
        ");
        $patient_stmt->execute([$patient_id, $patient_id]);
        $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // User ID lookup - find matching patient
        $user_stmt = $pdo->prepare("
            SELECT email, phone FROM users WHERE id = ?
        ");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $patient_stmt = $pdo->prepare("
                SELECT * FROM patients 
                WHERE (email = ? OR phone = ?)
                AND status = 'Active'
                LIMIT 1
            ");
            $patient_stmt->execute([$user['email'], $user['phone']]);
            $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $patient = null;
        }
    }
    
    if (!$patient) {
        echo json_encode([
            'success' => false,
            'message' => 'Patient not found'
        ]);
        exit();
    }
    
    // Calculate age
    $age = null;
    if ($patient['date_of_birth']) {
        $dob = new DateTime($patient['date_of_birth']);
        $now = new DateTime();
        $age = $dob->diff($now)->y;
    }
    
    // Calculate BMI
    $bmi = null;
    $bmi_category = null;
    if ($patient['height'] && $patient['weight'] && $patient['height'] > 0) {
        $height_m = $patient['height'] / 100;
        $bmi = $patient['weight'] / ($height_m * $height_m);
        
        if ($bmi < 18.5) {
            $bmi_category = 'Underweight';
        } elseif ($bmi >= 18.5 && $bmi < 25) {
            $bmi_category = 'Normal';
        } elseif ($bmi >= 25 && $bmi < 30) {
            $bmi_category = 'Overweight';
        } else {
            $bmi_category = 'Obese';
        }
    }
    
    // Get statistics
    $stats = [
        'total_appointments' => 0,
        'total_prescriptions' => 0,
        'last_appointment' => null
    ];
    
    // Appointment count
    $appt_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_appointments,
               MAX(appointment_date) as last_appointment
        FROM appointments 
        WHERE patient_id = ?
    ");
    $appt_stmt->execute([$patient['id']]);
    $appt_stats = $appt_stmt->fetch(PDO::FETCH_ASSOC);
    if ($appt_stats) {
        $stats['total_appointments'] = $appt_stats['total_appointments'];
        $stats['last_appointment'] = $appt_stats['last_appointment'];
    }
    
    // Prescription count
    $rx_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_prescriptions
        FROM prescriptions 
        WHERE patient_id = ? AND status = 'active'
    ");
    $rx_stmt->execute([$patient['id']]);
    $rx_stats = $rx_stmt->fetch(PDO::FETCH_ASSOC);
    if ($rx_stats) {
        $stats['total_prescriptions'] = $rx_stats['total_prescriptions'];
    }
    
    // Parse arrays for conditions, allergies, medications
    $chronic_conditions = $patient['chronic_conditions'] ? 
        array_map('trim', explode(',', $patient['chronic_conditions'])) : [];
    
    $allergies = $patient['allergies'] ? 
        array_map('trim', explode(',', $patient['allergies'])) : [];
    
    // Format current medications if stored as text
    $current_medications = $patient['current_medications'] ? 
        array_filter(array_map('trim', explode("\n", $patient['current_medications']))) : [];
    
    // Prepare the response
    $response = [
        'success' => true,
        'profile' => [
            'id' => (int)$patient['id'],
            'patient_id' => $patient['patient_id'],
            'first_name' => $patient['first_name'],
            'last_name' => $patient['last_name'],
            'full_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'date_of_birth' => $patient['date_of_birth'],
            'age' => $age,
            'gender' => $patient['gender'],
            'marital_status' => $patient['marital_status'],
            'phone' => $patient['phone'],
            'email' => $patient['email'],
            'address' => $patient['address'],
            'emergency_contact_name' => $patient['emergency_contact_name'],
            'emergency_contact_phone' => $patient['emergency_contact_phone'],
            'relationship' => $patient['relationship'],
            'insurance_provider' => $patient['insurance_provider'],
            'insurance_id' => $patient['insurance_id'],
            
            // Health metrics
            'height' => $patient['height'],
            'weight' => $patient['weight'],
            'blood_type' => $patient['blood_type'],
            'bmi' => $bmi ? round($bmi, 1) : null,
            'bmi_category' => $bmi_category,
            
            // Medical information
            'medical_history' => $patient['medical_history'],
            'chronic_conditions' => $chronic_conditions,
            'past_surgeries' => $patient['past_surgeries'],
            'allergies' => $allergies,
            'current_medications' => $current_medications,
            'family_history' => $patient['family_history'],
            
            // Lifestyle
            'smoking_status' => $patient['smoking_status'],
            'alcohol_consumption' => $patient['alcohol_consumption'],
            'exercise_frequency' => $patient['exercise_frequency'],
            'dietary_habits' => $patient['dietary_habits'],
            
            // Additional
            'primary_physician' => $patient['primary_physician'],
            'additional_notes' => $patient['additional_family_notes'],
            'status' => $patient['status'],
            'created_at' => $patient['created_at'],
            'updated_at' => $patient['updated_at']
        ],
        'stats' => $stats,
        'summary' => [
            'initials' => strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1))
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>