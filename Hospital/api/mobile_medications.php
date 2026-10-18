<?php
// api/mobile_medications.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$patient_id = $_GET['patient_id'] ?? null;
$filter = $_GET['filter'] ?? 'active';

if (!$patient_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID required'
    ]);
    exit();
}

try {
    $where_clause = "p.patient_id = ?";
    $params = [$patient_id];
    
    switch ($filter) {
        case 'active':
            $where_clause .= " AND p.status = 'active'";
            break;
        case 'expired':
            $where_clause .= " AND (p.status = 'completed' OR p.status = 'expired')";
            break;
        // 'all' needs no additional filter
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.prescription_number,
            p.medication_name,
            p.dosage,
            p.frequency,
            p.duration,
            p.instructions,
            p.prescription_date,
            p.valid_until,
            p.diagnosis,
            p.status,
            p.dispensed,
            p.dispensed_date,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            d.specialization,
            d.phone as doctor_phone
        FROM prescriptions p
        LEFT JOIN users d ON p.doctor_id = d.id
        WHERE $where_clause
        ORDER BY p.prescription_date DESC
    ");
    
    $stmt->execute($params);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_medications = [];
    foreach ($medications as $med) {
        $days_remaining = null;
        if ($med['valid_until']) {
            $end = new DateTime($med['valid_until']);
            $now = new DateTime();
            $diff = $now->diff($end);
            $days_remaining = $diff->days * ($diff->invert ? -1 : 1);
        }
        
        $formatted_medications[] = [
            'id' => $med['id'],
            'prescription_number' => $med['prescription_number'],
            'medication_name' => $med['medication_name'],
            'dosage' => $med['dosage'],
            'frequency' => $med['frequency'],
            'duration' => $med['duration'],
            'instructions' => $med['instructions'],
            'diagnosis' => $med['diagnosis'],
            'doctor_name' => $med['doctor_name'],
            'specialty' => $med['specialization'],
            'doctor_phone' => $med['doctor_phone'],
            'prescription_date' => $med['prescription_date'],
            'valid_until' => $med['valid_until'],
            'status' => $med['status'],
            'dispensed' => $med['dispensed'] ? true : false,
            'dispensed_date' => $med['dispensed_date'],
            'formatted_prescription_date' => date('M j, Y', strtotime($med['prescription_date'])),
            'formatted_valid_until' => $med['valid_until'] ? date('M j, Y', strtotime($med['valid_until'])) : 'No expiry',
            'days_remaining' => $days_remaining,
            'is_expiring_soon' => ($days_remaining !== null && $days_remaining > 0 && $days_remaining <= 7),
            'is_expired' => ($days_remaining !== null && $days_remaining < 0)
        ];
    }
    
    // Group by status
    $grouped = [
        'active' => [],
        'expiring_soon' => [],
        'expired' => []
    ];
    
    foreach ($formatted_medications as $med) {
        if ($med['is_expired'] || in_array($med['status'], ['completed', 'expired'])) {
            $grouped['expired'][] = $med;
        } elseif ($med['is_expiring_soon']) {
            $grouped['expiring_soon'][] = $med;
        } elseif ($med['status'] === 'active') {
            $grouped['active'][] = $med;
        }
    }
    
    echo json_encode([
        'success' => true,
        'medications' => $formatted_medications,
        'grouped' => $grouped,
        'count' => count($formatted_medications),
        'counts' => [
            'active' => count($grouped['active']),
            'expiring_soon' => count($grouped['expiring_soon']),
            'expired' => count($grouped['expired'])
        ],
        'filter' => $filter
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>