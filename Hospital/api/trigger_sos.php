<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$patient_id = $data['patient_id'] ?? 0;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

try {
    // First, verify the patient exists
    $stmt = $pdo->prepare("SELECT id, patient_id, first_name, last_name FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit;
    }
    
    // Create the medical summary
    $medical_summary = "Patient: {$patient['first_name']} {$patient['last_name']}\n";
    $medical_summary .= "Patient ID: {$patient['patient_id']}\n";
    
    // Try to insert into sos_alerts
    $stmt = $pdo->prepare("
        INSERT INTO sos_alerts 
        (patient_id, emergency_type, priority, status, latitude, longitude, medical_summary, created_at)
        VALUES (?, 'Medical Emergency', 'high', 'active', ?, ?, ?, NOW())
    ");
    
    $success = $stmt->execute([
        $patient_id,
        $latitude,
        $longitude,
        $medical_summary
    ]);
    
    if ($success) {
        $alert_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'alert_id' => $alert_id,
            'message' => 'Emergency alert sent successfully! Help is on the way.',
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name']
        ]);
    } else {
        $error = $stmt->errorInfo();
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $error[2]
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'System error: ' . $e->getMessage()
    ]);
}
?>