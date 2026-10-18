<?php
// api/mobile_appointments.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$patient_id = $_GET['patient_id'] ?? null;
$filter = $_GET['filter'] ?? 'upcoming';

if (!$patient_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID required'
    ]);
    exit();
}

try {
    $where_clause = "a.patient_id = ?";
    $params = [$patient_id];
    
    switch ($filter) {
        case 'upcoming':
            $where_clause .= " AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW() AND a.status IN ('scheduled', 'confirmed')";
            $order = "a.appointment_date ASC, a.appointment_time ASC";
            break;
        case 'past':
            $where_clause .= " AND CONCAT(a.appointment_date, ' ', a.appointment_time) < NOW()";
            $order = "a.appointment_date DESC, a.appointment_time DESC";
            break;
        case 'pending':
            $where_clause .= " AND a.status = 'pending'";
            $order = "a.created_at DESC";
            break;
        case 'today':
            $where_clause .= " AND a.appointment_date = CURDATE()";
            $order = "a.appointment_time ASC";
            break;
        default:
            $order = "a.appointment_date DESC, a.appointment_time DESC";
    }
    
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
            d.phone as doctor_phone
        FROM appointments a
        JOIN users d ON a.doctor_id = d.id
        WHERE $where_clause
        ORDER BY $order
    ");
    
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_appointments = [];
    foreach ($appointments as $apt) {
        $datetime = $apt['appointment_date'] . ' ' . $apt['appointment_time'];
        $formatted_appointments[] = [
            'id' => $apt['id'],
            'date' => $apt['appointment_date'],
            'time' => $apt['appointment_time'],
            'datetime' => $datetime,
            'formatted_date' => date('M j, Y', strtotime($apt['appointment_date'])),
            'formatted_time' => date('g:i A', strtotime($apt['appointment_time'])),
            'formatted_datetime' => date('M j, Y - g:i A', strtotime($datetime)),
            'status' => $apt['status'],
            'reason' => $apt['reason'] ?? 'Check-up',
            'notes' => $apt['notes'],
            'doctor_name' => $apt['doctor_name'],
            'specialty' => $apt['specialty'],
            'department' => $apt['department'],
            'doctor_phone' => $apt['doctor_phone'],
            'can_cancel' => (strtotime($datetime) > time() && in_array($apt['status'], ['scheduled', 'pending'])),
            'can_reschedule' => (strtotime($datetime) > time() && in_array($apt['status'], ['scheduled', 'confirmed']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'appointments' => $formatted_appointments,
        'count' => count($formatted_appointments),
        'filter' => $filter
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>