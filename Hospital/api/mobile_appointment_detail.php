<?php
// api/mobile_appointment_detail.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$appointment_id = $_GET['appointment_id'] ?? null;

if (!$appointment_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Appointment ID required'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.reason,
            a.notes,
            a.department,
            a.created_at,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            d.specialization as specialty,
            d.phone as doctor_phone,
            d.email as doctor_email
        FROM appointments a
        JOIN users d ON a.doctor_id = d.id
        WHERE a.id = ?
    ");
    
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found'
        ]);
        exit();
    }
    
    $datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    
    $formatted_appointment = [
        'id' => $appointment['id'],
        'date' => $appointment['appointment_date'],
        'time' => $appointment['appointment_time'],
        'datetime' => $datetime,
        'formatted_date' => date('M j, Y', strtotime($appointment['appointment_date'])),
        'formatted_time' => date('g:i A', strtotime($appointment['appointment_time'])),
        'formatted_datetime' => date('M j, Y - g:i A', strtotime($datetime)),
        'status' => $appointment['status'],
        'reason' => $appointment['reason'],
        'notes' => $appointment['notes'],
        'doctor_name' => $appointment['doctor_name'],
        'specialty' => $appointment['specialty'],
        'department' => $appointment['department'],
        'doctor_phone' => $appointment['doctor_phone'],
        'doctor_email' => $appointment['doctor_email'],
        'created_at' => $appointment['created_at'],
        'can_cancel' => (strtotime($datetime) > time() && $appointment['status'] == 'scheduled'),
        'can_reschedule' => (strtotime($datetime) > time() && in_array($appointment['status'], ['scheduled', 'confirmed']))
    ];
    
    echo json_encode([
        'success' => true,
        'appointment' => $formatted_appointment
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>