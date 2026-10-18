<?php
// api/mobile_medical_records.php
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

$patient_id = $_GET['patient_id'] ?? null;

if (!$patient_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID required'
    ]);
    exit();
}

try {
    error_log("Mobile Medical Records request for patient_id: " . $patient_id);
    
    // ===== GET PATIENT STATISTICS =====
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_visits,
            COUNT(DISTINCT a.doctor_id) as doctors_seen,
            COUNT(DISTINCT p.id) as total_prescriptions,
            COUNT(DISTINCT mr.id) as medical_records
        FROM appointments a
        LEFT JOIN prescriptions p ON a.patient_id = p.patient_id
        LEFT JOIN medical_records mr ON a.patient_id = mr.patient_id
        WHERE a.patient_id = ? 
        AND a.status IN ('completed', 'confirmed', 'scheduled')
    ");
    $stats_stmt->execute([$patient_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // ===== GET MEDICAL RECORDS (Appointment History) =====
    $medical_records_stmt = $pdo->prepare("
        SELECT 
            mr.id,
            mr.diagnosis,
            mr.treatment,
            mr.notes,
            mr.created_at,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            d.specialization as doctor_specialty,
            a.appointment_date,
            a.appointment_time,
            a.appointment_number,
            a.status,
            COUNT(DISTINCT p.id) as prescription_count
        FROM medical_records mr
        LEFT JOIN appointments a ON mr.patient_id = a.patient_id 
            AND DATE(mr.created_at) = DATE(a.appointment_date)
        LEFT JOIN users d ON mr.doctor_id = d.id
        LEFT JOIN prescriptions p ON mr.patient_id = p.patient_id 
            AND DATE(p.prescription_date) = DATE(mr.created_at)
        WHERE mr.patient_id = ?
        GROUP BY mr.id, a.appointment_date, a.appointment_number
        ORDER BY mr.created_at DESC
        LIMIT 50
    ");
    $medical_records_stmt->execute([$patient_id]);
    $medical_records = $medical_records_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format medical records
    $formatted_medical_records = [];
    foreach ($medical_records as $record) {
        $formatted_medical_records[] = [
            'id' => $record['id'],
            'appointment_number' => $record['appointment_number'] ?? 'N/A',
            'appointment_date' => $record['appointment_date'],
            'appointment_time' => $record['appointment_time'],
            'formatted_date' => $record['appointment_date'] ? date('M j, Y', strtotime($record['appointment_date'])) : 'Date not specified',
            'formatted_time' => $record['appointment_time'] ? date('g:i A', strtotime($record['appointment_time'])) : '',
            'status' => $record['status'] ?? 'completed',
            'doctor_name' => $record['doctor_name'] ?? 'Not specified',
            'doctor_specialty' => $record['doctor_specialty'],
            'diagnosis' => $record['diagnosis'],
            'treatment' => $record['treatment'],
            'notes' => $record['notes'],
            'prescription_count' => $record['prescription_count'] ?? 0,
            'created_at' => $record['created_at']
        ];
    }
    
    // ===== GET PRESCRIPTIONS =====
    $prescriptions_stmt = $pdo->prepare("
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
            p.status,
            p.dispensed,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            d.specialization
        FROM prescriptions p
        LEFT JOIN users d ON p.doctor_id = d.id
        WHERE p.patient_id = ?
        ORDER BY p.prescription_date DESC
        LIMIT 50
    ");
    $prescriptions_stmt->execute([$patient_id]);
    $prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format prescriptions
    $formatted_prescriptions = [];
    foreach ($prescriptions as $prescription) {
        $days_remaining = null;
        if ($prescription['valid_until']) {
            $end = new DateTime($prescription['valid_until']);
            $now = new DateTime();
            $diff = $now->diff($end);
            $days_remaining = $diff->days * ($diff->invert ? -1 : 1);
        }
        
        $formatted_prescriptions[] = [
            'id' => $prescription['id'],
            'prescription_number' => $prescription['prescription_number'],
            'medication_name' => $prescription['medication_name'],
            'dosage' => $prescription['dosage'],
            'frequency' => $prescription['frequency'],
            'duration' => $prescription['duration'],
            'instructions' => $prescription['instructions'],
            'doctor_name' => $prescription['doctor_name'],
            'specialty' => $prescription['specialization'],
            'prescription_date' => $prescription['prescription_date'],
            'valid_until' => $prescription['valid_until'],
            'status' => $prescription['status'],
            'dispensed' => $prescription['dispensed'] ? true : false,
            'formatted_date' => date('M j, Y', strtotime($prescription['prescription_date'])),
            'formatted_valid_until' => $prescription['valid_until'] ? date('M j, Y', strtotime($prescription['valid_until'])) : 'No expiry',
            'days_remaining' => $days_remaining,
            'is_expiring_soon' => ($days_remaining !== null && $days_remaining > 0 && $days_remaining <= 7),
            'is_expired' => ($days_remaining !== null && $days_remaining < 0)
        ];
    }
    
    // ===== GET CLINICAL NOTES =====
    $clinical_notes_stmt = $pdo->prepare("
        SELECT 
            cn.id,
            cn.subjective,
            cn.objective,
            cn.assessment,
            cn.plan,
            cn.created_at,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name
        FROM clinical_notes cn
        LEFT JOIN users d ON cn.doctor_id = d.id
        WHERE cn.patient_id = ?
        ORDER BY cn.created_at DESC
        LIMIT 50
    ");
    $clinical_notes_stmt->execute([$patient_id]);
    $clinical_notes = $clinical_notes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format clinical notes
    $formatted_clinical_notes = [];
    foreach ($clinical_notes as $note) {
        $formatted_clinical_notes[] = [
            'id' => $note['id'],
            'subjective' => $note['subjective'],
            'objective' => $note['objective'],
            'assessment' => $note['assessment'],
            'plan' => $note['plan'],
            'doctor_name' => $note['doctor_name'] ?? 'Unknown',
            'created_at' => $note['created_at'],
            'formatted_date' => date('M j, Y h:i A', strtotime($note['created_at']))
        ];
    }
    
    // ===== GET VITALS HISTORY =====
    $vitals_history_stmt = $pdo->prepare("
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
            CONCAT(u.first_name, ' ', u.last_name) as recorded_by_name
        FROM patient_vitals v
        LEFT JOIN users u ON v.recorded_by = u.id
        WHERE v.patient_id = ?
        ORDER BY v.recorded_at DESC
        LIMIT 30
    ");
    $vitals_history_stmt->execute([$patient_id]);
    $vitals_history = $vitals_history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format vitals history
    $formatted_vitals_history = [];
    foreach ($vitals_history as $vital) {
        $formatted_vitals_history[] = [
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
            'recorded_by_name' => $vital['recorded_by_name']
        ];
    }
    
    // Get latest vitals
    $latest_vitals = count($formatted_vitals_history) > 0 ? $formatted_vitals_history[0] : null;
    
    // ===== RETURN COMPREHENSIVE JSON RESPONSE =====
    $response = [
        'success' => true,
        'stats' => $stats,
        'medical_records' => $formatted_medical_records,
        'prescriptions' => $formatted_prescriptions,
        'clinical_notes' => $formatted_clinical_notes,
        'vitals_history' => $formatted_vitals_history,
        'latest_vitals' => $latest_vitals,
        'counts' => [
            'medical_records' => count($formatted_medical_records),
            'prescriptions' => count($formatted_prescriptions),
            'clinical_notes' => count($formatted_clinical_notes),
            'vitals_history' => count($formatted_vitals_history)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("Mobile Medical Records response prepared successfully");
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Mobile Medical Records error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>