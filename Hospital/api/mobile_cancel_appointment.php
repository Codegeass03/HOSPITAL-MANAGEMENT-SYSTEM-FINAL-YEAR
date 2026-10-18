<?php
// api/mobile_cancel_appointment.php
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

$appointment_id = $input['appointment_id'] ?? null;

if (!$appointment_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Appointment ID required'
    ]);
    exit();
}

try {
    // Check if appointment exists and can be cancelled
    $check_stmt = $pdo->prepare("
        SELECT id, appointment_date, appointment_time, status 
        FROM appointments 
        WHERE id = ?
    ");
    $check_stmt->execute([$appointment_id]);
    $appointment = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found'
        ]);
        exit();
    }
    
    // Check if appointment is in the future
    $datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    if (strtotime($datetime) < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel past appointments'
        ]);
        exit();
    }
    
    // Check if already cancelled
    if ($appointment['status'] === 'cancelled') {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment is already cancelled'
        ]);
        exit();
    }
    
    // Update appointment status to cancelled
    $update_stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled', 
            updated_at = NOW() 
        WHERE id = ?
    ");
    
    $update_stmt->execute([$appointment_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling appointment: ' . $e->getMessage()
    ]);
}
?>