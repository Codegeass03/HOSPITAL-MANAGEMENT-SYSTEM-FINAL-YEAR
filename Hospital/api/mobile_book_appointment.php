<?php
// api/mobile_book_appointment.php
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

$patient_id = $input['patient_id'] ?? null;
$doctor_id = $input['doctor_id'] ?? null;
$preferred_date = $input['preferred_date'] ?? null;
$preferred_time = $input['preferred_time'] ?? null;
$appointment_type = $input['appointment_type'] ?? 'consultation';
$urgency = $input['urgency'] ?? 'routine';
$reason = $input['reason'] ?? '';
$notes = $input['notes'] ?? '';

// Validation
if (!$patient_id || !$doctor_id || !$preferred_date || !$preferred_time) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit();
}

if (empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Reason for appointment is required'
    ]);
    exit();
}

try {
    // Insert appointment request
    $stmt = $pdo->prepare("
        INSERT INTO appointment_requests 
        (patient_id, doctor_id, preferred_date, preferred_time, type, urgency, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $patient_id,
        $doctor_id,
        $preferred_date,
        $preferred_time,
        $appointment_type,
        $urgency,
        $reason . ($notes ? "\n\nAdditional Notes: " . $notes : '')
    ]);
    
    $request_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment request submitted successfully',
        'request_id' => $request_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error booking appointment: ' . $e->getMessage()
    ]);
}
?>