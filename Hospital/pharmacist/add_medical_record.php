<?php
// CRITICAL: Start output buffering at the very first line to fix header error
ob_start();

// /**
//  * Get all doctors from the users table
//  * Can be used in both staff management and appointment forms
//  */
// function getDoctorsForDropdown($pdo) {
//     try {
//         $stmt = $pdo->prepare("
//             SELECT id, 
//                    CONCAT(first_name, ' ', last_name) as full_name,
//                    COALESCE(specialty, department) as specialty,
//                    department,
//                    email,
//                    phone
//             FROM users 
//             WHERE role = 'Doctor' AND status = 'active'
//             ORDER BY first_name ASC, last_name ASC
//         ");
//         $stmt->execute();
//         return $stmt->fetchAll(PDO::FETCH_ASSOC);
//     } catch (Exception $e) {
//         error_log('Error fetching doctors: ' . $e->getMessage());
//         return [];
//     }
// }

session_start();
include "includes/db_connect.php";

// Get patient ID from URL or session
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : (isset($_SESSION['current_patient_id']) ? $_SESSION['current_patient_id'] : null);

$patient_data = null;
$vitals_data = [];
$medications_data = [];
$lab_results_data = [];
$clinical_notes_data = [];
$appointments_data = [];

// Initialize notification variables
$notification_message = '';
$notification_type = '';

// Check for session notifications
if (isset($_SESSION['notification'])) {
    $notification_message = $_SESSION['notification'];
    $notification_type = $_SESSION['notification_type'];
    unset($_SESSION['notification']);
    unset($_SESSION['notification_type']);
}

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'patient-info';

