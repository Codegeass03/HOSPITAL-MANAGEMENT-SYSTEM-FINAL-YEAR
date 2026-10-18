<?php
session_start();

include("../connection.php");

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? 0;

if($appointment_id == 0){
    header("location: appointment.php?error=Invalid appointment ID");
    exit();
}

// Fetch appointment details
$sql = "SELECT a.appoid, a.apponum, a.pid, a.appodate, p.pname, p.pemail, p.ptel, p.pnic, p.pdob, p.paddress,
        d.docid, d.docname, d.docemail, s.scheduleid, s.title, s.scheduledate, s.scheduletime
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN schedule s ON a.scheduleid = s.scheduleid
        JOIN doctor d ON s.docid = d.docid
        WHERE a.appoid = '$appointment_id'";

$result = $database->query($sql);

if($result->num_rows == 0){
    header("location: appointment.php?error=Appointment not found");
    exit();
}

$appointment = $result->fetch_assoc();

// Handle form submissions
if($_POST){
    // Handle saving vital signs
    if(isset($_POST['save_vitals'])){
        $vitals_bp = $database->real_escape_string($_POST['blood_pressure'] ?? '');
        $vitals_temp = $database->real_escape_string($_POST['temperature'] ?? '');
        $vitals_hr = $database->real_escape_string($_POST['heart_rate'] ?? '');
        $vitals_rr = $database->real_escape_string($_POST['respiratory_rate'] ?? '');
        $vitals_weight = $database->real_escape_string($_POST['weight'] ?? '');
        $vitals_height = $database->real_escape_string($_POST['height'] ?? '');
        
        // Check if record already exists for this appointment
        $check_sql = "SELECT id FROM ehr_records WHERE appoid = '$appointment_id'";
        $check_result = $database->query($check_sql);
        
        if($check_result->num_rows > 0) {
            // Update existing record with vitals
            $vitals_sql = "UPDATE ehr_records SET 
                        vitals_bp = '$vitals_bp', 
                        vitals_temp = '$vitals_temp', 
                        vitals_hr = '$vitals_hr',
                        vitals_rr = '$vitals_rr',
                        vitals_weight = '$vitals_weight',
                        vitals_height = '$vitals_height',
                        updated_at = NOW()
                        WHERE appoid = '$appointment_id'";
        } else {
            // Insert new record with vitals
            $vitals_sql = "INSERT INTO ehr_records (appoid, pid, docid, vitals_bp, vitals_temp, vitals_hr, vitals_rr, vitals_weight, vitals_height) 
                        VALUES ('$appointment_id', '{$appointment['pid']}', '{$appointment['docid']}', '$vitals_bp', '$vitals_temp', '$vitals_hr', '$vitals_rr', '$vitals_weight', '$vitals_height')";
        }
        
        if($database->query($vitals_sql)){
            $vitals_success = "Vital signs saved successfully!";
        } else {
            $vitals_error = "Error saving vital signs: " . $database->error;
        }
    }
    
    // Handle saving EHR/Clinical Notes
    if(isset($_POST['save_ehr'])){
        $symptoms = $database->real_escape_string($_POST['symptoms'] ?? '');
        $diagnosis = $database->real_escape_string($_POST['diagnosis'] ?? '');
        $treatment_plan = $database->real_escape_string($_POST['treatment_plan'] ?? '');
        $notes = $database->real_escape_string($_POST['notes'] ?? '');
        
        // Check if record already exists for this appointment
        $check_sql = "SELECT id FROM ehr_records WHERE appoid = '$appointment_id'";
        $check_result = $database->query($check_sql);
        
        if($check_result->num_rows > 0) {
            // Update existing record
            $ehr_sql = "UPDATE ehr_records SET 
                        diagnosis = '$diagnosis', 
                        treatment_plan = '$treatment_plan', 
                        medical_notes = '$notes',
                        updated_at = NOW()
                        WHERE appoid = '$appointment_id'";
        } else {
            // Insert new record
            $ehr_sql = "INSERT INTO ehr_records (appoid, pid, docid, diagnosis, treatment_plan, medical_notes) 
                        VALUES ('$appointment_id', '{$appointment['pid']}', '{$appointment['docid']}', '$diagnosis', '$treatment_plan', '$notes')";
        }
        
        if($database->query($ehr_sql)){
            $success = "Medical record saved successfully!";
        } else {
            $error = "Error saving medical record: " . $database->error;
        }
    }
    
    // Handle adding prescription
    if(isset($_POST['add_prescription'])){
        $medication_name = $database->real_escape_string($_POST['medication_name'] ?? '');
        $dosage = $database->real_escape_string($_POST['dosage'] ?? '');
        $frequency = $database->real_escape_string($_POST['frequency'] ?? '');
        $duration = $database->real_escape_string($_POST['duration'] ?? '');
        $instructions = $database->real_escape_string($_POST['instructions'] ?? '');
        
        $prescription_sql = "INSERT INTO ehr_prescriptions (appoid, medication, dosage, frequency, duration, instructions) 
                            VALUES ('$appointment_id', '$medication_name', '$dosage', '$frequency', '$duration', '$instructions')";
        
        if($database->query($prescription_sql)){
            $prescription_success = "Prescription added successfully!";
        } else {
            $prescription_error = "Error adding prescription: " . $database->error;
        }
    }
    
    // Handle Complete Visit
    if(isset($_POST['complete_visit'])){
        // Update appointment status to Complete
        $update_sql = "UPDATE appointment SET 
                      status = 'Complete', 
                      completed_by = '{$_SESSION['user']}', 
                      completed_at = NOW(),
                      workflow_stage = 'completed'
                      WHERE appoid = '$appointment_id'";
        
        if($database->query($update_sql)){
            // Create consultation charge if it doesn't exist
            $charge_check = $database->query("SELECT id FROM patient_charges WHERE appoid = '$appointment_id' AND charge_type = 'consultation'");
            
            if($charge_check->num_rows == 0) {
                $charge_sql = "INSERT INTO patient_charges (pid, appoid, description, amount, charge_type, status, created_by, created_date) 
                              VALUES ('{$appointment['pid']}', '$appointment_id', 'Consultation Fee', 500.00, 'consultation', 'pending', '{$_SESSION['user']}', NOW())";
                $database->query($charge_sql);
            }
            
            $complete_success = "Visit completed successfully! The appointment has been marked as complete.";
            // Refresh the page to show updated status
            header("location: start_appointment.php?id=$appointment_id&success=Visit completed successfully");
            exit();
        } else {
            $complete_error = "Error completing visit: " . $database->error;
        }
    }
}

