<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Check if user is logged in as a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit;
}

// Get patient ID from session
$patient_id = $_SESSION['user_id'];

// Get patient information
$patient_stmt = $pdo->prepare("
    SELECT p.* 
    FROM patients p 
    WHERE p.id = ? OR EXISTS (
        SELECT 1 FROM users u 
        WHERE u.id = ? AND (u.phone = p.phone OR u.email = p.email)
    )
    LIMIT 1
");
$patient_stmt->execute([$patient_id, $patient_id]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    // Try one more method
    $patient_stmt2 = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patient_stmt2->execute([$patient_id]);
    $patient = $patient_stmt2->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient record not found. Please contact administration.";
        header("Location: patient_dashboard.php");
        exit;
    }
}

// Get patient's latest vitals
$latest_vitals_stmt = $pdo->prepare("
    SELECT * FROM recent_patient_vitals 
    WHERE patient_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 1
");
$latest_vitals_stmt->execute([$patient['id']]);
$latest_vitals = $latest_vitals_stmt->fetch();

// Get patient statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total_visits,
        COUNT(DISTINCT a.doctor_id) as doctors_seen,
        MIN(a.appointment_date) as first_visit,
        MAX(a.appointment_date) as last_visit,
        COUNT(DISTINCT p.id) as total_prescriptions,
        COUNT(DISTINCT mr.id) as medical_records_count
    FROM appointments a
    LEFT JOIN prescriptions p ON a.patient_id = p.patient_id
    LEFT JOIN medical_records mr ON a.patient_id = mr.patient_id
    WHERE a.patient_id = ? 
    AND a.status IN ('completed', 'confirmed', 'scheduled')
");
$stats_stmt->execute([$patient['id']]);
$stats = $stats_stmt->fetch();

// Get all medical records for this patient
$medical_records_stmt = $pdo->prepare("
    SELECT 
        mr.*,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        dp.specialty as doctor_specialty,
        a.appointment_date,
        a.appointment_time,
        a.appointment_number,
        COUNT(DISTINCT p.id) as prescription_count
    FROM medical_records mr
    LEFT JOIN appointments a ON mr.patient_id = a.patient_id AND DATE(mr.created_at) = DATE(a.appointment_date)
    LEFT JOIN users d ON mr.doctor_id = d.id
    LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
    LEFT JOIN prescriptions p ON mr.patient_id = p.patient_id AND DATE(p.prescription_date) = DATE(mr.created_at)
    WHERE mr.patient_id = ?
    GROUP BY mr.id, a.appointment_date, a.appointment_number
    ORDER BY mr.created_at DESC
");
$medical_records_stmt->execute([$patient['id']]);
$medical_records = $medical_records_stmt->fetchAll();

// Get recent vitals history
$vitals_history_stmt = $pdo->prepare("
    SELECT * FROM recent_patient_vitals 
    WHERE patient_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 5
");
$vitals_history_stmt->execute([$patient['id']]);
$vitals_history = $vitals_history_stmt->fetchAll();

// Get recent prescriptions
$recent_prescriptions_stmt = $pdo->prepare("
    SELECT 
        p.*,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        DATE_FORMAT(p.prescription_date, '%M %d, %Y') as formatted_date
    FROM prescriptions p
    LEFT JOIN users d ON p.doctor_id = d.id
    WHERE p.patient_id = ?
    ORDER BY p.prescription_date DESC
    LIMIT 10
");
$recent_prescriptions_stmt->execute([$patient['id']]);
$recent_prescriptions = $recent_prescriptions_stmt->fetchAll();

// Get clinical notes
$clinical_notes_stmt = $pdo->prepare("
    SELECT 
        cn.*,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        DATE_FORMAT(cn.created_at, '%M %d, %Y %h:%i %p') as formatted_date
    FROM clinical_notes cn
    LEFT JOIN users d ON cn.doctor_id = d.id
    WHERE cn.patient_id = ?
    ORDER BY cn.created_at DESC
    LIMIT 10
");
$clinical_notes_stmt->execute([$patient['id']]);
$clinical_notes = $clinical_notes_stmt->fetchAll();

// Calculate BMI and other health indicators if we have height and weight
$bmi = null;
$bmi_category = null;
if ($latest_vitals && $latest_vitals['weight'] && $latest_vitals['height']) {
    $height_m = $latest_vitals['height'] / 100;
    $bmi = $latest_vitals['weight'] / ($height_m * $height_m);
    
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical Records - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
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
            line-height: 1.5;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            animation: fadeIn 0.5s ease;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Overview Stats */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .overview-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid var(--primarycolor);
        }

        .overview-card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            font-size: 0.875rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
        }

        .card-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 0.25rem;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        /* Patient Info Card */
        .patient-info-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--textcolor);
        }

        /* Vitals Overview */
        .vitals-overview {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
        }

        .vitals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .vital-item {
            text-align: center;
            padding: 1.25rem 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .vital-item:hover {
            background: #f0f9ff;
            border-color: var(--primarycolor);
        }

        .vital-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .vital-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primarycolor);
        }

        .vital-unit {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-left: 2px;
        }

        .vital-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            margin-top: 0.5rem;
            display: inline-block;
        }

        .status-normal {
            background: #d1fae5;
            color: #065f46;
        }

        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .status-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Records List */
        .records-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
            display: none; /* Initially hide all tab content */
        }

        .records-section.active {
            display: block; /* Show active tab content */
            animation: fadeIn 0.3s ease;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .section-count {
            background: var(--accentcolor);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Tabs */
        .tabs-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 8px;
            overflow-x: auto;
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #64748b;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            min-width: 120px;
        }

        .tab-btn:hover {
            background: #f1f5f9;
            color: var(--textcolor);
        }

        .tab-btn.active {
            background: var(--primarycolor);
            color: white;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .tab-badge {
            background: #ef4444;
            color: white;
            border-radius: 9999px;
            padding: 0.125rem 0.375rem;
            font-size: 0.625rem;
            font-weight: 600;
        }

        .tab-btn.active .tab-badge {
            background: white;
            color: var(--primarycolor);
        }

        /* Record Cards */
        .record-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .record-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.25rem;
            border-left: 4px solid var(--primarycolor);
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        .record-card:hover {
            background: #f0f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .record-date {
            font-size: 1rem;
            font-weight: 600;
            color: var(--textcolor);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .record-type {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            background: var(--primarycolor);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doctor-info {
            margin-bottom: 1rem;
        }

        .doctor-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 0.25rem;
        }

        .doctor-specialty {
            font-size: 0.875rem;
            color: #64748b;
        }

        .record-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .preview-item {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .preview-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .preview-content {
            font-size: 0.875rem;
            color: var(--textcolor);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .record-vitals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .vital-preview {
            text-align: center;
            padding: 0.5rem;
            background: white;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .vital-preview-label {
            font-size: 0.625rem;
            color: #94a3b8;
            margin-bottom: 0.125rem;
            text-transform: uppercase;
        }

        .vital-preview-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primarycolor);
        }

        .record-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .prescription-count {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .expand-btn {
            background: transparent;
            border: 1px solid var(--primarycolor);
            color: var(--primarycolor);
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .expand-btn:hover {
            background: var(--primarycolor);
            color: white;
        }

        /* Expanded View */
        .expanded-view {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            margin-top: 1rem;
        }

        .expanded-view.active {
            max-height: 2000px;
        }

        .expanded-section {
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-content {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--textcolor);
        }

        .prescriptions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .prescription-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--secondarycolor);
        }

        .medication-name {
            font-weight: 600;
            color: var(--textcolor);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .prescription-details {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            margin-bottom: 0.125rem;
        }

        /* Clinical Notes */
        .clinical-notes-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .clinical-note-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--secondarycolor);
        }

        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .note-date {
            font-weight: 600;
            color: var(--textcolor);
            font-size: 0.875rem;
        }

        .note-doctor {
            color: #64748b;
            font-size: 0.75rem;
        }

        .note-section {
            margin-bottom: 0.75rem;
        }

        .note-label {
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .note-content {
            font-size: 0.875rem;
            color: var(--textcolor);
            line-height: 1.5;
            white-space: pre-wrap;
        }

        /* Vitals History */
        .vitals-history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .vital-history-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid var(--border-color);
        }

        .history-date {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        /* Appointment Cards for Medical History Tab */
        .appointment-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.25rem;
            border-left: 4px solid var(--primarycolor);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .appointment-card:hover {
            background: #f0f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .appointment-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--textcolor);
        }

        .appointment-date {
            font-size: 0.875rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .appointment-detail {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 0.875rem;
            color: var(--textcolor);
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .empty-subtitle {
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--primarycolor);
            border: 2px solid var(--primarycolor);
        }

        .btn-outline:hover {
            background: var(--primarycolor);
            color: white;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }
            
            .vitals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .record-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .record-preview {
                grid-template-columns: 1fr;
            }
            
            .appointment-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .record-footer {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
            
            .tabs-navigation {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .prescriptions-grid {
                grid-template-columns: 1fr;
            }
            
            .vitals-history-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .vitals-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .tabs-navigation {
                flex-direction: column;
            }
            
            .tab-btn {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <h1>My Medical Records</h1>
            <p>View your complete health history, prescriptions, and medical information</p>
        </div>

        <!-- Overview Stats -->
        <div class="overview-grid">
            <div class="overview-card">
                <div class="card-title">Total Visits</div>
                <div class="card-value"><?php echo $stats['total_visits'] ?? 0; ?></div>
                <div class="card-subtitle">Medical consultations</div>
            </div>
            <div class="overview-card">
                <div class="card-title">Doctors Seen</div>
                <div class="card-value"><?php echo $stats['doctors_seen'] ?? 0; ?></div>
                <div class="card-subtitle">Healthcare professionals</div>
            </div>
            <div class="overview-card">
                <div class="card-title">Prescriptions</div>
                <div class="card-value"><?php echo $stats['total_prescriptions'] ?? 0; ?></div>
                <div class="card-subtitle">Medication records</div>
            </div>
            <div class="overview-card">
                <div class="card-title">Medical Records</div>
                <div class="card-value"><?php echo $stats['medical_records_count'] ?? 0; ?></div>
                <div class="card-subtitle">Health documents</div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="patient-info-card">
            <div class="section-header">
                <h2>
                    <i class="fas fa-user-injured"></i>
                    Patient Information
                </h2>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['id']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?php echo !empty($patient['date_of_birth']) ? date('M j, Y', strtotime($patient['date_of_birth'])) : 'Not recorded'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?php echo ucfirst($patient['gender'] ?? 'Not specified'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['phone'] ?? 'Not recorded'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Blood Type</span>
                    <span class="info-value"><?php echo $patient['blood_type'] ?: 'Not recorded'; ?></span>
                </div>
            </div>
        </div>

        <!-- Latest Vitals -->
        <div class="vitals-overview">
            <div class="vitals-header">
                <h2>
                    <i class="fas fa-heartbeat"></i>
                    Latest Vital Signs
                </h2>
                <?php if ($latest_vitals && $latest_vitals['recorded_at']): ?>
                    <span class="card-subtitle">Recorded: <?php echo date('M j, Y g:i A', strtotime($latest_vitals['recorded_at'])); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($latest_vitals): ?>
                <div class="vitals-grid">
                    <?php if ($latest_vitals['blood_pressure']): ?>
                        <div class="vital-item">
                            <div class="vital-label">Blood Pressure</div>
                            <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['blood_pressure']); ?><span class="vital-unit">mmHg</span></div>
                            <?php 
                            $bp_systolic = $latest_vitals['blood_pressure_systolic'];
                            $bp_diastolic = $latest_vitals['blood_pressure_diastolic'];
                            $bp_status = '';
                            
                            if ($bp_systolic && $bp_diastolic) {
                                if ($bp_systolic < 120 && $bp_diastolic < 80) {
                                    $bp_status = 'Normal';
                                } elseif ($bp_systolic >= 120 && $bp_systolic <= 129 && $bp_diastolic < 80) {
                                    $bp_status = 'Elevated';
                                } elseif (($bp_systolic >= 130 && $bp_systolic <= 139) || ($bp_diastolic >= 80 && $bp_diastolic <= 89)) {
                                    $bp_status = 'Stage 1 Hypertension';
                                } elseif ($bp_systolic >= 140 || $bp_diastolic >= 90) {
                                    $bp_status = 'Stage 2 Hypertension';
                                }
                            }
                            ?>
                            <?php if ($bp_status): ?>
                                <div class="vital-status <?php echo strtolower(str_replace(' ', '-', $bp_status)); ?>">
                                    <?php echo $bp_status; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($latest_vitals['heart_rate']): ?>
                        <div class="vital-item">
                            <div class="vital-label">Heart Rate</div>
                            <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['heart_rate']); ?><span class="vital-unit">bpm</span></div>
                            <?php 
                            $hr_status = '';
                            if ($latest_vitals['heart_rate'] < 60) {
                                $hr_status = 'Bradycardia';
                            } elseif ($latest_vitals['heart_rate'] >= 60 && $latest_vitals['heart_rate'] <= 100) {
                                $hr_status = 'Normal';
                            } elseif ($latest_vitals['heart_rate'] > 100) {
                                $hr_status = 'Tachycardia';
                            }
                            ?>
                            <?php if ($hr_status): ?>
                                <div class="vital-status <?php echo strtolower($hr_status); ?>">
                                    <?php echo $hr_status; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($latest_vitals['temperature']): ?>
                        <div class="vital-item">
                            <div class="vital-label">Temperature</div>
                            <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['temperature']); ?><span class="vital-unit">°F</span></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($latest_vitals['oxygen_saturation']): ?>
                        <div class="vital-item">
                            <div class="vital-label">Oxygen Saturation</div>
                            <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['oxygen_saturation']); ?><span class="vital-unit">%</span></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($latest_vitals['weight']): ?>
                        <div class="vital-item">
                            <div class="vital-label">Weight</div>
                            <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['weight']); ?><span class="vital-unit">kg</span></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($latest_vitals['height']): ?>
                        <div class="vital-item">
                            <div class="vital-label">Height</div>
                            <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['height']); ?><span class="vital-unit">cm</span></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($bmi): ?>
                        <div class="vital-item">
                            <div class="vital-label">Body Mass Index</div>
                            <div class="vital-value"><?php echo number_format($bmi, 1); ?></div>
                            <div class="vital-status <?php echo strtolower($bmi_category); ?>">
                                <?php echo $bmi_category; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-heartbeat"></i>
                    <h3 class="empty-title">No Vitals Recorded</h3>
                    <p class="empty-subtitle">Vital signs will be recorded during your appointments</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-navigation">
            <button class="tab-btn active" data-tab="records">
                <i class="fas fa-file-medical"></i>
                Medical History
                <span class="tab-badge"><?php echo count($medical_records); ?></span>
            </button>
            <button class="tab-btn" data-tab="prescriptions">
                <i class="fas fa-pills"></i>
                Prescriptions
                <span class="tab-badge"><?php echo count($recent_prescriptions); ?></span>
            </button>
            <button class="tab-btn" data-tab="notes">
                <i class="fas fa-stethoscope"></i>
                Clinical Notes
                <span class="tab-badge"><?php echo count($clinical_notes); ?></span>
            </button>
            <button class="tab-btn" data-tab="vitals">
                <i class="fas fa-heartbeat"></i>
                Vitals History
                <span class="tab-badge"><?php echo count($vitals_history); ?></span>
            </button>
        </div>

        <!-- Medical History Tab (Appointment History with Diagnosis) -->
        <div id="records-tab" class="records-section active">
            <div class="section-header">
                <h2>Appointment History & Diagnoses</h2>
                <span class="section-count"><?php echo count($medical_records); ?> records</span>
            </div>
            
            <?php if (count($medical_records) > 0): ?>
                <div class="record-cards">
                    <?php foreach ($medical_records as $record): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <div class="appointment-title">
                                    <i class="fas fa-calendar-check"></i>
                                    Appointment #<?php echo htmlspecialchars($record['appointment_number'] ?? 'N/A'); ?>
                                </div>
                                <div class="appointment-date">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    if ($record['appointment_date']) {
                                        echo date('F j, Y', strtotime($record['appointment_date']));
                                        if ($record['appointment_time']) {
                                            echo ' at ' . date('g:i A', strtotime($record['appointment_time']));
                                        }
                                    } else {
                                        echo 'Date not specified';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="appointment-details">
                                <div class="appointment-detail">
                                    <div class="detail-label">Doctor</div>
                                    <div class="detail-value">Dr. <?php echo htmlspecialchars($record['doctor_name'] ?? 'Not specified'); ?></div>
                                </div>
                                <?php if ($record['doctor_specialty']): ?>
                                    <div class="appointment-detail">
                                        <div class="detail-label">Specialty</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($record['doctor_specialty']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($record['diagnosis']): ?>
                                    <div class="appointment-detail">
                                        <div class="detail-label">Primary Diagnosis</div>
                                        <div class="detail-value">
                                            <?php 
                                            $diagnosis = $record['diagnosis'];
                                            echo htmlspecialchars(strlen($diagnosis) > 100 ? substr($diagnosis, 0, 100) . '...' : $diagnosis);
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($record['diagnosis'] || $record['treatment'] || $record['notes']): ?>
                                <button class="expand-btn" onclick="toggleAppointmentDetails(<?php echo $record['id']; ?>)">
                                    <i class="fas fa-chevron-down"></i>
                                    View Full Details
                                </button>
                                
                                <!-- Expanded View -->
                                <div id="appointment-details-<?php echo $record['id']; ?>" class="expanded-view">
                                    <?php if ($record['diagnosis']): ?>
                                        <div class="expanded-section">
                                            <div class="section-title">
                                                <i class="fas fa-diagnoses"></i>
                                                Full Diagnosis
                                            </div>
                                            <div class="section-content"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($record['treatment']): ?>
                                        <div class="expanded-section">
                                            <div class="section-title">
                                                <i class="fas fa-prescription-bottle-alt"></i>
                                                Treatment Plan
                                            </div>
                                            <div class="section-content"><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($record['notes']): ?>
                                        <div class="expanded-section">
                                            <div class="section-title">
                                                <i class="fas fa-sticky-note"></i>
                                                Additional Notes
                                            </div>
                                            <div class="section-content"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($record['prescription_count'] > 0): ?>
                                        <div class="expanded-section">
                                            <div class="section-title">
                                                <i class="fas fa-pills"></i>
                                                Prescriptions
                                            </div>
                                            <div class="section-content">
                                                <p><strong><?php echo $record['prescription_count']; ?> prescription(s)</strong> were prescribed during this appointment.</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-medical"></i>
                    <h3 class="empty-title">No Appointment History Found</h3>
                    <p class="empty-subtitle">Your medical history will appear here after you complete appointments with doctors.</p>
                    <div class="action-buttons">
                        <a href="book_appointment.php" class="btn btn-primary">Book Appointment</a>
                        <a href="patient_appointments.php" class="btn btn-outline">View Appointments</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Prescriptions Tab (Medicines with Instructions) -->
        <div id="prescriptions-tab" class="records-section">
            <div class="section-header">
                <h2>Prescriptions & Medications</h2>
                <span class="section-count"><?php echo count($recent_prescriptions); ?> prescriptions</span>
            </div>
            
            <?php if (count($recent_prescriptions) > 0): ?>
                <div class="prescriptions-grid">
                    <?php foreach ($recent_prescriptions as $prescription): ?>
                        <div class="prescription-card">
                            <div class="medication-name">
                                <i class="fas fa-capsules"></i>
                                <?php echo htmlspecialchars($prescription['medication_name']); ?>
                            </div>
                            <div class="prescription-details">
                                <div class="detail-item">
                                    <span class="detail-label">Dosage:</span>
                                    <span><?php echo htmlspecialchars($prescription['dosage']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Frequency:</span>
                                    <span><?php echo htmlspecialchars($prescription['frequency']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Duration:</span>
                                    <span><?php echo htmlspecialchars($prescription['duration']); ?></span>
                                </div>
                                
                            </div>
                            <?php if ($prescription['instructions']): ?>
                                <div class="expanded-section">
                                    <div class="section-title">
                                        <i class="fas fa-info-circle"></i>
                                        Instructions
                                    </div>
                                    <div class="section-content" style="padding: 0.75rem; margin-top: 0.5rem;">
                                        <?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8;">
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        Prescribed: <?php echo $prescription['formatted_date']; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-user-md"></i>
                                        Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                                    </span>
                                </div>
                                <div style="margin-top: 0.5rem;">
                                    <span class="vital-status <?php echo strtolower($prescription['status'] ?? 'active'); ?>">
                                        <?php echo ucfirst($prescription['status'] ?? 'Active'); ?>
                                    </span>
                                    <?php if ($prescription['dispensed'] ?? false): ?>
                                        <span class="vital-status status-normal" style="margin-left: 0.5rem;">
                                            <i class="fas fa-check-circle"></i> Dispensed
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <h3 class="empty-title">No Prescriptions Found</h3>
                    <p class="empty-subtitle">Prescriptions will appear here after they are prescribed by your doctor.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Clinical Notes Tab (SOAP Format) -->
        <div id="notes-tab" class="records-section">
            <div class="section-header">
                <h2>Clinical Notes (SOAP Format)</h2>
                <span class="section-count"><?php echo count($clinical_notes); ?> notes</span>
            </div>
            
            <?php if (count($clinical_notes) > 0): ?>
                <div class="clinical-notes-grid">
                    <?php foreach ($clinical_notes as $note): ?>
                        <div class="clinical-note-card">
                            <div class="note-header">
                                <div class="note-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo $note['formatted_date']; ?>
                                </div>
                                <div class="note-doctor">
                                    <i class="fas fa-user-md"></i>
                                    Dr. <?php echo htmlspecialchars($note['doctor_name'] ?? 'Unknown'); ?>
                                </div>
                            </div>
                            
                            
                            
                            <?php if ($note['subjective']): ?>
                                <div class="note-section">
                                    <div class="note-label">
                                        <i class="fas fa-comment-medical"></i>
                                        Subjective (Symptoms)
                                    </div>
                                    <div class="note-content"><?php echo nl2br(htmlspecialchars($note['subjective'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($note['objective']): ?>
                                <div class="note-section">
                                    <div class="note-label">
                                        <i class="fas fa-clipboard-check"></i>
                                        Objective (Findings)
                                    </div>
                                    <div class="note-content"><?php echo nl2br(htmlspecialchars($note['objective'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($note['assessment']): ?>
                                <div class="note-section">
                                    <div class="note-label">
                                        <i class="fas fa-stethoscope"></i>
                                        Assessment (Diagnosis)
                                    </div>
                                    <div class="note-content"><?php echo nl2br(htmlspecialchars($note['assessment'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($note['plan']): ?>
                                <div class="note-section">
                                    <div class="note-label">
                                        <i class="fas fa-tasks"></i>
                                        Plan (Treatment)
                                    </div>
                                    <div class="note-content"><?php echo nl2br(htmlspecialchars($note['plan'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                         
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-stethoscope"></i>
                    <h3 class="empty-title">No Clinical Notes Found</h3>
                    <p class="empty-subtitle">Clinical notes will appear here after your doctor documents them during appointments.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Vitals History Tab -->
        <div id="vitals-tab" class="records-section">
            <div class="section-header">
                <h2>Vitals History</h2>
                <span class="section-count"><?php echo count($vitals_history); ?> readings</span>
            </div>
            
            <?php if (count($vitals_history) > 0): ?>
                <div class="vitals-history-grid">
                    <?php foreach ($vitals_history as $index => $vital): ?>
                        <div class="vital-history-card">
                            <div class="history-date">
                                <i class="fas fa-clock"></i>
                                <?php echo date('M j, Y g:i A', strtotime($vital['recorded_at'])); ?>
                                <?php if ($index === 0): ?>
                                    <span class="vital-status status-normal" style="margin-left: 0.5rem; font-size: 0.625rem;">
                                        <i class="fas fa-star"></i> Latest
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="vitals-grid" style="grid-template-columns: repeat(2, 1fr); gap: 0.5rem;">
                                <?php if ($vital['blood_pressure']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Blood Pressure</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($vital['blood_pressure']); ?><span class="vital-unit">mmHg</span></div>
                                        <?php 
                                        // Calculate BP status
                                        if (strpos($vital['blood_pressure'], '/') !== false) {
                                            list($systolic, $diastolic) = explode('/', $vital['blood_pressure']);
                                            $systolic = intval(trim($systolic));
                                            $diastolic = intval(trim($diastolic));
                                            
                                            if ($systolic < 120 && $diastolic < 80) {
                                                $status = 'Normal';
                                            } elseif ($systolic >= 120 && $systolic <= 129 && $diastolic < 80) {
                                                $status = 'Elevated';
                                            } elseif (($systolic >= 130 && $systolic <= 139) || ($diastolic >= 80 && $diastolic <= 89)) {
                                                $status = 'Stage 1';
                                            } elseif ($systolic >= 140 || $diastolic >= 90) {
                                                $status = 'Stage 2';
                                            }
                                            
                                            if (isset($status)): ?>
                                                <div class="vital-status <?php echo strtolower(str_replace(' ', '-', $status)); ?>">
                                                    <?php echo $status; ?>
                                                </div>
                                            <?php endif;
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['heart_rate']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Heart Rate</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($vital['heart_rate']); ?><span class="vital-unit">bpm</span></div>
                                        <?php 
                                        $hr = intval($vital['heart_rate']);
                                        if ($hr < 60) {
                                            $status = 'Low';
                                        } elseif ($hr >= 60 && $hr <= 100) {
                                            $status = 'Normal';
                                        } else {
                                            $status = 'High';
                                        }
                                        ?>
                                        <div class="vital-status <?php echo strtolower($status); ?>">
                                            <?php echo $status; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['temperature']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Temperature</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($vital['temperature']); ?><span class="vital-unit">°F</span></div>
                                        <?php 
                                        $temp = floatval($vital['temperature']);
                                        if ($temp < 97.5) {
                                            $status = 'Low';
                                        } elseif ($temp >= 97.5 && $temp <= 99.5) {
                                            $status = 'Normal';
                                        } else {
                                            $status = 'Fever';
                                        }
                                        ?>
                                        <div class="vital-status <?php echo strtolower($status); ?>">
                                            <?php echo $status; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['oxygen_saturation']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">SpO2</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($vital['oxygen_saturation']); ?><span class="vital-unit">%</span></div>
                                        <?php 
                                        $spo2 = floatval($vital['oxygen_saturation']);
                                        if ($spo2 >= 95) {
                                            $status = 'Normal';
                                        } elseif ($spo2 >= 90 && $spo2 < 95) {
                                            $status = 'Low';
                                        } else {
                                            $status = 'Critical';
                                        }
                                        ?>
                                        <div class="vital-status <?php echo strtolower($status); ?>">
                                            <?php echo $status; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['weight']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Weight</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($vital['weight']); ?><span class="vital-unit">kg</span></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['height']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Height</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($vital['height']); ?><span class="vital-unit">cm</span></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($vital['recorded_by_name']): ?>
                                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #94a3b8; text-align: center;">
                                    <i class="fas fa-user-nurse"></i>
                                    Recorded by: <?php echo htmlspecialchars($vital['recorded_by_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-heartbeat"></i>
                    <h3 class="empty-title">No Vitals History Found</h3>
                    <p class="empty-subtitle">Vital signs will be recorded during your appointments.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize tabs on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set first tab as active
            const firstTabBtn = document.querySelector('.tab-btn.active');
            const firstTabContent = document.getElementById('records-tab');
            
            if (firstTabBtn && firstTabContent) {
                firstTabContent.classList.add('active');
            }
            
            // Add click event listeners to all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    switchTab(tabName);
                });
            });
            
            // Animate cards on scroll
            const cards = document.querySelectorAll('.record-card, .appointment-card, .prescription-card, .clinical-note-card, .vital-history-card');
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            cards.forEach(card => {
                observer.observe(card);
            });
        });

        // Switch between tabs
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.records-section').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Activate selected tab button
            const activeButton = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
            
            // Scroll to top of tab content
            if (selectedTab) {
                selectedTab.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Toggle expanded view for appointment details
        function toggleAppointmentDetails(appointmentId) {
            const expandedView = document.getElementById('appointment-details-' + appointmentId);
            const button = event.target.closest('.expand-btn');
            
            if (expandedView.classList.contains('active')) {
                expandedView.classList.remove('active');
                if (button) {
                    button.innerHTML = '<i class="fas fa-chevron-down"></i> View Full Details';
                    button.style.marginBottom = '0';
                }
            } else {
                // Close other expanded views in the same tab
                const parentTab = expandedView.closest('.records-section');
                parentTab.querySelectorAll('.expanded-view.active').forEach(view => {
                    if (view.id !== 'appointment-details-' + appointmentId) {
                        view.classList.remove('active');
                        const otherBtn = view.parentElement.querySelector('.expand-btn');
                        if (otherBtn) otherBtn.innerHTML = '<i class="fas fa-chevron-down"></i> View Full Details';
                    }
                });
                
                expandedView.classList.add('active');
                if (button) {
                    button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
                    button.style.marginBottom = '1rem';
                }
            }
        }

        // Toggle expanded view for records
        function toggleRecord(recordId) {
            const expandedView = document.getElementById(recordId);
            const button = event.currentTarget.closest('.expand-btn') || event.target;
            
            if (expandedView.classList.contains('active')) {
                expandedView.classList.remove('active');
                if (button) {
                    button.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
                }
            } else {
                // Close other expanded views in the same tab
                const parentTab = expandedView.closest('.records-section');
                parentTab.querySelectorAll('.expanded-view.active').forEach(view => {
                    if (view.id !== recordId) {
                        view.classList.remove('active');
                        const otherBtn = view.parentElement.querySelector('.expand-btn');
                        if (otherBtn) otherBtn.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
                    }
                });
                
                expandedView.classList.add('active');
                if (button) {
                    button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
                }
            }
        }
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>