if ($patient_id) {
    // Fetch patient basic information using patient_id field
    $stmt = $pdo->prepare("
        SELECT *, 
               CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) as full_name,
               TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as calculated_age
        FROM patients 
        WHERE patient_id = ? OR id = ?
    ");
    $stmt->execute([$patient_id, $patient_id]);
    $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient_data) {
        // Store current patient in session
        $_SESSION['current_patient_id'] = $patient_data['patient_id'];
        
        // Fetch vitals data from patient_vitals table
        $vitals_stmt = $pdo->prepare("
            SELECT pv.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as recorded_by_name
            FROM patient_vitals pv 
            LEFT JOIN users u ON pv.recorded_by = u.id 
            WHERE pv.patient_id = ? 
            ORDER BY pv.recorded_at DESC
            LIMIT 10
        ");
        $vitals_stmt->execute([$patient_data['id']]);
        $vitals_data = $vitals_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch medications from patient_medications table
        $meds_stmt = $pdo->prepare("
            SELECT pm.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as doctor_name
            FROM patient_medications pm 
            LEFT JOIN users u ON pm.prescribing_doctor = u.id 
            WHERE pm.patient_id = ? 
            ORDER BY pm.start_date DESC
        ");
        $meds_stmt->execute([$patient_data['id']]);
        $medications_data = $meds_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch lab results from lab_results table
        $labs_stmt = $pdo->prepare("
            SELECT lr.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as technician_name
            FROM lab_results lr 
            LEFT JOIN users u ON lr.recorded_by = u.id 
            WHERE lr.patient_id = ? 
            ORDER BY lr.performed_date DESC
        ");
        $labs_stmt->execute([$patient_data['id']]);
        $lab_results_data = $labs_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch clinical notes from medical_records table
        $notes_stmt = $pdo->prepare("
            SELECT mr.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                   mr.diagnosis as assessment,
                   mr.treatment as plan
            FROM medical_records mr 
            LEFT JOIN users u ON mr.doctor_id = u.id 
            WHERE mr.patient_id = ? AND mr.record_type IN ('consultation', 'note')
            ORDER BY mr.record_date DESC
        ");
        $notes_stmt->execute([$patient_data['id']]);
        $clinical_notes_data = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch appointments from appointments table
        $appts_stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                   a.reason,
                   a.appointment_date,
                   a.appointment_time
            FROM appointments a 
            LEFT JOIN users u ON a.doctor_id = u.id 
            WHERE a.patient_id = ? 
            ORDER BY a.appointment_date DESC
        ");
        $appts_stmt->execute([$patient_data['id']]);
        $appointments_data = $appts_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Function to generate unique record number
function generateRecordNumber($pdo) {
    $date_prefix = date('YmdH'); // Format: YYYYMMDDHH
    $random_suffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $record_number = $date_prefix . '-' . $random_suffix;
    
    // Check if this record number already exists
    $check_stmt = $pdo->prepare("SELECT id FROM medical_records WHERE record_number = ?");
    $check_stmt->execute([$record_number]);
    
    // If it exists, generate a new one recursively
    if ($check_stmt->fetch()) {
        return generateRecordNumber($pdo);
    }
    
    return $record_number;
}

// Updated Function to save lab request
function saveLabRequest($pdo, $patient_id, $data) {
    try {
        // Generate unique record number
        $record_number = generateRecordNumber($pdo);
        
        $record_stmt = $pdo->prepare("
            INSERT INTO medical_records 
            (patient_id, doctor_id, record_type, record_date, record_number, department, notes, status, created_by) 
            VALUES (?, ?, 'lab_result', CURDATE(), ?, ?, ?, 'completed', ?)
        ");
        
        $doctor_id = $_SESSION['user_id'] ?? 3;
        $department = 'Laboratory';
        
        $record_stmt->execute([
            $patient_id,
            $doctor_id,
            $record_number,
            $department,
            $data['notes'] ?? 'Lab test requested',
            $_SESSION['user_id'] ?? 1
        ]);
        
        $record_id = $pdo->lastInsertId();
        
        // Create lab result entry
        $lab_stmt = $pdo->prepare("
            INSERT INTO lab_results 
            (record_id, patient_id, test_name, test_category, performed_date, status, recorded_by) 
            VALUES (?, ?, ?, ?, CURDATE(), 'pending', ?)
        ");
        
        $lab_stmt->execute([
            $record_id,
            $patient_id,
            $data['test_name'],
            $data['test_category'] ?? 'General',
            $_SESSION['user_id'] ?? 1
        ]);
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Error creating lab request: ' . $e->getMessage());
    }
}

// Updated Function to save clinical note with unique record number
function saveClinicalNote($pdo, $patient_id, $data) {
    try {
        // Generate unique record number
        $record_number = generateRecordNumber($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO medical_records 
            (patient_id, doctor_id, record_type, record_date, record_number, department, 
             diagnosis, treatment, notes, status, created_by) 
            VALUES (?, ?, 'consultation', CURDATE(), ?, ?, ?, ?, ?, 'completed', ?)
        ");
        
        $doctor_id = $_SESSION['user_id'] ?? 3;
        $department = $_SESSION['department'] ?? 'General';
        
        $diagnosis = $data['assessment'] ?? '';
        $treatment = $data['plan'] ?? '';
        $notes = "Subjective: " . ($data['subjective'] ?? '') . "\n" .
                 "Objective: " . ($data['objective'] ?? '');
        
        $stmt->execute([
            $patient_id,
            $doctor_id,
            $record_number,
            $department,
            $diagnosis,
            $treatment,
            $notes,
            $_SESSION['user_id'] ?? 1
        ]);
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Error creating clinical note: ' . $e->getMessage());
    }
}

// Alternative: If you want to let the database auto-generate, modify your medical_records table:
/*
ALTER TABLE medical_records MODIFY COLUMN record_number VARCHAR(50) UNIQUE DEFAULT NULL;

Or add this trigger to auto-generate unique record numbers:

DELIMITER //
CREATE TRIGGER generate_record_number_before_insert
BEFORE INSERT ON medical_records
FOR EACH ROW
BEGIN
    IF NEW.record_number IS NULL THEN
        SET NEW.record_number = CONCAT(DATE_FORMAT(NOW(), '%Y%m%d%H'), '-', LPAD(FLOOR(RAND() * 10000), 4, '0'));
    END IF;
END //
DELIMITER ;
*/

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient_data) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'save_vitals':
                    savePatientVitals($pdo, $patient_data['id'], $_POST);
                    $_SESSION['notification'] = 'Vital signs recorded successfully!';
                    $_SESSION['notification_type'] = 'success';
                    $redirect_tab = 'vitals';
                    break;
                    
                case 'save_medication':
                    savePatientMedication($pdo, $patient_data['id'], $_POST);
                    $_SESSION['notification'] = 'Medication prescribed successfully!';
                    $_SESSION['notification_type'] = 'success';
                    $redirect_tab = 'prescriptions';
                    break;
                    
                case 'save_lab_request':
                    saveLabRequest($pdo, $patient_data['id'], $_POST);
                    $_SESSION['notification'] = 'Lab test ordered successfully!';
                    $_SESSION['notification_type'] = 'success';
                    $redirect_tab = 'labs';
                    break;
                    
                case 'save_clinical_note':
                    saveClinicalNote($pdo, $patient_data['id'], $_POST);
                    $_SESSION['notification'] = 'Clinical note saved successfully!';
                    $_SESSION['notification_type'] = 'success';
                    $redirect_tab = 'clinical-notes';
                    break;
                    
                case 'save_appointment':
                    saveAppointment($pdo, $patient_data['id'], $_POST);
                    $_SESSION['notification'] = 'Appointment scheduled successfully!';
                    $_SESSION['notification_type'] = 'success';
                    $redirect_tab = 'appointments';
                    break;
            }
            
            // Redirect to prevent form resubmission
            header("Location: add_medical_record.php?patient_id=" . urlencode($patient_data['patient_id']) . "&tab=" . $redirect_tab);
            exit();
            
        } catch (Exception $e) {
            $_SESSION['notification'] = 'Error: ' . $e->getMessage();
            $_SESSION['notification_type'] = 'error';
            header("Location: add_medical_record.php?patient_id=" . urlencode($patient_data['patient_id']));
            exit();
        }
    }
}

// Function to save vitals
function savePatientVitals($pdo, $patient_id, $data) {
    // Calculate BMI
    $bmi = null;
    if (!empty($data['weight']) && !empty($data['height'])) {
        $height_m = $data['height'] / 100;
        $bmi = round($data['weight'] / ($height_m * $height_m), 2);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO patient_vitals 
        (patient_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, 
         temperature, respiratory_rate, oxygen_saturation, weight, height, bmi, recorded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $recorded_by = $_SESSION['user_id'] ?? 1;
    
    $stmt->execute([
        $patient_id,
        $data['blood_pressure_systolic'] ?? null,
        $data['blood_pressure_diastolic'] ?? null,
        $data['heart_rate'] ?? null,
        $data['temperature'] ?? null,
        $data['respiratory_rate'] ?? null,
        $data['oxygen_saturation'] ?? null,
        $data['weight'] ?? null,
        $data['height'] ?? null,
        $bmi,
        $recorded_by
    ]);
    
    // Update patient's height and weight if provided
    if (!empty($data['weight']) || !empty($data['height'])) {
        $update_stmt = $pdo->prepare("
            UPDATE patients SET 
            weight = COALESCE(?, weight),
            height = COALESCE(?, height),
            updated_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$data['weight'] ?? null, $data['height'] ?? null, $patient_id]);
    }
    
    return true;
}

// Function to save medication
function savePatientMedication($pdo, $patient_id, $data) {
    $stmt = $pdo->prepare("
        INSERT INTO patient_medications 
        (patient_id, medication_name, dosage, frequency, route, duration, 
         start_date, end_date, prescribing_doctor, instructions, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    $prescribing_doctor = $_SESSION['user_id'] ?? 3;
    
    $stmt->execute([
        $patient_id,
        $data['medication_name'],
        $data['dosage'],
        $data['frequency'],
        $data['route'] ?? 'oral',
        $data['duration'],
        $data['start_date'],
        $data['end_date'] ?? null,
        $prescribing_doctor,
        $data['instructions'] ?? ''
    ]);
    
    return true;
}

// Function to save appointment
/**
 * Updated saveAppointment function - uses doctor_id directly like your scheduling page
 */
function saveAppointment($pdo, $patient_id, $data) {
    try {
        // Validate that doctor_id exists in users table with role 'Doctor'
        $doctor_stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE id = ? AND role = 'Doctor' AND status = 'active'
            LIMIT 1
        ");
        $doctor_stmt->execute([$data['doctor_id']]);
        $doctor = $doctor_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doctor) {
            throw new Exception('Selected doctor is not available. Please select another doctor.');
        }
        
        // Insert appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments 
            (patient_id, doctor_id, appointment_date, appointment_time, 
             appointment_type, reason, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        
        $stmt->execute([
            $patient_id,
            $data['doctor_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $data['appointment_type'],
            $data['reason'] ?? '',
            $_SESSION['user_id'] ?? 1
        ]);
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Error scheduling appointment: ' . $e->getMessage());
    }
}


// Now include sidebar - after all processing logic
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient EMR - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* All previous CSS remains the same */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Notification Styles - NEW */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease-out;
        }
        
        .notification.success {
            background-color: #10b981;
            color: white;
        }
        
        .notification.error {
            background-color: #ef4444;
            color: white;
        }
        
        .notification.warning {
            background-color: #f59e0b;
            color: white;
        }
        
        .notification.info {
            background-color: #3b82f6;
            color: white;
        }
        
        .notification-icon {
            font-size: 1.5rem;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .notification-close:hover {
            opacity: 1;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .notification.hide {
            animation: slideOut 0.3s ease-out forwards;
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            color: #64748b;
            text-decoration: none;
            font-size: 1.5rem;
            margin-right: 1.5rem;
            transition: color 0.2s;
        }

        .header-text {
            flex-grow: 1;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .patient-actions {
            display: flex;
            gap: 0.75rem;
        }

        .patient-banner {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }

        .patient-meta {
            display: flex;
            gap: 1.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        .search-container {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            gap: 2rem;
            overflow-x: auto;
        }

        .tab-button {
            padding: 0.75rem 0;
            background: none;
            border: none;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .tab-button.active {
            color: #0f172a;
            font-weight: 600;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #3b82f6;
        }

        .tab-content {
            display: none;
            padding: 2rem 1.5rem;
        }

        .tab-content.active {
            display: block;
        }

        .tab-section-header {
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .tab-section-header:first-of-type {
            margin-top: 0;
        }

        .tab-section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-grid-3-col {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-grid-2-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #1f2937;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: #0f172a;
            color: white;
        }

        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .no-patient-selected {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-scheduled {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .vitals-timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .vital-entry {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
        }

        .vital-metrics {
            display: flex;
            gap: 2rem;
        }

        .vital-metric {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .vital-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .vital-label {
            font-size: 0.75rem;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .form-grid-3-col, .form-grid-2-col {
                grid-template-columns: 1fr;
            }
            .patient-banner {
                flex-direction: column;
                text-align: center;
            }
            .patient-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            .notification {
                right: 10px;
                left: 10px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>

<!-- Notification Display - NEW -->
<?php if (!empty($notification_message)): ?>
    <div class="notification <?php echo htmlspecialchars($notification_type); ?>" id="notification">
        <div class="notification-icon">
            <?php if ($notification_type === 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php elseif ($notification_type === 'error'): ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php elseif ($notification_type === 'warning'): ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php else: ?>
                <i class="fas fa-info-circle"></i>
            <?php endif; ?>
        </div>
        <div class="notification-content">
            <?php echo htmlspecialchars($notification_message); ?>
        </div>
        <button class="notification-close" onclick="closeNotification()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<div class="main-content">
    <div class="page-header">
        <a href="patients.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-text">
            <h1>Electronic Medical Record</h1>
            <p>View and manage patient medical records</p>
        </div>
        <div class="patient-actions">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Patient Search -->
    <div class="search-container">
        <form method="GET" class="search-form">
            <div class="form-group">
                <label class="form-label">Search Patient</label>
                <input type="text" name="search" class="form-input" placeholder="Enter patient name or ID" 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <div class="form-group" style="max-width: 200px;">
                <label class="form-label">Search By</label>
                <select name="search_type" class="form-select">
                    <option value="name" <?php echo ($_GET['search_type'] ?? 'name') === 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="id" <?php echo ($_GET['search_type'] ?? '') === 'id' ? 'selected' : ''; ?>>Patient ID</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>

        <?php
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $search_type = $_GET['search_type'] ?? 'name';
            
            if ($search_type === 'name') {
                $search_stmt = $pdo->prepare("
                    SELECT *, CONCAT(first_name, ' ', last_name) as full_name 
                    FROM patients 
                    WHERE CONCAT(first_name, ' ', last_name) LIKE ? 
                    OR patient_id LIKE ?
                    LIMIT 10
                ");
                $search_term = "%$search%";
                $search_stmt->execute([$search_term, $search_term]);
            } else {
                $search_stmt = $pdo->prepare("
                    SELECT *, CONCAT(first_name, ' ', last_name) as full_name 
                    FROM patients 
                    WHERE patient_id = ?
                ");
                $search_stmt->execute([$search]);
            }
            
            $search_results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($search_results) {
                echo '<div style="margin-top: 1rem;">';
                echo '<h3 style="margin-bottom: 0.5rem; font-size: 1rem;">Search Results:</h3>';
                foreach ($search_results as $result) {
                    echo '<div style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">';
                    echo '<div>';
                    echo '<strong>' . htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) . '</strong>';
                    echo '<div style="font-size: 0.875rem; color: #64748b;">';
                    echo 'ID: ' . htmlspecialchars($result['patient_id']) . ' | ';
                    echo 'Age: ' . $result['age'] . ' | ';
                    echo 'Gender: ' . $result['gender'];
                    echo '</div>';
                    echo '</div>';
                    echo '<a href="?patient_id=' . $result['patient_id'] . '" class="btn btn-primary" style="padding: 0.5rem 1rem;">Select</a>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div style="margin-top: 1rem; color: #ef4444;">No patients found matching your search.</div>';
            }
        }
        ?>
    </div>

    <?php if (!$patient_data): ?>
        <div class="no-patient-selected">
            <i class="fas fa-user-injured" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <h2>No Patient Selected</h2>
            <p>Please search for a patient to view their electronic medical record.</p>
        </div>
    <?php else: ?>
        <!-- Patient Banner -->
        <div class="patient-banner">
            <div class="patient-avatar">
                <?php 
                $initials = substr($patient_data['first_name'], 0, 1) . substr($patient_data['last_name'], 0, 1);
                echo strtoupper($initials);
                ?>
            </div>
            <div class="patient-details">
                <h2><?php echo htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']); ?></h2>
                <div class="patient-meta">
                    <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($patient_data['patient_id']); ?></span>
                    <span><i class="fas fa-birthday-cake"></i> <?php echo $patient_data['calculated_age']; ?> years old</span>
                    <span><i class="fas fa-venus-mars"></i> <?php echo $patient_data['gender']; ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient_data['phone']); ?></span>
                    <span><i class="fas fa-tint"></i> Blood Type: <?php echo $patient_data['blood_type'] ?? 'Not specified'; ?></span>
                </div>
            </div>
        </div>

        <div class="form-container">
            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button type="button" class="tab-button <?php echo $active_tab === 'patient-info' ? 'active' : ''; ?>" data-tab="patient-info">
                    <i class="fas fa-user"></i> Patient Info
                </button>
                <button type="button" class="tab-button <?php echo $active_tab === 'vitals' ? 'active' : ''; ?>" data-tab="vitals">
                    <i class="fas fa-heartbeat"></i> Vitals
                </button>
                <button type="button" class="tab-button <?php echo $active_tab === 'clinical-notes' ? 'active' : ''; ?>" data-tab="clinical-notes">
                    <i class="fas fa-file-medical"></i> Clinical Notes
                </button>
                <button type="button" class="tab-button <?php echo $active_tab === 'labs' ? 'active' : ''; ?>" data-tab="labs">
                    <i class="fas fa-flask"></i> Lab 
                </button>
                <button type="button" class="tab-button <?php echo $active_tab === 'prescriptions' ? 'active' : ''; ?>" data-tab="prescriptions">
                    <i class="fas fa-pills"></i> Medications
                </button>
                <!-- <button type="button" class="tab-button <?php echo $active_tab === 'appointments' ? 'active' : ''; ?>" data-tab="appointments">
                    <i class="fas fa-calendar-check"></i> Appointments
                </button> -->
            </div>

            <!-- Patient Info Tab -->
            <div class="tab-content <?php echo $active_tab === 'patient-info' ? 'active' : ''; ?>" id="patient-info-tab">
                <div class="tab-section-header">
                    <h2>Demographic Information</h2>
                    <p>Basic patient details and contact information</p>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['first_name']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['last_name']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Patient ID</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['patient_id']); ?>" readonly>
                    </div>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="text" class="form-input" value="<?php echo $patient_data['date_of_birth']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age</label>
                        <input type="text" class="form-input" value="<?php echo $patient_data['calculated_age']; ?> years" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <input type="text" class="form-input" value="<?php echo $patient_data['gender']; ?>" readonly>
                    </div>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['phone']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['email'] ?? 'Not specified'); ?>" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea class="form-textarea" readonly><?php echo htmlspecialchars($patient_data['address'] ?? 'Not specified'); ?></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Medical Information</h2>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Blood Type</label>
                        <input type="text" class="form-input" value="<?php echo $patient_data['blood_type'] ?? 'Not specified'; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height</label>
                        <input type="text" class="form-input" value="<?php echo $patient_data['height'] ? $patient_data['height'] . ' cm' : 'Not recorded'; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Weight</label>
                        <input type="text" class="form-input" value="<?php echo $patient_data['weight'] ? $patient_data['weight'] . ' kg' : 'Not recorded'; ?>" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Allergies</label>
                    <textarea class="form-textarea" readonly><?php echo htmlspecialchars($patient_data['allergies'] ?? 'No known allergies'); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Current Medications</label>
                    <textarea class="form-textarea" readonly><?php echo htmlspecialchars($patient_data['current_medications'] ?? 'No current medications'); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Medical History</label>
                    <textarea class="form-textarea" readonly><?php echo htmlspecialchars($patient_data['medical_history'] ?? 'No medical history recorded'); ?></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Insurance Information</h2>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Insurance Provider</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['insurance_provider'] ?? 'Not specified'); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Insurance ID</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient_data['insurance_id'] ?? 'Not specified'); ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- Vitals Tab -->
            <div class="tab-content <?php echo $active_tab === 'vitals' ? 'active' : ''; ?>" id="vitals-tab">
                <div class="tab-section-header">
                    <h2>Vital Signs</h2>
                    <p>Record and monitor patient vital signs</p>
                </div>

                <form method="POST" id="vitalsForm">
                    <input type="hidden" name="action" value="save_vitals">
                    <div class="form-grid-3-col">
                        <div class="form-group">
                            <label class="form-label">Blood Pressure (Systolic)</label>
                            <input type="number" name="blood_pressure_systolic" class="form-input" placeholder="120" min="50" max="250">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Blood Pressure (Diastolic)</label>
                            <input type="number" name="blood_pressure_diastolic" class="form-input" placeholder="80" min="30" max="150">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Heart Rate (BPM)</label>
                            <input type="number" name="heart_rate" class="form-input" placeholder="72" min="30" max="200">
                        </div>
                    </div>

                    <div class="form-grid-3-col">
                        <div class="form-group">
                            <label class="form-label">Temperature (°C)</label>
                            <input type="number" name="temperature" class="form-input" placeholder="36.6" step="0.1" min="34" max="42">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Respiratory Rate</label>
                            <input type="number" name="respiratory_rate" class="form-input" placeholder="16" min="6" max="60">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Oxygen Saturation (%)</label>
                            <input type="number" name="oxygen_saturation" class="form-input" placeholder="98" min="70" max="100">
                        </div>
                    </div>

                    <div class="form-grid-3-col">
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-input" placeholder="70.5" step="0.1" value="<?php echo $patient_data['weight'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" name="height" class="form-input" placeholder="175.0" step="0.1" value="<?php echo $patient_data['height'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">BMI</label>
                            <input type="number" id="bmi_calculated" class="form-input" readonly>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Vitals
                    </button>
                </form>

                <div class="tab-section-header">
                    <h2>Recent Vital Signs</h2>
                </div>

                <div class="vitals-timeline">
                    <?php if (empty($vitals_data)): ?>
                        <div style="text-align: center; padding: 2rem; color: #64748b;">
                            <i class="fas fa-heartbeat" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>No vital signs recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vitals_data as $vital): ?>
                            <div class="vital-entry">
                                <div class="vital-metrics">
                                    <div class="vital-metric">
                                        <span class="vital-value"><?php echo ($vital['blood_pressure_systolic'] ?? '--') . '/' . ($vital['blood_pressure_diastolic'] ?? '--'); ?></span>
                                        <span class="vital-label">BP</span>
                                    </div>
                                    <div class="vital-metric">
                                        <span class="vital-value"><?php echo $vital['heart_rate'] ?? '--'; ?></span>
                                        <span class="vital-label">HR</span>
                                    </div>
                                    <div class="vital-metric">
                                        <span class="vital-value"><?php echo $vital['temperature'] ?? '--'; ?></span>
                                        <span class="vital-label">Temp</span>
                                    </div>
                                    <div class="vital-metric">
                                        <span class="vital-value"><?php echo $vital['oxygen_saturation'] ? $vital['oxygen_saturation'] . '%' : '--'; ?></span>
                                        <span class="vital-label">SpO2</span>
                                    </div>
                                </div>
                                <div class="vital-meta">
                                    <span style="color: #64748b; font-size: 0.875rem;">
                                        <?php echo date('M j, Y g:i A', strtotime($vital['recorded_at'])); ?>
                                    </span>
                                    <span style="color: #64748b; font-size: 0.875rem;">
                                        By: <?php echo $vital['recorded_by_name'] ?? 'System'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Medications Tab -->
            <div class="tab-content <?php echo $active_tab === 'prescriptions' ? 'active' : ''; ?>" id="prescriptions-tab">
                <div class="tab-section-header">
                    <h2>Current Medications</h2>
                    <p>Active prescriptions and medication history</p>
                </div>

                <?php if (empty($medications_data)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-pills" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>No medications prescribed yet.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Route</th>
                                <th>Start Date</th>
                                <th>Prescribed By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medications_data as $med): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                    <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                                    <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                                    <td><?php echo ucfirst($med['route'] ?? 'oral'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($med['start_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($med['doctor_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($med['status']); ?>">
                                            <?php echo ucfirst($med['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="tab-section-header">
                    <h2>New Medication</h2>
                </div>

                <form method="POST" id="medicationForm">
                    <input type="hidden" name="action" value="save_medication">
                    <div class="form-grid-3-col">
                        <div class="form-group">
                            <label class="form-label">Medication Name</label>
                            <input type="text" name="medication_name" class="form-input" placeholder="e.g., Amoxicillin" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dosage</label>
                            <input type="text" name="dosage" class="form-input" placeholder="e.g., 500mg" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Frequency</label>
                            <select name="frequency" class="form-select" required>
                                <option value="">Select frequency</option>
                                <option value="Once daily">Once daily</option>
                                <option value="Twice daily">Twice daily</option>
                                <option value="Three times daily">Three times daily</option>
                                <option value="Four times daily">Four times daily</option>
                                <option value="As needed">As needed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-3-col">
                        <div class="form-group">
                            <label class="form-label">Route</label>
                            <select name="route" class="form-select">
                                <option value="oral">Oral</option>
                                <option value="injection">Injection</option>
                                <option value="topical">Topical</option>
                                <option value="intravenous">Intravenous</option>
                                <option value="inhalation">Inhalation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-input" placeholder="e.g., 7 days" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instructions</label>
                        <textarea name="instructions" class="form-textarea" placeholder="Special instructions for the patient"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-prescription"></i> Prescribe Medication
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"><a href="ehr_system.php"></a></i> Save Session
                    </button>
                </form>
            </div>

            <!-- Lab Results Tab -->
            <div class="tab-content <?php echo $active_tab === 'labs' ? 'active' : ''; ?>" id="labs-tab">
                <div class="tab-section-header">
                    <h2>Lab Results</h2>
                    <p>Laboratory test results and reports</p>
                </div>

                <?php if (empty($lab_results_data)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-flask" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>No lab results available.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th>Category</th>
                                <th>Result</th>
                                <th>Status</th>
                                <th>Performed Date</th>
                                <th>Technician</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_results_data as $lab): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lab['test_name']); ?></td>
                                    <td><?php echo htmlspecialchars($lab['test_category'] ?? 'General'); ?></td>
                                    <td>
                                        <?php if ($lab['result_value']): ?>
                                            <strong><?php echo htmlspecialchars($lab['result_value']); ?></strong>
                                            <?php if ($lab['unit']): ?>
                                                <?php echo htmlspecialchars($lab['unit']); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #64748b;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($lab['status']); ?>">
                                            <?php echo ucfirst($lab['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $lab['performed_date'] ? date('M j, Y', strtotime($lab['performed_date'])) : 'Pending'; ?></td>
                                    <td><?php echo htmlspecialchars($lab['technician_name'] ?? 'Unknown'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="tab-section-header">
                    <h2>New Lab Request</h2>
                </div>

                <form method="POST" id="labForm">
                    <input type="hidden" name="action" value="save_lab_request">
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Test Name</label>
                            <select name="test_name" class="form-select" required>
                                <option value="">Select test type</option>
                                <option value="Complete Blood Count">Complete Blood Count</option>
                                <option value="Basic Metabolic Panel">Basic Metabolic Panel</option>
                                <option value="Liver Function Test">Liver Function Test</option>
                                <option value="Lipid Panel">Lipid Panel</option>
                                <option value="HbA1c">HbA1c</option>
                                <option value="Urinalysis">Urinalysis</option>
                                <option value="Thyroid Function Test">Thyroid Function Test</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Test Category</label>
                            <input type="text" name="test_category" class="form-input" placeholder="e.g., Hematology">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-textarea" placeholder="Additional notes or instructions"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Order Lab Test
                    </button>
                </form>
            </div>

            <!-- Clinical Notes Tab -->
            <div class="tab-content <?php echo $active_tab === 'clinical-notes' ? 'active' : ''; ?>" id="clinical-notes-tab">
                <div class="tab-section-header">
                    <h2>Clinical Notes</h2>
                    <p>Patient assessment, diagnosis, and treatment plans</p>
                </div>

                <form method="POST" id="clinicalNoteForm">
                    <input type="hidden" name="action" value="save_clinical_note">
                    <div class="form-group">
                        <label class="form-label">Subjective</label>
                        <textarea name="subjective" class="form-textarea" placeholder="Patient's complaints and history"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Objective</label>
                        <textarea name="objective" class="form-textarea" placeholder="Physical exam findings, vital signs, test results"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Assessment</label>
                        <textarea name="assessment" class="form-textarea" placeholder="Diagnosis, differential diagnosis"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Plan</label>
                        <textarea name="plan" class="form-textarea" placeholder="Treatment plan, medications, follow-up"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Clinical Note
                    </button>
                </form>

                <div class="tab-section-header">
                    <h2>Previous Notes</h2>
                </div>

                <?php if (empty($clinical_notes_data)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-file-medical" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>No clinical notes recorded yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($clinical_notes_data as $note): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <strong style="display: block;">Dr. <?php echo htmlspecialchars($note['doctor_name']); ?></strong>
                                    <span style="color: #64748b; font-size: 0.875rem;">
                                        <?php echo date('M j, Y', strtotime($note['record_date'])); ?>
                                    </span>
                                </div>
                                <span class="status-badge status-<?php echo strtolower($note['status']); ?>">
                                    <?php echo ucfirst($note['status']); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($note['notes'])): ?>
                                <div style="margin-bottom: 1rem;">
                                    <strong style="display: block; color: #374151; margin-bottom: 0.5rem;">Notes:</strong>
                                    <p style="color: #64748b; white-space: pre-line;"><?php echo htmlspecialchars($note['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($note['assessment'])): ?>
                                <div style="margin-bottom: 1rem;">
                                    <strong style="display: block; color: #374151; margin-bottom: 0.5rem;">Assessment:</strong>
                                    <p style="color: #64748b;"><?php echo nl2br(htmlspecialchars($note['assessment'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($note['plan'])): ?>
                                <div>
                                    <strong style="display: block; color: #374151; margin-bottom: 0.5rem;">Plan:</strong>
                                    <p style="color: #64748b;"><?php echo nl2br(htmlspecialchars($note['plan'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

           

<!-- Appointments Tab -->
<div class="tab-content <?php echo $active_tab === 'appointments' ? 'active' : ''; ?>" id="appointments-tab">
    <div class="tab-section-header">
        <h2>Appointments</h2>
        <p>Schedule and manage patient appointments</p>
    </div>

    <?php if (empty($appointments_data)): ?>
        <div style="text-align: center; padding: 2rem; color: #64748b;">
            <i class="fas fa-calendar-check" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <p>No appointments scheduled.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Doctor</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments_data as $appt): ?>
                    <tr>
                        <td>
                            <?php echo date('M j, Y', strtotime($appt['appointment_date'])); ?>
                            <?php echo $appt['appointment_time'] ? ' at ' . date('g:i A', strtotime($appt['appointment_time'])) : ''; ?>
                        </td>
                        <td>Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $appt['appointment_type'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($appt['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $appt['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($appt['reason'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="tab-section-header">
        <h2>Schedule New Appointment</h2>
    </div>

    <form method="POST" id="appointmentForm">
        <input type="hidden" name="action" value="save_appointment">
        
        <div class="form-grid-2-col">
            <div class="form-group">
                <label class="form-label">Date *</label>
                <input type="date" name="appointment_date" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Time *</label>
                <input type="time" name="appointment_time" class="form-input" required>
            </div>
        </div>

        <div class="form-grid-2-col">
            <div class="form-group">
                <label class="form-label">Doctor/Provider *</label>
                <select name="doctor_id" class="form-select" required id="doctorSelect">
                    <option value="">-- Select a doctor --</option>
                    <?php 
                        try {
                            // Query active doctors from users table
                            $doctors_stmt = $pdo->prepare("
                                SELECT id, 
                                       CONCAT(first_name, ' ', last_name) as full_name,
                                       COALESCE(specialty, department) as specialty,
                                       email,
                                       phone
                                FROM users 
                                WHERE role = 'Doctor' AND status = 'active'
                                ORDER BY first_name ASC, last_name ASC
                            ");
                            $doctors_stmt->execute();
                            $doctors = $doctors_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($doctors)):
                    ?>
                                <option value="" disabled>No doctors available</option>
                    <?php 
                            else:
                                foreach ($doctors as $doctor):
                                    $display_text = 'Dr. ' . htmlspecialchars($doctor['full_name']);
                                    if (!empty($doctor['specialty'])):
                                        $display_text .= ' (' . htmlspecialchars($doctor['specialty']) . ')';
                                    endif;
                    ?>
                        <option value="<?php echo $doctor['id']; ?>"
                                data-email="<?php echo htmlspecialchars($doctor['email']); ?>"
                                data-phone="<?php echo htmlspecialchars($doctor['phone']); ?>">
                            <?php echo $display_text; ?>
                        </option>
                    <?php 
                                endforeach;
                            endif;
                        } catch (Exception $e) {
                            echo '<option value="" disabled>Error loading doctors</option>';
                            error_log('Error fetching doctors: ' . $e->getMessage());
                        }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Appointment Type *</label>
                <select name="appointment_type" class="form-select" required>
                    <option value="">Select type</option>
                    <option value="consultation">Consultation</option>
                    <option value="followup">Follow-up</option>
                    <option value="emergency">Emergency</option>
                    <option value="procedure">Procedure</option>
                    <option value="checkup">Checkup</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Reason for Visit *</label>
            <textarea name="reason" class="form-textarea" placeholder="Brief description of visit purpose" required></textarea>
        </div>

        <!-- Optional: Display selected doctor's contact info -->
        <div id="doctorInfo" style="display: none; padding: 1rem; background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; margin-bottom: 1rem;">
            <strong style="color: #1e40af;">Selected Doctor Info:</strong>
            <div style="font-size: 0.875rem; color: #1e3a8a; margin-top: 0.5rem;">
                <div><span style="font-weight: 500;">Email:</span> <span id="doctorEmail"></span></div>
                <div><span style="font-weight: 500;">Phone:</span> <span id="doctorPhone"></span></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-calendar-plus"></i> Schedule Appointment
        </button>
    </form>
</div>

<!-- JavaScript to show doctor info when selected -->
<script>
document.getElementById('doctorSelect').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const doctorInfo = document.getElementById('doctorInfo');
    
    if (this.value) {
        document.getElementById('doctorEmail').textContent = selected.getAttribute('data-email');
        document.getElementById('doctorPhone').textContent = selected.getAttribute('data-phone');
        doctorInfo.style.display = 'block';
    } else {
        doctorInfo.style.display = 'none';
    }
});

// Set today's date as default
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    
    const appointmentDateInput = document.querySelector('input[name="appointment_date"]');
    if (appointmentDateInput && !appointmentDateInput.value) {
        appointmentDateInput.value = today;
    }
});
</script>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Tab Navigation
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', () => {
        const tabId = button.getAttribute('data-tab');
        
        // Remove active class from all tabs and buttons
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Add active class to clicked tab and corresponding content
        button.classList.add('active');
        document.getElementById(`${tabId}-tab`).classList.add('active');
    });
});

// BMI Calculation
function calculateBMI() {
    const heightInput = document.querySelector('input[name="height"]');
    const weightInput = document.querySelector('input[name="weight"]');
    const bmiInput = document.getElementById('bmi_calculated');
    
    if (heightInput && weightInput && bmiInput) {
        const height = parseFloat(heightInput.value) / 100; // Convert to meters
        const weight = parseFloat(weightInput.value);
        
        if (height > 0 && weight > 0) {
            const bmi = (weight / (height * height)).toFixed(1);
            bmiInput.value = bmi;
        } else {
            bmiInput.value = '';
        }
    }
}

// Add event listeners for height and weight inputs
document.addEventListener('DOMContentLoaded', function() {
    const heightInput = document.querySelector('input[name="height"]');
    const weightInput = document.querySelector('input[name="weight"]');
    
    if (heightInput) {
        heightInput.addEventListener('input', calculateBMI);
    }
    if (weightInput) {
        weightInput.addEventListener('input', calculateBMI);
    }
    
    // Calculate initial BMI if values exist
    calculateBMI();
    
    // Set today's date as default for dates
    const today = new Date().toISOString().split('T')[0];
    
    const appointmentDateInput = document.querySelector('input[name="appointment_date"]');
    if (appointmentDateInput) {
        appointmentDateInput.value = today;
    }
    
    const medicationStartDate = document.querySelector('input[name="start_date"]');
    if (medicationStartDate) {
        medicationStartDate.value = today;
    }
});
</script>

</body>
</html>