// Fetch existing prescriptions for this appointment
$prescriptions_sql = "SELECT ep.* 
                     FROM ehr_prescriptions ep
                     WHERE ep.appoid = '$appointment_id'
                     ORDER BY ep.created_at DESC";

$prescriptions_result = $database->query($prescriptions_sql);

// Check if appointment is already completed
$status_check = $database->query("SELECT status FROM appointment WHERE appoid = '$appointment_id'");
$is_completed = false;
if($status_check->num_rows > 0) {
    $status_row = $status_check->fetch_assoc();
    $is_completed = ($status_row['status'] == 'Complete');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHR - Appointment #<?php echo $appointment['apponum']; ?> - Smart Clinic Management System</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bgcolor);
            color: var(--textcolor);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .menu {
            width: 280px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .menu::-webkit-scrollbar {
            width: 6px;
        }

        .menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-subtitle {
            font-size: 13px;
            opacity: 0.8;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .menu-row {
            margin: 5px 0;
        }

        .menu-btn {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-btn.menu-active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }

        .menu-btn::before {
            content: '';
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: brightness(0) invert(1);
        }

        .menu-icon-dashbord::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3C/svg%3E");
        }

        .menu-icon-doctor::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M22 21v-2a4 4 0 0 0-3-3.87'/%3E%3Cpath d='M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E");
        }

        .menu-icon-schedule::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-patient::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
        }

        .menu-icon-pharmacy::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
        }

        .menu-icon-billing::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E");
        }

        .menu-icon-reports::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='18' y1='20' x2='18' y2='10'/%3E%3Cline x1='12' y1='20' x2='12' y2='4'/%3E%3Cline x1='6' y1='20' x2='6' y2='14'/%3E%3C/svg%3E");
        }

        .menu-text {
            font-size: 15px;
            color: white;
            text-decoration: none;
        }

        .non-style-link-menu {
            text-decoration: none;
            color: white;
            display: block;
        }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px 50px;
        }

        .nav-bar {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* EHR Container Styles */
        .ehr-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
        }

        .patient-header {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .patient-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .patient-header p {
            opacity: 0.9;
            margin-bottom: 5px;
            font-size: 14px;
        }

        /* Tab Styles */
        .tab-container {
            margin-top: 20px;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 15px 25px;
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab-button:hover {
            color: var(--primarycolor);
            background: rgba(8, 145, 178, 0.05);
        }

        .tab-button.active {
            color: var(--primarycolor);
            border-bottom-color: var(--primarycolor);
            background: white;
        }

        .tab-content {
            display: none;
            padding: 25px;
            background: white;
            border-radius: 0 0 8px 8px;
            animation: transitionIn-Y-over 0.5s;
        }

        .tab-content.active {
            display: block;
        }

        .tab-content h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--primarycolor);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accentcolor);
        }

        /* Form Styles */
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 3px solid var(--primarycolor);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
            font-size: 14px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* Medication Form */
        .medication-form {
            background: linear-gradient(135deg, #e8f4fd 0%, #d1e7ff 100%);
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid var(--primarycolor);
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.1);
        }

        /* Vital Signs Grid */
        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .vital-input {
            text-align: center;
        }

        .vital-input label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .vital-input input {
            text-align: center;
            font-weight: 600;
            background: white;
        }

        /* Prescription List */
        .prescription-list {
            margin-top: 25px;
        }

        .prescription-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .prescription-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .prescription-info h4 {
            margin: 0 0 8px 0;
            color: var(--primarydark);
            font-size: 16px;
            font-weight: 600;
        }

        .prescription-details {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .prescription-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-dispensed {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #b3d9ff;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Buttons */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
        }

        .action-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .action-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .action-btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-left: 4px solid #dc3545;
        }

        /* Animations */
        @keyframes transitionIn-Y-over {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes transitionIn-Y-bottom {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .ehr-container {
            animation: transitionIn-Y-over 0.5s;
        }

        .tab-content {
            animation: transitionIn-Y-bottom 0.6s;
        }

        .prescription-item {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Menu -->
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23e0f2fe'/%3E%3Cpath d='M50 45c8.284 0 15-6.716 15-15s-6.716-15-15-15-15 6.716-15 15 6.716 15 15 15zm0 7.5c-10 0-30 5-30 15V75h60v-7.5c0-10-20-15-30-15z' fill='%230891b2'/%3E%3C/svg%3E" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle">admin@edoc.com</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php"><input type="button" value="Log out" class="logout-btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Schedule</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment menu-active">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">Appointment</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><p class="menu-text">Patients</p></a>
                    </td>
                </tr>
              
            </table>
        </div>

        <div class="dash-body">
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Electronic Health Record</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Appointment #<?php echo $appointment['apponum']; ?> - <?php echo htmlspecialchars($appointment['pname']); ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="appointment.php" class="action-btn">Back to Appointments</a>
                
                
                <?php if(!$is_completed): ?>
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" name="complete_visit" class="action-btn" style="background: #10b981;" 
                                onclick="return confirm('Are you sure you want to complete this visit? This will mark the appointment as completed and cannot be undone.')">
                            Complete Visit
                        </button>
                    </form>
                <?php else: ?>
                    <button class="action-btn" style="background: #22c55e; cursor: not-allowed;" disabled>
                        Visit Completed
                    </button>
                <?php endif; ?>
                
               
            </div>

            <!-- EHR Container -->
            <div class="ehr-container">
                <!-- Patient Header -->
                <div class="patient-header">
                    <h2><?php echo htmlspecialchars($appointment['pname']); ?></h2>
                    <p>
                        <strong>Patient ID:</strong> P-<?php echo $appointment['pid']; ?> | 
                        <strong>DOB:</strong> <?php echo date('F j, Y', strtotime($appointment['pdob'])); ?> |
                        <strong>Contact:</strong> <?php echo htmlspecialchars($appointment['ptel']); ?>
                    </p>
                    <p>
                        <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($appointment['docname']); ?> |
                        <strong>Appointment:</strong> <?php echo date('F j, Y', strtotime($appointment['appodate'])); ?>
                        <?php if($is_completed): ?>
                            | <strong>Status:</strong> <span style="background: #22c55e; color: white; padding: 4px 8px; border-radius: 4px; font-weight: 600;">COMPLETED</span>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if(isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($complete_error)): ?>
                    <div class="alert alert-error">
                        <?php echo $complete_error; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($vitals_success)): ?>
                    <div class="alert alert-success">
                        <?php echo $vitals_success; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($vitals_error)): ?>
                    <div class="alert alert-error">
                        <?php echo $vitals_error; ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Interface -->
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-button active" onclick="openTab('vitals')">Vital Signs</button>
                        <button class="tab-button" onclick="openTab('ehr')">Clinical Notes</button>
                        <button class="tab-button" onclick="openTab('prescription')">Prescription</button>
                        <!-- <button class="tab-button" onclick="openTab('history')">History</button> -->
                    </div>

                    <!-- Vital Signs Tab -->
                    <div id="vitals" class="tab-content active">
                        <h3>Vital Signs</h3>
                        <form method="POST" action="">
                            <div class="vital-signs-grid">
                                <div class="vital-input">
                                    <label>Blood Pressure (mmHg)</label>
                                    <input type="text" name="blood_pressure" placeholder="120/80">
                                </div>
                                <div class="vital-input">
                                    <label>Heart Rate (bpm)</label>
                                    <input type="number" name="heart_rate" placeholder="72">
                                </div>
                                <div class="vital-input">
                                    <label>Temperature (°C)</label>
                                    <input type="number" step="0.1" name="temperature" placeholder="36.6">
                                </div>
                                <div class="vital-input">
                                    <label>Respiratory Rate</label>
                                    <input type="number" name="respiratory_rate" placeholder="16">
                                </div>
                                <div class="vital-input">
                                    <label>Weight (kg)</label>
                                    <input type="number" step="0.1" name="weight" placeholder="70.5">
                                </div>
                                <div class="vital-input">
                                    <label>Height (cm)</label>
                                    <input type="number" name="height" placeholder="175">
                                </div>
                                <div class="vital-input">
                                    <label>BMI</label>
                                    <input type="number" step="0.1" name="bmi" placeholder="23.1" readonly>
                                </div>
                            </div>
                            <button type="submit" name="save_vitals" class="btn btn-primary">Save Vital Signs</button>
                        </form>
                    </div>

                    <!-- EHR Tab -->
                    <div id="ehr" class="tab-content">
                        <h3>Clinical Notes & Diagnosis</h3>
                        <form method="POST" action="">
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="symptoms">Presenting Symptoms *</label>
                                    <textarea id="symptoms" name="symptoms" required placeholder="Describe the patient's symptoms, duration, severity..."><?php echo $_POST['symptoms'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="diagnosis">Diagnosis *</label>
                                    <textarea id="diagnosis" name="diagnosis" required placeholder="Enter diagnosis..."><?php echo $_POST['diagnosis'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="treatment_plan">Treatment Plan</label>
                                    <textarea id="treatment_plan" name="treatment_plan" placeholder="Describe the recommended treatment..."><?php echo $_POST['treatment_plan'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Additional Notes</label>
                                    <textarea id="notes" name="notes" placeholder="Any additional clinical notes..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" name="save_ehr" class="btn btn-primary">Save Medical Record</button>
                                <button type="button" onclick="openTab('prescription')" class="btn btn-secondary">Continue to Prescription</button>
                            </div>
                        </form>
                    </div>

                    <!-- Prescription Tab -->
                    <div id="prescription" class="tab-content">
                        <h3>Medication Prescription</h3>
                        
                        <?php if(isset($prescription_success)): ?>
                            <div class="alert alert-success">
                                <?php echo $prescription_success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(isset($prescription_error)): ?>
                            <div class="alert alert-error">
                                <?php echo $prescription_error; ?>
                            </div>
                        <?php endif; ?>

                        <div class="medication-form">
                            <form method="POST" action="">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label for="medication_name">Medication Name *</label>
                                        <input type="text" id="medication_name" name="medication_name" required list="medications" value="<?php echo $_POST['medication_name'] ?? ''; ?>">
                                        <datalist id="medications">
                                            <option value="Paracetamol">
                                            <option value="Amoxicillin">
                                            <option value="Ibuprofen">
                                            <option value="Insulin">
                                            <option value="Cough Syrup">
                                            <option value="Antihistamine">
                                            <option value="Antibiotic">
                                            <option value="Pain Relief">
                                        </datalist>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="dosage">Dosage *</label>
                                        <input type="text" id="dosage" name="dosage" required placeholder="e.g., 500mg, 10ml" value="<?php echo $_POST['dosage'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="frequency">Frequency *</label>
                                        <select id="frequency" name="frequency" required>
                                            <option value="">Select Frequency</option>
                                            <option value="Once daily" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'Once daily') ? 'selected' : ''; ?>>Once daily</option>
                                            <option value="Twice daily" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'Twice daily') ? 'selected' : ''; ?>>Twice daily</option>
                                            <option value="Three times daily" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'Three times daily') ? 'selected' : ''; ?>>Three times daily</option>
                                            <option value="Four times daily" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'Four times daily') ? 'selected' : ''; ?>>Four times daily</option>
                                            <option value="As needed" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'As needed') ? 'selected' : ''; ?>>As needed</option>
                                            <option value="Before meals" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'Before meals') ? 'selected' : ''; ?>>Before meals</option>
                                            <option value="After meals" <?php echo (isset($_POST['frequency']) && $_POST['frequency'] == 'After meals') ? 'selected' : ''; ?>>After meals</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="duration">Duration *</label>
                                        <input type="text" id="duration" name="duration" required placeholder="e.g., 7 days, 2 weeks" value="<?php echo $_POST['duration'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="instructions">Special Instructions</label>
                                    <textarea id="instructions" name="instructions" placeholder="Any special instructions for the patient..."><?php echo $_POST['instructions'] ?? ''; ?></textarea>
                                </div>
                                
                                <button type="submit" name="add_prescription" class="btn btn-primary">Add to Prescription</button>
                            </form>
                        </div>

                        <!-- Current Prescriptions -->
                        <div class="prescription-list">
                            <h4 style="color: var(--primarycolor); margin-bottom: 20px;">Current Prescriptions</h4>
                            <?php if($prescriptions_result->num_rows > 0): ?>
                                <?php while($prescription = $prescriptions_result->fetch_assoc()): ?>
                                    <div class="prescription-item">
                                        <div class="prescription-info">
                                            <h4><?php echo htmlspecialchars($prescription['medication']); ?></h4>
                                            <div class="prescription-details">
                                                <?php echo htmlspecialchars($prescription['dosage']); ?> | 
                                                <?php echo htmlspecialchars($prescription['frequency']); ?> | 
                                                <?php echo htmlspecialchars($prescription['duration']); ?>
                                                <?php if(!empty($prescription['instructions'])): ?>
                                                    <br><em><?php echo htmlspecialchars($prescription['instructions']); ?></em>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="prescription-status status-<?php echo $prescription['status'] ?? 'pending_pharmacy'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $prescription['status'] ?? 'pending_pharmacy')); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div style="text-align: center; color: #64748b; padding: 30px; background: #f8f9fa; border-radius: 8px;">
                                    <p style="margin: 0; font-size: 16px;">No prescriptions added yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div id="history" class="tab-content">
                        <h3>Medical History</h3>
                        <div style="text-align: center; color: #64748b; padding: 40px; background: #f8f9fa; border-radius: 8px;">
                            <p style="font-size: 16px; margin-bottom: 10px;">Previous medical records and prescriptions will appear here.</p>
                            <p style="font-size: 14px;">This section displays the patient's complete medical history across all visits.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tab buttons
            var tabButtons = document.getElementsByClassName('tab-button');
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            // Show the selected tab content and activate the button
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Auto-calculate BMI
        document.querySelector('input[name="weight"]')?.addEventListener('input', calculateBMI);
        document.querySelector('input[name="height"]')?.addEventListener('input', calculateBMI);

        function calculateBMI() {
            const weight = parseFloat(document.querySelector('input[name="weight"]').value);
            const height = parseFloat(document.querySelector('input[name="height"]').value) / 100; // convert to meters
            
            if (weight && height) {
                const bmi = weight / (height * height);
                document.querySelector('input[name="bmi"]').value = bmi.toFixed(1);
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);

        // Check if print action is requested
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('action') === 'print') {
            window.print();
        }
    </script>
</body>
</html>