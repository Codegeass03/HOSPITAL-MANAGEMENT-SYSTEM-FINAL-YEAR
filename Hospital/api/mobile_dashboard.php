<?php
// api/mobile_dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID required'
    ]);
    exit();
}

try {
    error_log("Mobile Dashboard request for user_id: " . $user_id);
    
    // ===== FETCH USER INFORMATION =====
    $user_stmt = $pdo->prepare("
        SELECT 
            id as user_id,
            username,
            email,
            first_name,
            last_name,
            phone,
            role,
            status,
            last_login
        FROM users 
        WHERE id = ? AND role = 'Patient' AND status = 'active'
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        error_log("User not found or not a patient: " . $user_id);
        echo json_encode([
            'success' => false,
            'message' => 'Patient user not found'
        ]);
        exit();
    }
    
    // ===== FETCH PATIENT RECORD =====
    $patient_stmt = $pdo->prepare("
        SELECT 
            id as patient_db_id,
            patient_id as patient_number,
            first_name,
            last_name,
            date_of_birth,
            age,
            gender,
            marital_status,
            height,
            weight,
            blood_type,
            emergency_contact_name,
            emergency_contact_phone,
            relationship,
            insurance_provider,
            insurance_id,
            medical_history,
            chronic_conditions,
            allergies,
            current_medications,
            status,
            address
        FROM patients 
        WHERE (email = ? OR phone = ?)
        AND status = 'Active'
        LIMIT 1
    ");
    $patient_stmt->execute([$user['email'], $user['phone']]);
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $patient_stmt2 = $pdo->prepare("
            SELECT 
                id as patient_db_id,
                patient_id as patient_number,
                first_name,
                last_name,
                date_of_birth,
                age,
                gender,
                marital_status,
                height,
                weight,
                blood_type,
                emergency_contact_name,
                emergency_contact_phone,
                relationship,
                insurance_provider,
                insurance_id,
                medical_history,
                chronic_conditions,
                allergies,
                current_medications,
                status,
                address
            FROM patients 
            WHERE (first_name LIKE ? AND last_name LIKE ?)
            AND status = 'Active'
            LIMIT 1
        ");
        $firstNamePattern = $user['first_name'] . '%';
        $lastNamePattern = $user['last_name'] . '%';
        $patient_stmt2->execute([$firstNamePattern, $lastNamePattern]);
        $patient = $patient_stmt2->fetch(PDO::FETCH_ASSOC);
    }
    
    $age = null;
    if ($patient && $patient['date_of_birth']) {
        $dob = new DateTime($patient['date_of_birth']);
        $now = new DateTime();
        $age = $dob->diff($now)->y;
    }
    
    // ===== INITIALIZE STATS =====
    $stats = [
        'upcoming_appointments' => 0,
        'total_appointments' => 0,
        'lab_results' => 0,
        'active_prescriptions' => 0,
        'medical_records' => 0,
        'vital_signs' => 0,
        'today_vitals' => 0,
        'doctors_seen' => 0,
        'total_visits' => 0
    ];
    
    $latest_vitals = null;
    $upcoming_appointments = [];
    $current_medications = [];
    $recent_lab_orders = [];
    $health_metrics = [];
    
    if ($patient && isset($patient['patient_db_id'])) {
        $patient_db_id = $patient['patient_db_id'];
        
        // ===== UPCOMING APPOINTMENTS (FIXED: Combined date and time, no room_number) =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE patient_id = ? 
            AND CONCAT(appointment_date, ' ', appointment_time) >= NOW()
            AND status IN ('scheduled', 'confirmed')
        ");
        $stmt->execute([$patient_db_id]);
        $stats['upcoming_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $appointments_stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.reason,
                a.notes,
                a.department,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                d.specialization as specialty
            FROM appointments a
            JOIN users d ON a.doctor_id = d.id
            WHERE a.patient_id = ?
            AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW()
            AND a.status IN ('scheduled', 'confirmed')
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT 5
        ");
        $appointments_stmt->execute([$patient_db_id]);
        $upcoming_appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ===== TOTAL APPOINTMENTS =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE patient_id = ?
        ");
        $stmt->execute([$patient_db_id]);
        $stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // ===== TOTAL VISITS & DOCTORS SEEN =====
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT a.id) as total_visits,
                COUNT(DISTINCT a.doctor_id) as doctors_seen
            FROM appointments a
            WHERE a.patient_id = ? 
            AND a.status IN ('completed', 'confirmed', 'scheduled')
        ");
        $stats_stmt->execute([$patient_db_id]);
        $visit_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_visits'] = $visit_stats['total_visits'] ?? 0;
        $stats['doctors_seen'] = $visit_stats['doctors_seen'] ?? 0;
        
        // ===== LAB RESULTS COUNT =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM lab_orders 
            WHERE patient_id = ? 
            AND status = 'completed'
        ");
        $stmt->execute([$patient_db_id]);
        $stats['lab_results'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $lab_stmt = $pdo->prepare("
            SELECT 
                lo.id,
                lo.order_number,
                lo.order_date,
                lo.status,
                lo.priority,
                COUNT(loi.id) as test_count
            FROM lab_orders lo
            LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
            WHERE lo.patient_id = ?
            GROUP BY lo.id
            ORDER BY lo.order_date DESC
            LIMIT 5
        ");
        $lab_stmt->execute([$patient_db_id]);
        $recent_lab_orders = $lab_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ===== ACTIVE PRESCRIPTIONS (FIXED: No start_date or end_date in your schema) =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions 
            WHERE patient_id = ? 
            AND status = 'active'
        ");
        $stmt->execute([$patient_db_id]);
        $stats['active_prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $meds_stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.medication_name,
                p.dosage,
                p.frequency,
                p.duration,
                p.instructions,
                p.prescription_date,
                p.valid_until,
                p.status,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                d.specialization
            FROM prescriptions p
            LEFT JOIN users d ON p.doctor_id = d.id
            WHERE p.patient_id = ?
            AND p.status = 'active'
            ORDER BY p.prescription_date DESC
            LIMIT 10
        ");
        $meds_stmt->execute([$patient_db_id]);
        $current_medications = $meds_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ===== MEDICAL RECORDS (clinical_notes) =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM clinical_notes 
            WHERE patient_id = ?
        ");
        $stmt->execute([$patient_db_id]);
        $stats['medical_records'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // ===== VITAL SIGNS (FIXED: Using patient_vitals table) =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM patient_vitals 
            WHERE patient_id = ?
        ");
        $stmt->execute([$patient_db_id]);
        $stats['vital_signs'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM patient_vitals 
            WHERE patient_id = ? 
            AND DATE(recorded_at) = CURDATE()
        ");
        $stmt->execute([$patient_db_id]);
        $stats['today_vitals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // ===== LATEST VITALS (FIXED: Using proper column names) =====
        $vitals_stmt = $pdo->prepare("
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
            LIMIT 1
        ");
        $vitals_stmt->execute([$patient_db_id]);
        $latest_vitals = $vitals_stmt->fetch(PDO::FETCH_ASSOC);
        
        // ===== HEALTH METRICS (Last 14 days) =====
        $metrics_stmt = $pdo->prepare("
            SELECT 
                DATE(recorded_at) as date,
                AVG(temperature) as avg_temp,
                AVG(heart_rate) as avg_hr,
                AVG(oxygen_saturation) as avg_spo2,
                AVG(weight) as avg_weight,
                AVG(blood_pressure_systolic) as avg_bp_sys,
                AVG(blood_pressure_diastolic) as avg_bp_dia
            FROM patient_vitals 
            WHERE patient_id = ?
            AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            GROUP BY DATE(recorded_at)
            ORDER BY date ASC
        ");
        $metrics_stmt->execute([$patient_db_id]);
        $health_metrics = $metrics_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ===== CALCULATE BMI =====
    $bmi = null;
    $bmi_category = null;
    if ($latest_vitals && isset($latest_vitals['bmi']) && $latest_vitals['bmi'] > 0) {
        $bmi = round(floatval($latest_vitals['bmi']), 1);
    } elseif ($latest_vitals && isset($latest_vitals['weight']) && isset($latest_vitals['height'])) {
        $height_m = floatval($latest_vitals['height']) / 100;
        $weight = floatval($latest_vitals['weight']);
        
        if ($height_m > 0 && $weight > 0) {
            $bmi = round($weight / ($height_m * $height_m), 1);
        }
    }
    
    if ($bmi) {
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
    
    // ===== PREPARE PATIENT DATA =====
    $patient_data = [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'status' => $user['status'],
        'last_login' => $user['last_login']
    ];
    
    if ($patient) {
        $patient_data = array_merge($patient_data, [
            'patient_id' => $patient['patient_db_id'],
            'patient_number' => $patient['patient_number'],
            'gender' => $patient['gender'],
            'age' => $age,
            'date_of_birth' => $patient['date_of_birth'],
            'marital_status' => $patient['marital_status'],
            'height' => $patient['height'],
            'weight' => $patient['weight'],
            'blood_type' => $patient['blood_type'],
            'address' => $patient['address'],
            'emergency_contact_name' => $patient['emergency_contact_name'],
            'emergency_contact_phone' => $patient['emergency_contact_phone'],
            'relationship' => $patient['relationship'],
            'insurance_provider' => $patient['insurance_provider'],
            'insurance_id' => $patient['insurance_id'],
            'medical_history' => $patient['medical_history'],
            'chronic_conditions' => $patient['chronic_conditions'],
            'allergies' => $patient['allergies'],
            'current_medications_text' => $patient['current_medications']
        ]);
    } else {
        $patient_data = array_merge($patient_data, [
            'patient_id' => null,
            'patient_number' => 'PT' . str_pad($user['user_id'], 6, '0', STR_PAD_LEFT),
            'gender' => 'Not specified',
            'age' => null,
            'date_of_birth' => null,
            'marital_status' => null,
            'height' => null,
            'weight' => null,
            'blood_type' => null,
            'address' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'relationship' => null,
            'insurance_provider' => null,
            'insurance_id' => null,
            'medical_history' => null,
            'chronic_conditions' => null,
            'allergies' => null,
            'current_medications_text' => null
        ]);
    }
    
    // ===== CHECK PROFILE COMPLETENESS =====
    $profile_complete = true;
    $missing_fields = [];
    
    if (!$patient_data['date_of_birth'] || $patient_data['date_of_birth'] == '2000-01-01') {
        $profile_complete = false;
        $missing_fields[] = 'date_of_birth';
    }
    if (!$patient_data['gender'] || $patient_data['gender'] == 'Not specified') {
        $profile_complete = false;
        $missing_fields[] = 'gender';
    }
    if (empty($patient_data['height'])) {
        $profile_complete = false;
        $missing_fields[] = 'height';
    }
    if (empty($patient_data['weight'])) {
        $profile_complete = false;
        $missing_fields[] = 'weight';
    }
    if (empty($patient_data['blood_type'])) {
        $profile_complete = false;
        $missing_fields[] = 'blood_type';
    }
    if (empty($patient_data['emergency_contact_name'])) {
        $profile_complete = false;
        $missing_fields[] = 'emergency_contact';
    }
    
    // ===== FORMAT APPOINTMENTS FOR MOBILE (FIXED: Combined date+time) =====
    $formatted_appointments = [];
    foreach ($upcoming_appointments as $apt) {
        $datetime = $apt['appointment_date'] . ' ' . $apt['appointment_time'];
        $formatted_appointments[] = [
            'id' => $apt['id'],
            'date' => $apt['appointment_date'],
            'time' => $apt['appointment_time'],
            'datetime' => $datetime,
            'formatted_date' => date('M j, Y', strtotime($apt['appointment_date'])),
            'formatted_time' => date('g:i A', strtotime($apt['appointment_time'])),
            'status' => $apt['status'],
            'reason' => $apt['reason'] ?? 'Check-up',
            'notes' => $apt['notes'],
            'doctor_name' => $apt['doctor_name'],
            'specialty' => $apt['specialty'],
            'department' => $apt['department']
        ];
    }
    
    // ===== FORMAT MEDICATIONS FOR MOBILE (FIXED: Using valid_until instead of end_date) =====
    $formatted_medications = [];
    foreach ($current_medications as $med) {
        $days_remaining = null;
        if ($med['valid_until']) {
            $end = new DateTime($med['valid_until']);
            $now = new DateTime();
            $diff = $now->diff($end);
            $days_remaining = $diff->days * ($diff->invert ? -1 : 1);
        }
        
        $formatted_medications[] = [
            'id' => $med['id'],
            'medication_name' => $med['medication_name'],
            'dosage' => $med['dosage'],
            'frequency' => $med['frequency'],
            'duration' => $med['duration'],
            'instructions' => $med['instructions'],
            'doctor_name' => $med['doctor_name'],
            'specialty' => $med['specialization'],
            'prescription_date' => $med['prescription_date'],
            'valid_until' => $med['valid_until'],
            'status' => $med['status'],
            'formatted_date' => date('M j, Y', strtotime($med['prescription_date'])),
            'formatted_valid_until' => $med['valid_until'] ? date('M j, Y', strtotime($med['valid_until'])) : 'No expiry',
            'days_remaining' => $days_remaining,
            'is_expiring_soon' => ($days_remaining !== null && $days_remaining > 0 && $days_remaining <= 7),
            'is_expired' => ($days_remaining !== null && $days_remaining < 0)
        ];
    }
    
    // ===== FORMAT LAB ORDERS FOR MOBILE =====
    $formatted_lab_orders = [];
    foreach ($recent_lab_orders as $order) {
        $formatted_lab_orders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'] ?? 'LAB-' . $order['id'],
            'order_date' => $order['order_date'],
            'formatted_date' => date('M j, Y', strtotime($order['order_date'])),
            'status' => $order['status'],
            'priority' => $order['priority'],
            'test_count' => $order['test_count']
        ];
    }
    
    // ===== VITAL SIGNS ASSESSMENT =====
    $vitals_assessment = [];
    if ($latest_vitals) {
        // Temperature
        $temp = floatval($latest_vitals['temperature'] ?? 0);
        if ($temp > 0) {
            $vitals_assessment['temperature'] = [
                'value' => $temp,
                'unit' => '°C',
                'status' => ($temp >= 36.1 && $temp <= 37.2) ? 'normal' : 
                           (($temp < 35.0 || $temp > 38.0) ? 'critical' : 'warning'),
                'label' => ($temp >= 36.1 && $temp <= 37.2) ? 'Normal' : 
                          (($temp < 35.0 || $temp > 38.0) ? 'Abnormal' : 'Elevated')
            ];
        }
        
        // Blood Pressure
        if ($latest_vitals['blood_pressure_systolic'] && $latest_vitals['blood_pressure_diastolic']) {
            $bp_sys = intval($latest_vitals['blood_pressure_systolic']);
            $bp_dia = intval($latest_vitals['blood_pressure_diastolic']);
            $vitals_assessment['blood_pressure'] = [
                'value' => $bp_sys . '/' . $bp_dia,
                'systolic' => $bp_sys,
                'diastolic' => $bp_dia,
                'unit' => 'mmHg',
                'status' => ($bp_sys <= 120 && $bp_dia <= 80) ? 'normal' : 
                           (($bp_sys <= 130 && $bp_dia <= 85) ? 'warning' : 'critical'),
                'label' => ($bp_sys <= 120 && $bp_dia <= 80) ? 'Normal' : 
                          (($bp_sys <= 130 && $bp_dia <= 85) ? 'Elevated' : 'High')
            ];
        }
        
        // Heart Rate
        $hr = floatval($latest_vitals['heart_rate'] ?? 0);
        if ($hr > 0) {
            $vitals_assessment['heart_rate'] = [
                'value' => $hr,
                'unit' => 'bpm',
                'status' => ($hr >= 60 && $hr <= 100) ? 'normal' : 
                           (($hr > 40 && $hr < 60) || ($hr > 100 && $hr < 120) ? 'warning' : 'critical'),
                'label' => ($hr >= 60 && $hr <= 100) ? 'Normal' : 
                          (($hr > 40 && $hr < 60) || ($hr > 100 && $hr < 120) ? 'Abnormal' : 'Critical')
            ];
        }
        
        // Oxygen Saturation
        $spo2 = floatval($latest_vitals['oxygen_saturation'] ?? 0);
        if ($spo2 > 0) {
            $vitals_assessment['oxygen_saturation'] = [
                'value' => $spo2,
                'unit' => '%',
                'status' => ($spo2 >= 95) ? 'normal' : (($spo2 >= 90) ? 'warning' : 'critical'),
                'label' => ($spo2 >= 95) ? 'Normal' : (($spo2 >= 90) ? 'Low' : 'Critical')
            ];
        }
        
        // BMI
        if ($bmi) {
            $vitals_assessment['bmi'] = [
                'value' => $bmi,
                'category' => $bmi_category,
                'status' => ($bmi >= 18.5 && $bmi < 25) ? 'normal' : 'warning',
                'height' => $latest_vitals['height'],
                'weight' => $latest_vitals['weight']
            ];
        }
    }
    
    // ===== QUICK ACTIONS FOR MOBILE =====
    $quick_actions = [
        [
            'id' => 'book_appointment',
            'title' => 'Book Appointment',
            'icon' => 'calendar-plus',
            'color' => '#3b82f6',
            'route' => 'BookAppointment'
        ],
        [
            'id' => 'view_records',
            'title' => 'Medical Records',
            'icon' => 'file-medical',
            'color' => '#10b981',
            'route' => 'MedicalRecords'
        ],
        [
            'id' => 'lab_results',
            'title' => 'Lab Results',
            'icon' => 'flask',
            'color' => '#f59e0b',
            'route' => 'LabResults'
        ],
        [
            'id' => 'medications',
            'title' => 'Medications',
            'icon' => 'pills',
            'color' => '#8b5cf6',
            'route' => 'Medications'
        ],
        [
            'id' => 'emergency_sos',
            'title' => 'Emergency SOS',
            'icon' => 'ambulance',
            'color' => '#ef4444',
            'route' => 'EmergencySOS'
        ],
        [
            'id' => 'edit_profile',
            'title' => 'Edit Profile',
            'icon' => 'user-edit',
            'color' => '#64748b',
            'route' => 'EditProfile'
        ]
    ];
    
    // ===== RETURN COMPREHENSIVE JSON RESPONSE =====
    $response = [
        'success' => true,
        'patient' => $patient_data,
        'stats' => $stats,
        'profile_complete' => $profile_complete,
        'missing_fields' => $missing_fields,
        'latest_vitals' => $latest_vitals,
        'vitals_assessment' => $vitals_assessment,
        'bmi' => $bmi,
        'bmi_category' => $bmi_category,
        'upcoming_appointments' => $formatted_appointments,
        'current_medications' => $formatted_medications,
        'recent_lab_orders' => $formatted_lab_orders,
        'health_metrics' => $health_metrics,
        'quick_actions' => $quick_actions,
        'timestamp' => date('Y-m-d H:i:s'),
        'has_patient_record' => $patient ? true : false
    ];
    
    error_log("Mobile Dashboard response prepared successfully");
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Mobile Dashboard error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>