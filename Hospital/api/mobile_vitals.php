<?php
// api/mobile_vitals.php
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
$days = $_GET['days'] ?? 30;
$limit = $_GET['limit'] ?? 50;

if (!$patient_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID required'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.blood_pressure_systolic,
            v.blood_pressure_diastolic,
            CONCAT(v.blood_pressure_systolic, '/', v.blood_pressure_diastolic) as blood_pressure,
            v.heart_rate,
            v.temperature,
            v.oxygen_saturation,
            v.respiratory_rate,
            v.weight,
            v.height,
            v.bmi,
            v.recorded_at,
            v.notes,
            CONCAT(u.first_name, ' ', u.last_name) as recorded_by_name,
            u.role as recorded_by_role
        FROM patient_vitals v
        LEFT JOIN users u ON v.recorded_by = u.id
        WHERE v.patient_id = ?
        AND v.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY v.recorded_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$patient_id, $days, $limit]);
    $vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_vitals = [];
    foreach ($vitals as $vital) {
        $formatted_vitals[] = [
            'id' => $vital['id'],
            'recorded_at' => $vital['recorded_at'],
            'formatted_date' => date('M j, Y', strtotime($vital['recorded_at'])),
            'formatted_time' => date('g:i A', strtotime($vital['recorded_at'])),
            'formatted_datetime' => date('M j, Y - g:i A', strtotime($vital['recorded_at'])),
            'temperature' => $vital['temperature'],
            'blood_pressure' => $vital['blood_pressure'],
            'blood_pressure_systolic' => $vital['blood_pressure_systolic'],
            'blood_pressure_diastolic' => $vital['blood_pressure_diastolic'],
            'heart_rate' => $vital['heart_rate'],
            'respiratory_rate' => $vital['respiratory_rate'],
            'oxygen_saturation' => $vital['oxygen_saturation'],
            'height' => $vital['height'],
            'weight' => $vital['weight'],
            'bmi' => $vital['bmi'],
            'notes' => $vital['notes'],
            'recorded_by_name' => $vital['recorded_by_name'],
            'recorded_by_role' => $vital['recorded_by_role']
        ];
    }
    
    // Calculate trends
    $trends = [];
    if (count($formatted_vitals) > 1) {
        $latest = $formatted_vitals[0];
        $previous = $formatted_vitals[1];
        
        if ($latest['temperature'] && $previous['temperature']) {
            $temp_diff = floatval($latest['temperature']) - floatval($previous['temperature']);
            $trends['temperature'] = [
                'direction' => $temp_diff > 0 ? 'up' : ($temp_diff < 0 ? 'down' : 'stable'),
                'change' => abs($temp_diff),
                'percentage' => $previous['temperature'] > 0 ? abs(($temp_diff / $previous['temperature']) * 100) : 0
            ];
        }
        
        if ($latest['heart_rate'] && $previous['heart_rate']) {
            $hr_diff = floatval($latest['heart_rate']) - floatval($previous['heart_rate']);
            $trends['heart_rate'] = [
                'direction' => $hr_diff > 0 ? 'up' : ($hr_diff < 0 ? 'down' : 'stable'),
                'change' => abs($hr_diff),
                'percentage' => $previous['heart_rate'] > 0 ? abs(($hr_diff / $previous['heart_rate']) * 100) : 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'vitals' => $formatted_vitals,
        'count' => count($formatted_vitals),
        'trends' => $trends,
        'period_days' => $days
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>