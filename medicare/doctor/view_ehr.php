<?php
// Start session at the very beginning with output buffering
ob_start();
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
        header("location: ../login.php");
        exit();
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
    exit();
}

// Import database
include("../connection.php");

// Get doctor details
$userrow = $database->query("select * from doctor where docemail='$useremail'");
if($userrow && $userrow->num_rows > 0){
    $userfetch=$userrow->fetch_assoc();
    $userid= $userfetch["docid"];
    $username=$userfetch["docname"];
} else {
    header("location: ../login.php");
    exit();
}

// Check if appointment ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("location: appointment.php");
    exit();
}

$appoid = $_GET['id'];

// Validate that this appointment belongs to the doctor
$appointment_check = $database->prepare("SELECT a.*, p.*, d.docid 
                                        FROM appointment a 
                                        INNER JOIN schedule s ON a.scheduleid = s.scheduleid 
                                        INNER JOIN patient p ON a.pid = p.pid 
                                        INNER JOIN doctor d ON s.docid = d.docid 
                                        WHERE a.appoid = ? AND d.docid = ?");
$appointment_check->bind_param("ii", $appoid, $userid);
$appointment_check->execute();
$appointment_result = $appointment_check->get_result();

if($appointment_result->num_rows == 0){
    header("location: appointment.php");
    exit();
}

$appointment_data = $appointment_result->fetch_assoc();
$pid = $appointment_data['pid'];
$patient_name = $appointment_data['pname'];
$appodate = $appointment_data['appodate'];

// Get EHR record for this appointment
$ehr_query = $database->prepare("SELECT * FROM ehr_records WHERE appoid = ? AND docid = ?");
$ehr_query->bind_param("ii", $appoid, $userid);
$ehr_query->execute();
$ehr_result = $ehr_query->get_result();
$ehr_data = $ehr_result->fetch_assoc();

// Get prescriptions for this appointment
$prescription_query = $database->prepare("SELECT * FROM ehr_prescriptions WHERE appoid = ? ORDER BY created_at DESC");
$prescription_query->bind_param("i", $appoid);
$prescription_query->execute();
$prescription_result = $prescription_query->get_result();

// Get all EHR history for this patient
$history_query = $database->prepare("SELECT e.*, a.appodate, d.docname 
                                    FROM ehr_records e 
                                    INNER JOIN appointment a ON e.appoid = a.appoid 
                                    INNER JOIN doctor d ON e.docid = d.docid 
                                    WHERE e.pid = ? 
                                    ORDER BY e.created_at DESC");
$history_query->bind_param("i", $pid);
$history_query->execute();
$history_result = $history_query->get_result();

// Get prescription count
$prescription_count = $prescription_result->num_rows;

// Store prescriptions in array for later use
$prescriptions_data = [];
while($pres = $prescription_result->fetch_assoc()) {
    $prescriptions_data[] = $pres;
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if(isset($_POST['save_vitals'])){
        // Save vitals update
        $bp = !empty($_POST['vitals_bp']) ? $_POST['vitals_bp'] : NULL;
        $temp = !empty($_POST['vitals_temp']) ? $_POST['vitals_temp'] : NULL;
        $hr = !empty($_POST['vitals_hr']) ? $_POST['vitals_hr'] : NULL;
        $rr = !empty($_POST['vitals_rr']) ? $_POST['vitals_rr'] : NULL;
        $weight = !empty($_POST['vitals_weight']) ? $_POST['vitals_weight'] : NULL;
        $height = !empty($_POST['vitals_height']) ? $_POST['vitals_height'] : NULL;
        
        if($ehr_data){
            // Update existing vitals
            $update_vitals = $database->prepare("UPDATE ehr_records 
                                                SET vitals_bp = ?, vitals_temp = ?, vitals_hr = ?, 
                                                    vitals_rr = ?, vitals_weight = ?, vitals_height = ?,
                                                    updated_at = CURRENT_TIMESTAMP 
                                                WHERE id = ?");
            $update_vitals->bind_param("ssssssi", $bp, $temp, $hr, $rr, $weight, $height, $ehr_data['id']);
            if($update_vitals->execute()){
                $success_message = "Vitals updated successfully!";
                // Refresh data
                $ehr_query = $database->prepare("SELECT * FROM ehr_records WHERE appoid = ? AND docid = ?");
                $ehr_query->bind_param("ii", $appoid, $userid);
                $ehr_query->execute();
                $ehr_result = $ehr_query->get_result();
                $ehr_data = $ehr_result->fetch_assoc();
            }
        } else {
            // Insert new vitals
            $insert_vitals = $database->prepare("INSERT INTO ehr_records (appoid, pid, docid, vitals_bp, vitals_temp, 
                                                vitals_hr, vitals_rr, vitals_weight, vitals_height) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_vitals->bind_param("iiissssss", $appoid, $pid, $userid, $bp, $temp, $hr, $rr, $weight, $height);
            if($insert_vitals->execute()){
                $success_message = "Vitals recorded successfully!";
                $ehr_query = $database->prepare("SELECT * FROM ehr_records WHERE appoid = ? AND docid = ?");
                $ehr_query->bind_param("ii", $appoid, $userid);
                $ehr_query->execute();
                $ehr_result = $ehr_query->get_result();
                $ehr_data = $ehr_result->fetch_assoc();
            }
        }
    }
    
    if(isset($_POST['save_diagnosis'])){
        $diagnosis = !empty($_POST['diagnosis']) ? $_POST['diagnosis'] : NULL;
        $treatment_plan = !empty($_POST['treatment_plan']) ? $_POST['treatment_plan'] : NULL;
        $medical_notes = !empty($_POST['medical_notes']) ? $_POST['medical_notes'] : NULL;
        
        if($ehr_data){
            // Update diagnosis
            $update_diagnosis = $database->prepare("UPDATE ehr_records 
                                                   SET diagnosis = ?, treatment_plan = ?, medical_notes = ?,
                                                       updated_at = CURRENT_TIMESTAMP 
                                                   WHERE id = ?");
            $update_diagnosis->bind_param("sssi", $diagnosis, $treatment_plan, $medical_notes, $ehr_data['id']);
            if($update_diagnosis->execute()){
                $success_message = "Diagnosis updated successfully!";
                $ehr_query = $database->prepare("SELECT * FROM ehr_records WHERE appoid = ? AND docid = ?");
                $ehr_query->bind_param("ii", $appoid, $userid);
                $ehr_query->execute();
                $ehr_result = $ehr_query->get_result();
                $ehr_data = $ehr_result->fetch_assoc();
            }
        } else {
            // Insert new diagnosis
            $insert_diagnosis = $database->prepare("INSERT INTO ehr_records (appoid, pid, docid, diagnosis, 
                                                   treatment_plan, medical_notes) 
                                                   VALUES (?, ?, ?, ?, ?, ?)");
            $insert_diagnosis->bind_param("iiisss", $appoid, $pid, $userid, $diagnosis, $treatment_plan, $medical_notes);
            if($insert_diagnosis->execute()){
                $success_message = "Diagnosis recorded successfully!";
                $ehr_query = $database->prepare("SELECT * FROM ehr_records WHERE appoid = ? AND docid = ?");
                $ehr_query->bind_param("ii", $appoid, $userid);
                $ehr_query->execute();
                $ehr_result = $ehr_query->get_result();
                $ehr_data = $ehr_result->fetch_assoc();
            }
        }
    }
    
    if(isset($_POST['add_prescription'])){
        $medication = $_POST['medication'];
        $dosage = $_POST['dosage'];
        $frequency = $_POST['frequency'];
        $duration = $_POST['duration'];
        $instructions = $_POST['instructions'];
        
        $add_prescription = $database->prepare("INSERT INTO ehr_prescriptions 
                                               (appoid, medication, dosage, frequency, duration, instructions) 
                                               VALUES (?, ?, ?, ?, ?, ?)");
        $add_prescription->bind_param("isssss", $appoid, $medication, $dosage, $frequency, $duration, $instructions);
        if($add_prescription->execute()){
            $success_message = "Prescription added successfully!";
            // Refresh prescriptions
            $prescription_query = $database->prepare("SELECT * FROM ehr_prescriptions WHERE appoid = ? ORDER BY created_at DESC");
            $prescription_query->bind_param("i", $appoid);
            $prescription_query->execute();
            $prescription_result = $prescription_query->get_result();
            
            $prescription_count = $prescription_result->num_rows;
            $prescriptions_data = [];
            while($pres = $prescription_result->fetch_assoc()) {
                $prescriptions_data[] = $pres;
            }
        }
    }
}

// Store history data in array
$history_data = [];
while($history = $history_result->fetch_assoc()) {
    // Get prescriptions for each history record
    $hist_presc_query = $database->prepare("SELECT medication FROM ehr_prescriptions WHERE appoid = ?");
    $hist_presc_query->bind_param("i", $history['appoid']);
    $hist_presc_query->execute();
    $hist_presc_result = $hist_presc_query->get_result();
    
    $prescription_list = [];
    while($pres = $hist_presc_result->fetch_assoc()){
        $prescription_list[] = $pres['medication'];
    }
    
    $history['prescriptions'] = $prescription_list;
    $history_data[] = $history;
}

// End output buffering
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHR - Patient Medical Records - Smart Clinic Management System</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
            --section-bg: #ffffff;
            --border-color: #e5e7eb;
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

        .menu-icon-appointment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-patient::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
        }

        .menu-icon-ehr::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpolyline points='14 2 14 8 20 8'/%3E%3Cline x1='16' y1='13' x2='8' y2='13'/%3E%3Cline x1='16' y1='17' x2='8' y2='17'/%3E%3Cpolyline points='10 9 9 9 8 9'/%3E%3C/svg%3E");
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

        /* Header */
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

        /* Patient Info Card */
        .patient-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
        }

        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .patient-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
        }

        .patient-meta {
            color: #64748b;
            font-size: 14px;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            color: var(--textcolor);
            font-weight: 500;
        }

        /* Section Container */
        .section-container {
            background: var(--section-bg);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accentcolor);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--textcolor);
            margin-bottom: 8px;
        }

        .form-input {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-back {
            background: #9ca3af;
            color: white;
        }

        .btn-back:hover {
            background: #6b7280;
        }

        /* Vitals Grid */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .vital-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid var(--border-color);
        }

        .vital-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .vital-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--primarycolor);
        }

        .vital-unit {
            font-size: 12px;
            color: #94a3b8;
            margin-left: 5px;
        }

        /* Prescriptions Table */
        .prescription-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .prescription-table th {
            background: #f1f5f9;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--textcolor);
            border-bottom: 2px solid var(--border-color);
        }

        .prescription-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .prescription-table tr:hover {
            background: #f8fafc;
        }

        /* History Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primarycolor);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .timeline-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -33px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accentcolor);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--primarycolor);
        }

        .timeline-date {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .timeline-doctor {
            font-size: 13px;
            color: var(--primarycolor);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .timeline-diagnosis {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primarycolor);
        }

        .timeline-prescriptions {
            font-size: 13px;
            color: #475569;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .tab:hover {
            color: var(--primarycolor);
        }

        .tab.active {
            color: var(--primarycolor);
            border-bottom: 2px solid var(--primarycolor);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #e0f2fe;
            color: #075985;
        }

        /* Print Styles */
        @media print {
            .menu, .nav-bar, .btn, form {
                display: none !important;
            }
            
            .dash-body {
                margin-left: 0;
                padding: 20px;
            }
            
            .section-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .menu {
                width: 70px;
            }
            
            .menu-text, .profile-subtitle {
                display: none;
            }
            
            .dash-body {
                margin-left: 70px;
                padding: 20px;
            }
            
            .nav-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .vitals-grid {
                grid-template-columns: 1fr;
            }
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
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo substr($username,0,13)  ?>..</p>
                                    <p class="profile-subtitle"><?php echo substr($useremail,0,22)  ?></p>
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
                    <td class="menu-btn menu-icon-appointment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Appointments</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><p class="menu-text">My Patients</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-ehr menu-active">
                        <a href="#" class="non-style-link-menu"><p class="menu-text">EHR Records</p></a>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Main Content -->
        <div class="dash-body">
            <!-- Header -->
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Electronic Health Records</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Comprehensive patient medical history</p>
                </div>
                <div>
                    <a href="appointment.php" class="btn btn-back">
                        ← Back to Appointments
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Patient Information Card -->
            <div class="patient-card">
                <div class="patient-header">
                    <div>
                        <h2 class="patient-title"><?php echo htmlspecialchars($patient_name); ?></h2>
                        <p class="patient-meta">Patient ID: P-<?php echo $pid; ?> | Appointment Date: <?php echo $appodate; ?></p>
                    </div>
                    <!-- <div>
                        <button onclick="window.print()" class="btn btn-secondary">Print Record</button>
                    </div> -->
                </div>
                
                <div class="patient-details">
                    <div class="detail-item">
                        <span class="detail-label">Appointment ID</span>
                        <span class="detail-value">A-<?php echo $appoid; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Date of Visit</span>
                        <span class="detail-value"><?php echo $appodate; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Attending Doctor</span>
                        <span class="detail-value">Dr. <?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Record Status</span>
                        <span class="detail-value">
                            <?php if($ehr_data): ?>
                                <span class="badge badge-success">Completed</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('current')">Current Visit</div>
                <div class="tab" onclick="switchTab('history')">Medical History</div>
            </div>

            <!-- Current Visit Tab -->
            <div id="current" class="tab-content active">
                <!-- Vitals Section -->
                

                <!-- Diagnosis & Treatment Section -->
                <div class="section-container">
                    <h3 class="section-title">Diagnosis & Treatment Plan</h3>
                    
                    <?php if($ehr_data && ($ehr_data['diagnosis'] || $ehr_data['treatment_plan'])): ?>
                    <div style="margin-bottom: 30px;">
                        <?php if($ehr_data['diagnosis']): ?>
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--primarycolor);margin-bottom: 10px;font-size: 16px;">Current Diagnosis</h4>
                            <div style="background: #f8fafc;padding: 15px;border-radius: 6px;border-left: 3px solid var(--primarycolor);">
                                <?php echo nl2br(htmlspecialchars($ehr_data['diagnosis'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($ehr_data['treatment_plan']): ?>
                        <div>
                            <h4 style="color: var(--primarycolor);margin-bottom: 10px;font-size: 16px;">Treatment Plan</h4>
                            <div style="background: #f8fafc;padding: 15px;border-radius: 6px;border-left: 3px solid #10b981;">
                                <?php echo nl2br(htmlspecialchars($ehr_data['treatment_plan'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Diagnosis Form -->
                    <form method="POST" action="">
                        <h4 style="margin-bottom: 20px;color: var(--primarycolor);font-size: 16px;">Update Diagnosis & Treatment</h4>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label">Diagnosis</label>
                            <textarea name="diagnosis" class="form-input form-textarea" 
                                      placeholder="Enter diagnosis details, symptoms, and findings..."><?php echo $ehr_data['diagnosis'] ?? ''; ?></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label">Treatment Plan</label>
                            <textarea name="treatment_plan" class="form-input form-textarea" 
                                      placeholder="Enter treatment recommendations, medications, and follow-up instructions..."><?php echo $ehr_data['treatment_plan'] ?? ''; ?></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label">Additional Medical Notes</label>
                            <textarea name="medical_notes" class="form-input form-textarea" 
                                      placeholder="Any additional notes or observations..."><?php echo $ehr_data['medical_notes'] ?? ''; ?></textarea>
                        </div>
                        <button type="submit" name="save_diagnosis" class="btn btn-primary">Save Diagnosis</button>
                    </form>
                </div>

                <!-- Prescriptions Section -->
                <div class="section-container">
                    <h3 class="section-title">
                        <span>Prescriptions</span>
                        <?php if($prescription_count > 0): ?>
                        <span class="badge badge-info"><?php echo $prescription_count; ?> medications</span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if($prescription_count > 0): ?>
                    <table class="prescription-table">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($prescriptions_data as $prescription): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($prescription['medication']); ?></strong></td>
                                <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['frequency']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['duration']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['instructions'] ?? '--'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center;color: #64748b;padding: 30px;">No prescriptions recorded for this visit.</p>
                    <?php endif; ?>

                    <!-- Add Prescription Form -->
                    <form method="POST" action="" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 20px;color: var(--primarycolor);font-size: 16px;">Add New Prescription</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Medication Name</label>
                                <input type="text" name="medication" class="form-input" 
                                       placeholder="e.g., Amoxicillin 500mg" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dosage</label>
                                <input type="text" name="dosage" class="form-input" 
                                       placeholder="e.g., 500mg" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Frequency</label>
                                <input type="text" name="frequency" class="form-input" 
                                       placeholder="e.g., Three times daily" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" class="form-input" 
                                       placeholder="e.g., 7 days" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Special Instructions</label>
                                <input type="text" name="instructions" class="form-input" 
                                       placeholder="e.g., Take after meals">
                            </div>
                        </div>
                        <button type="submit" name="add_prescription" class="btn btn-primary">Add Prescription</button>
                    </form>
                </div>
            </div>

            <!-- Medical History Tab -->
            <div id="history" class="tab-content">
                <div class="section-container">
                    <h3 class="section-title">
                        <span>Medical History Timeline</span>
                        <span style="font-size: 14px;color: #64748b;">Past visits and treatments</span>
                    </h3>
                    
                    <?php if(count($history_data) > 0): ?>
                    <div class="timeline">
                        <?php foreach($history_data as $history): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo date('F j, Y', strtotime($history['created_at'])); ?> 
                                (Appointment: <?php echo $history['appodate']; ?>)
                            </div>
                            <div class="timeline-doctor">
                                Dr. <?php echo htmlspecialchars($history['docname']); ?>
                            </div>
                            <?php if($history['diagnosis']): ?>
                            <div class="timeline-diagnosis">
                                <strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($history['diagnosis'])); ?>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($history['prescriptions'])): ?>
                            <div class="timeline-prescriptions">
                                <strong>Prescriptions:</strong> <?php echo htmlspecialchars(implode(', ', $history['prescriptions'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="text-align: center;color: #64748b;padding: 40px;">
                        No previous medical history found for this patient.
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="appointment.php" class="btn btn-secondary">← Back to Appointments</a>
                <!-- <button onclick="window.print()" class="btn btn-secondary">Print This Record</button> -->
                <?php if($ehr_data): ?>
                <!-- <span style="color: #10b981;font-weight: 600;margin-left: auto;display: flex;align-items: center;gap: 8px;">
                    ✓ EHR Record Complete
                </span> -->
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }
        
        // Function to clear vitals form
        function clearVitals() {
            if(confirm('Are you sure you want to clear all vital signs? This action cannot be undone.')) {
                document.querySelector('input[name="vitals_bp"]').value = '';
                document.querySelector('input[name="vitals_temp"]').value = '';
                document.querySelector('input[name="vitals_hr"]').value = '';
                document.querySelector('input[name="vitals_rr"]').value = '';
                document.querySelector('input[name="vitals_weight"]').value = '';
                document.querySelector('input[name="vitals_height"]').value = '';
            }
        }
        
        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);
        
        // Confirm before submitting forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if(!confirm('Are you sure you want to save these changes?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>