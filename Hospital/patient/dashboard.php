<?php
session_start();
include "includes/db_connect.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get the logged-in patient's ID from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: login.php?role=patient");
    exit();
}
include "includes/sidebar.php";
// Fetch Patient Information by joining users and patients tables
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            u.username, 
            u.email as login_email,
            u.first_name as user_first_name,
            u.last_name as user_last_name,
            u.phone as user_phone
        FROM users u
        LEFT JOIN patients p ON (u.email = p.email OR u.phone = p.phone)
        WHERE u.id = ? AND u.role = 'Patient' AND u.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        die("Patient not found or account inactive.");
    }
    
    // If no patient record found but user exists, create basic patient info from user data
    if (!$patient['patient_id']) {
        $patient['patient_id'] = 'PT' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $patient['first_name'] = $patient['user_first_name'];
        $patient['last_name'] = $patient['user_last_name'];
        $patient['email'] = $patient['login_email'];
        $patient['phone'] = $patient['user_phone'];
        $patient['gender'] = 'Other';
        $patient['date_of_birth'] = '2000-01-01';
        $patient['age'] = date('Y') - 2000;
        $patient['status'] = 'Active';
    }
} catch (PDOException $e) {
    die("Error fetching patient information: " . $e->getMessage());
}

// ===== FETCH DATA FROM THE SAME SOURCES AS patient_medical_records.php =====

// Get patient's latest vitals (from recent_patient_vitals table as in medical_records.php)
$latest_vitals = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM recent_patient_vitals 
        WHERE patient_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$patient['id']]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $latest_vitals = [];
}

// Get patient statistics (similar to medical_records.php)
$stats = [];
try {
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
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_visits' => 0, 'doctors_seen' => 0, 'total_prescriptions' => 0, 'medical_records_count' => 0];
}

// Dashboard-specific stats
$stats['upcoming_appointments'] = 0;
$stats['lab_results'] = 0;
$stats['vital_signs'] = 0;
$stats['today_vitals'] = 0;
$stats['active_prescriptions'] = 0;

// 1. Upcoming Appointments
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE patient_id = ? 
        AND DATE(appointment_date) >= CURDATE()
        AND status IN ('scheduled', 'confirmed')
    ");
    $stmt->execute([$patient['id']]);
    $stats['upcoming_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['upcoming_appointments'] = 0;
}

// 2. Current Prescriptions (active ones)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE patient_id = ? 
        AND status = 'active'
        AND (end_date IS NULL OR end_date >= CURDATE())
    ");
    $stmt->execute([$patient['id']]);
    $stats['active_prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['active_prescriptions'] = 0;
}

// 3. Lab Results
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM lab_orders lo
        WHERE lo.patient_id = ? 
        AND lo.status = 'completed'
    ");
    $stmt->execute([$patient['id']]);
    $stats['lab_results'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['lab_results'] = 0;
}

// 4. Vital Signs Records (from recent_patient_vitals)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM recent_patient_vitals 
        WHERE patient_id = ?
    ");
    $stmt->execute([$patient['id']]);
    $stats['vital_signs'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['vital_signs'] = 0;
}

// 5. Today's Vital Signs
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM recent_patient_vitals 
        WHERE patient_id = ? 
        AND DATE(recorded_at) = CURDATE()
    ");
    $stmt->execute([$patient['id']]);
    $stats['today_vitals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['today_vitals'] = 0;
}

// Upcoming Appointments Details
$upcoming_appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            d.specialization,
            d.room_number
        FROM appointments a
        JOIN users d ON a.doctor_id = d.id
        WHERE a.patient_id = ?
        AND DATE(a.appointment_date) >= CURDATE()
        AND a.status IN ('scheduled', 'confirmed')
        ORDER BY a.appointment_date ASC
        LIMIT 5
    ");
    $stmt->execute([$patient['id']]);
    $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $upcoming_appointments = [];
}

// Current Medications - FROM PRESCRIPTIONS TABLE (as in medical_records.php)
$current_medications = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            DATE_FORMAT(p.prescription_date, '%M %d, %Y') as formatted_date
        FROM prescriptions p
        LEFT JOIN users d ON p.doctor_id = d.id
        WHERE p.patient_id = ?
        AND p.status = 'active'
        AND (p.end_date IS NULL OR p.end_date >= CURDATE())
        ORDER BY p.prescription_date DESC
        LIMIT 5
    ");
    $stmt->execute([$patient['id']]);
    $current_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $current_medications = [];
}

// Recent Lab Orders
$recent_lab_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            lo.*,
            COUNT(loi.id) as test_count
        FROM lab_orders lo
        LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
        WHERE lo.patient_id = ?
        GROUP BY lo.id
        ORDER BY lo.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([$patient['id']]);
    $recent_lab_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_lab_orders = [];
}

// Health Metrics Chart Data (Last 14 days) - FROM recent_patient_vitals
$health_metrics = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(recorded_at) as date,
            AVG(CAST(temperature AS DECIMAL(10,2))) as avg_temp,
            AVG(CAST(heart_rate AS DECIMAL(10,2))) as avg_hr,
            AVG(CAST(oxygen_saturation AS DECIMAL(10,2))) as avg_spo2,
            AVG(CAST(weight AS DECIMAL(10,2))) as avg_weight
        FROM recent_patient_vitals 
        WHERE patient_id = ?
        AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(recorded_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$patient['id']]);
    $health_metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $health_metrics = [];
}

// Calculate BMI and other health indicators if we have height and weight
$bmi = null;
$bmi_category = null;
if ($latest_vitals && isset($latest_vitals['weight']) && isset($latest_vitals['height'])) {
    $height_m = floatval($latest_vitals['height']) / 100;
    $weight = floatval($latest_vitals['weight']);
    
    if ($height_m > 0 && $weight > 0) {
        $bmi = $weight / ($height_m * $height_m);
        
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
}

// Check if patient needs to complete profile
$profile_complete = true;
if (!$patient['date_of_birth'] || $patient['date_of_birth'] == '2000-01-01' || 
    !$patient['gender'] || $patient['gender'] == 'Other' ||
    empty($patient['height']) || empty($patient['weight'])) {
    $profile_complete = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
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

        /* Welcome Card */
        .welcome-card {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .welcome-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
        }

        .welcome-content {
            flex: 1;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .patient-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .patient-details {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .patient-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .patient-id-badge {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
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
            border: 1px solid transparent;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-color: #fbbf24;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            cursor: pointer;
            border-left: 4px solid #3b82f6;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .stat-card {
                padding: 1rem;
            }
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.blue { background: #3b82f6; }
        .stat-icon.green { background: #10b981; }
        .stat-icon.purple { background: #8b5cf6; }
        .stat-icon.orange { background: #f59e0b; }
        .stat-icon.red { background: #ef4444; }
        .stat-icon.teal { background: #14b8a6; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Data Card */
        .data-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .data-card {
                padding: 1rem;
            }
        }

        .data-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .data-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-all-link {
            font-size: 0.875rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        /* Health Metrics */
        .health-metrics {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 640px) {
            .health-metrics {
                grid-template-columns: 1fr;
            }
        }

        .metric-card {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            border-left: 3px solid #3b82f6;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .metric-label {
            font-size: 0.875rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .metric-status {
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .status-normal { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-danger { background: #fee2e2; color: #991b1b; }

        .metric-timestamp {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.5rem;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-top: 1rem;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .data-table tr {
            cursor: pointer;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-scheduled { background: #dbeafe; color: #1d4ed8; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #f3f4f6; color: #374151; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-in_progress { background: #fef3c7; color: #92400e; }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        @media (max-width: 640px) {
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }

        .quick-action {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: #374151;
            transition: all 0.2s;
        }

        .quick-action:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            transform: translateY(-1px);
        }

        .quick-action-icon {
            width: 3rem;
            height: 3rem;
            background: #eff6ff;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #3b82f6;
            margin: 0 auto 0.75rem;
        }

        .quick-action-label {
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        @media (max-width: 640px) {
            .no-data {
                padding: 2rem 1rem;
            }
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .no-data h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .no-data p {
            font-size: 0.875rem;
        }

        /* Mobile Menu Button */
        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: white;
                border: none;
                border-radius: 0.375rem;
                padding: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                cursor: pointer;
            }
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        /* BMI Display */
        .bmi-display {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 3px solid #3b82f6;
        }

        .bmi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .bmi-category {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .bmi-normal { color: #065f46; }
        .bmi-underweight { color: #d97706; }
        .bmi-overweight { color: #92400e; }
        .bmi-obese { color: #dc2626; }
    </style>
</head>
<body>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="main-content">
    <!-- Page Header -->
    <!-- <div class="page-header">
        <div>
            <h1>Patient Dashboard</h1>
            <p>Welcome back! Here's an overview of your health and appointments</p>
        </div>
    </div> -->

    <!-- Add SOS Emergency Button -->
<div class="sos-emergency-section" style="margin-top: 20px;">
    <button id="sosEmergencyBtn" class="btn-sos-emergency" 
            style="background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
                   color: white;
                   border: none;
                   padding: 25px 30px;
                   border-radius: 30px;
                   font-size: 20px;
                   font-weight: bold;
                   cursor: pointer;
                   display: flex;
                   align-items: center;
                   gap: 10px;
                   width: 100%;
                   justify-content: center;
                   animation: sosPulse 2s infinite;">
        <i class="fas fa-first-aid"></i>
        EMERGENCY SOS - MEDICAL ASSISTANCE NEEDED
    </button>
    
    <div id="sosConfirmation" style="display: none; margin-top: 15px; padding: 15px; 
                                     background: #ffeaea; border-radius: 8px; border: 2px solid #f44336;">
        <h3 style="color: #d32f2f; margin-bottom: 10px;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Emergency Alert
        </h3>
        <p style="margin-bottom: 15px;">Are you experiencing a medical emergency?</p>
        <div style="display: flex; gap: 10px;">
            <button id="confirmSOS" class="btn" style="background: #f44336; color: white;">YES, SEND EMERGENCY ALERT</button>
            <button id="cancelSOS" class="btn" style="background: #666; color: white;">CANCEL</button>
        </div>
    </div>
    
    <div id="sosSuccess" style="display: none; margin-top: 15px; padding: 15px; 
                               background: #d1fae5; border-radius: 8px; border: 2px solid #10b981;">
        <h3 style="color: #065f46; margin-bottom: 10px;">
            <i class="fas fa-check-circle"></i> Emergency Alert Sent!
        </h3>
        <p>Help is on the way. Emergency services have been notified.</p>
        <p><strong>Stay calm and wait for assistance.</strong></p>
    </div>
</div>

<style>
@keyframes sosPulse {
    0% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7); }
    70% { box-shadow: 0 0 0 15px rgba(244, 67, 54, 0); }
    100% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
}
</style>

<br>
<br>
    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="welcome-content">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div class="patient-avatar">
                    <?php 
                        $initials = substr($patient['first_name'] ?? 'P', 0, 1) . substr($patient['last_name'] ?? 'T', 0, 1);
                        echo strtoupper($initials);
                    ?>
                </div>
                <div>
                    <div class="patient-name"><?php echo htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?></div>
                    <div class="patient-details">
                        <span class="patient-detail">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?> • <?php echo $patient['age'] ?? 'N/A'; ?> years
                        </span>
                        <span class="patient-detail">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'Not recorded'); ?>
                        </span>
                        <span class="patient-detail">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email'] ?? 'Not recorded'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="patient-id-badge">
                Patient ID: <?php echo htmlspecialchars($patient['patient_id'] ?? 'N/A'); ?>
            </div>
        </div>
        <div style="font-size: 0.875rem; color: #64748b;">
            Last updated: <?php echo date('F j, Y g:i A'); ?>
        </div>
    </div>

    
    <?php if (!$profile_complete): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Profile Incomplete</strong>
                <p style="margin-top: 0.25rem;">Please complete your profile for better healthcare services.</p>
            </div>
            <a href="edit_profile.php" class="btn btn-primary" style="margin-left: auto;">
                Complete Profile
            </a>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='my_appointments.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['upcoming_appointments']; ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='patient_medical_records.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['medical_records_count']; ?></div>
                    <div class="stat-label">Medical Records</div>
                </div>
                <div class="stat-icon green">
                    <i class="fas fa-file-medical"></i>
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='patient_medical_records.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['active_prescriptions']; ?></div>
                    <div class="stat-label">Active Prescriptions</div>
                </div>
                <div class="stat-icon purple">
                    <i class="fas fa-prescription-bottle-alt"></i>
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='lab_results.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['lab_results']; ?></div>
                    <div class="stat-label">Lab Results</div>
                </div>
                <div class="stat-icon orange">
                    <i class="fas fa-flask"></i>
                </div>
            </div>
        </div>

       

        <div class="stat-card" onclick="window.location.href='patient_medical_records.php?tab=vitals'">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['vital_signs']; ?></div>
                    <div class="stat-label">All Vitals</div>
                </div>
                <div class="stat-icon red">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div>
            <!-- Health Metrics Summary -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2 class="data-card-title">
                        <i class="fas fa-heartbeat"></i> Health Metrics Summary
                    </h2>
                    <a href="patient_medical_records.php?tab=vitals" class="view-all-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if ($latest_vitals): ?>
                    <div class="health-metrics">
                        <div class="metric-card">
                            <div class="metric-value">
                                <?php echo $latest_vitals['temperature'] ?? 'N/A'; ?>°F
                            </div>
                            <div class="metric-label">
                                <span>Temperature</span>
                                <?php 
                                $temp = $latest_vitals['temperature'] ?? 0;
                                $temp_status = ($temp >= 97.5 && $temp <= 99.5) ? 'status-normal' : 
                                              (($temp < 97.5 || $temp > 100.5) ? 'status-danger' : 'status-warning');
                                ?>
                                <span class="metric-status <?php echo $temp_status; ?>">
                                    <?php echo $temp_status === 'status-normal' ? 'Normal' : 
                                           ($temp_status === 'status-warning' ? 'Elevated' : 'Abnormal'); ?>
                                </span>
                            </div>
                            <div class="metric-timestamp">
                                <i class="far fa-clock"></i> Latest reading
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-value">
                                <?php echo ($latest_vitals['blood_pressure'] ?? '--'); ?>
                            </div>
                            <div class="metric-label">
                                <span>Blood Pressure</span>
                                <?php 
                                $bp = $latest_vitals['blood_pressure'] ?? '';
                                if (preg_match('/(\d+)\/(\d+)/', $bp, $matches)) {
                                    $bp_sys = intval($matches[1]);
                                    $bp_dia = intval($matches[2]);
                                    $bp_status = ($bp_sys <= 120 && $bp_dia <= 80) ? 'status-normal' : 
                                                (($bp_sys <= 130 && $bp_dia <= 85) ? 'status-warning' : 'status-danger');
                                } else {
                                    $bp_status = 'status-warning';
                                }
                                ?>
                                <span class="metric-status <?php echo $bp_status; ?>">
                                    <?php echo $bp_status === 'status-normal' ? 'Normal' : 
                                           ($bp_status === 'status-warning' ? 'Elevated' : 'High'); ?>
                                </span>
                            </div>
                            <div class="metric-timestamp">
                                <i class="far fa-clock"></i> mmHg
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-value">
                                <?php echo $latest_vitals['heart_rate'] ?? 'N/A'; ?> bpm
                            </div>
                            <div class="metric-label">
                                <span>Heart Rate</span>
                                <?php 
                                $hr = $latest_vitals['heart_rate'] ?? 0;
                                $hr_status = ($hr >= 60 && $hr <= 100) ? 'status-normal' : 
                                            (($hr > 40 && $hr < 60) || ($hr > 100 && $hr < 120) ? 'status-warning' : 'status-danger');
                                ?>
                                <span class="metric-status <?php echo $hr_status; ?>">
                                    <?php echo $hr_status === 'status-normal' ? 'Normal' : 
                                           ($hr_status === 'status-warning' ? 'Abnormal' : 'Critical'); ?>
                                </span>
                            </div>
                            <div class="metric-timestamp">
                                <i class="far fa-clock"></i> beats/min
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-value">
                                <?php echo $latest_vitals['oxygen_saturation'] ?? 'N/A'; ?>%
                            </div>
                            <div class="metric-label">
                                <span>Oxygen Level</span>
                                <?php 
                                $spo2 = $latest_vitals['oxygen_saturation'] ?? 0;
                                $spo2_status = ($spo2 >= 95) ? 'status-normal' : 
                                              (($spo2 >= 90) ? 'status-warning' : 'status-danger');
                                ?>
                                <span class="metric-status <?php echo $spo2_status; ?>">
                                    <?php echo $spo2_status === 'status-normal' ? 'Normal' : 
                                           ($spo2_status === 'status-warning' ? 'Low' : 'Critical'); ?>
                                </span>
                            </div>
                            <div class="metric-timestamp">
                                <i class="far fa-clock"></i> SpO2
                            </div>
                        </div>
                    </div>
                    
                    <!-- BMI Display -->
                    <?php if ($bmi): ?>
                    <div class="bmi-display">
                        <div class="bmi-value">BMI: <?php echo number_format($bmi, 1); ?></div>
                        <div class="bmi-category bmi-<?php echo strtolower($bmi_category); ?>">
                            <i class="fas fa-weight-scale"></i> <?php echo $bmi_category; ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
                            Height: <?php echo $latest_vitals['height'] ?? 'N/A'; ?> cm • 
                            Weight: <?php echo $latest_vitals['weight'] ?? 'N/A'; ?> kg
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Chart -->
                    <?php if (!empty($health_metrics)): ?>
                    <div class="chart-container">
                        <canvas id="healthChart"></canvas>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 1rem; font-size: 0.75rem; color: #64748b; text-align: center;">
                        Last recorded: <?php echo date('F j, Y g:i A', strtotime($latest_vitals['recorded_at'])); ?>
                        <?php if (isset($latest_vitals['recorded_by_name'])): ?>
                            by <?php echo htmlspecialchars($latest_vitals['recorded_by_name']); ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-heartbeat"></i>
                        <h3>No Vital Signs Recorded</h3>
                        <p>Your vital signs will appear here once recorded by medical staff.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Appointments -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2 class="data-card-title">
                        <i class="fas fa-calendar-alt"></i> Upcoming Appointments
                    </h2>
                    <a href="my_appointments.php" class="view-all-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if (count($upcoming_appointments) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <tr onclick="window.location.href='appointment_details.php?id=<?php echo $appointment['id']; ?>'">
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            <?php echo htmlspecialchars($appointment['specialization']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['reason'] ?? 'Check-up'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>No Upcoming Appointments</h3>
                        <p>You don't have any scheduled appointments.</p>
                        <a href="book_appointment.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Current Medications (From Prescriptions) -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2 class="data-card-title">
                        <i class="fas fa-prescription-bottle-alt"></i> Current Medications
                    </h2>
                    <a href="patient_medical_records.php?tab=prescriptions" class="view-all-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if (count($current_medications) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Prescribed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_medications as $med): ?>
                                <tr onclick="window.location.href='patient_medical_records.php?tab=prescriptions'">
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($med['medication_name'] ?? 'Unknown'); ?></div>
                                        <?php if ($med['instructions']): ?>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                                                <?php echo substr(htmlspecialchars($med['instructions']), 0, 50); ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($med['dosage'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($med['frequency'] ?? 'As needed'); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($med['doctor_name'] ?? 'Unknown'); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            <?php echo $med['formatted_date'] ?? date('M j, Y', strtotime($med['start_date'] ?? '')); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-prescription-bottle-alt"></i>
                        <h3>No Active Medications</h3>
                        <p>You don't have any current prescriptions.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Quick Actions -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2 class="data-card-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h2>
                </div>
                <div class="quick-actions-grid">
                    <a href="book_appointment.php" class="quick-action">
                        <div class="quick-action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="quick-action-label">Book Appointment</div>
                    </a>
                    
                    <a href="patient_medical_records.php" class="quick-action">
                        <div class="quick-action-icon">
                            <i class="fas fa-file-medical"></i>
                        </div>
                        <div class="quick-action-label">View Records</div>
                    </a>
                    
                    <a href="lab_results.php" class="quick-action">
                        <div class="quick-action-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="quick-action-label">Lab Results</div>
                    </a>
                    
                    <a href="patient_medical_records.php?tab=prescriptions" class="quick-action">
                        <div class="quick-action-icon">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="quick-action-label">Medications</div>
                    </a>
                    
                    <a href="edit_profile.php" class="quick-action">
                        <div class="quick-action-icon">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div class="quick-action-label">Edit Profile</div>
                    </a>
                    
                    <a href="lab_results.php" class="quick-action">
                        <div class="quick-action-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="quick-action-label">Reports</div>
                    </a>
                </div>
            </div>

            <!-- Recent Lab Results -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2 class="data-card-title">
                        <i class="fas fa-flask"></i> Recent Lab Results
                    </h2>
                    <a href="lab_results.php" class="view-all-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if (count($recent_lab_orders) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Date</th>
                                <th>Tests</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_lab_orders as $order): ?>
                                <tr onclick="window.location.href='lab_results.php?view=<?php echo $order['id']; ?>'">
                                    <td>
                                        <div style="font-weight: 500;">
                                            <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo $order['test_count']; ?> test(s)</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data" style="padding: 1.5rem;">
                        <i class="fas fa-flask"></i>
                        <h3>No Lab Results</h3>
                        <p style="font-size: 0.875rem;">Your lab results will appear here</p>
                    </div>
                <?php endif; ?>
            </div>

           
        </div>
    </div>
</div>

<script>
    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar.style.display === 'none' || !sidebar.style.display) {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        } else {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 1024) {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        }
    });
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        }
        
        // Initialize Health Chart
        const healthCtx = document.getElementById('healthChart')?.getContext('2d');
        if (healthCtx && <?php echo !empty($health_metrics) ? 'true' : 'false'; ?>) {
            const healthChart = new Chart(healthCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($date) {
                        return date('M j', strtotime($date['date']));
                    }, $health_metrics)); ?>,
                    datasets: [
                        {
                            label: 'Temperature (°F)',
                            data: <?php echo json_encode(array_map(function($metric) {
                                return $metric['avg_temp'] ?? 0;
                            }, $health_metrics)); ?>,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Heart Rate (bpm)',
                            data: <?php echo json_encode(array_map(function($metric) {
                                return $metric['avg_hr'] ?? 0;
                            }, $health_metrics)); ?>,
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Oxygen (%)',
                            data: <?php echo json_encode(array_map(function($metric) {
                                return $metric['avg_spo2'] ?? 0;
                            }, $health_metrics)); ?>,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: false,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    });
</script>

</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sosBtn = document.getElementById('sosEmergencyBtn');
    const sosConfirmation = document.getElementById('sosConfirmation');
    const sosSuccess = document.getElementById('sosSuccess');
    const confirmSOS = document.getElementById('confirmSOS');
    const cancelSOS = document.getElementById('cancelSOS');
    
    // Get patient ID from PHP
    const patientId = <?php echo $patient['id'] ?? 'null'; ?>;
    
    // Function to get current location
    function getCurrentLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                // Try to get approximate location from IP
                fetch('https://ipapi.co/json/')
                    .then(response => response.json())
                    .then(data => {
                        if (data.latitude && data.longitude) {
                            resolve({
                                latitude: data.latitude,
                                longitude: data.longitude,
                                accuracy: 50000, // Approximate accuracy in meters
                                source: 'ip'
                            });
                        } else {
                            reject('Geolocation not available');
                        }
                    })
                    .catch(() => reject('Cannot determine location'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        source: 'gps'
                    });
                },
                (error) => {
                    // Fallback to IP-based location
                    fetch('https://ipapi.co/json/')
                        .then(response => response.json())
                        .then(data => {
                            if (data.latitude && data.longitude) {
                                resolve({
                                    latitude: data.latitude,
                                    longitude: data.longitude,
                                    accuracy: 50000,
                                    source: 'ip_fallback'
                                });
                            } else {
                                reject('Location access denied and IP location unavailable');
                            }
                        })
                        .catch(() => reject('Location access denied'));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }
    
    // Show confirmation dialog
    sosBtn.addEventListener('click', function() {
        sosConfirmation.style.display = 'block';
        sosBtn.style.display = 'none';
    });
    
    // Cancel SOS
    cancelSOS.addEventListener('click', function() {
        sosConfirmation.style.display = 'none';
        sosBtn.style.display = 'flex';
    });
    
    // Confirm SOS
    confirmSOS.addEventListener('click', async function() {
        if (!patientId) {
            alert('Error: Patient ID not found. Please log in again.');
            return;
        }
        
        try {
            // Update button state
            const originalText = confirmSOS.innerHTML;
            confirmSOS.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Alert...';
            confirmSOS.disabled = true;
            
            // Get current location
            let location;
            try {
                location = await getCurrentLocation();
                console.log('Location obtained:', location);
            } catch (locationError) {
                console.warn('Location error:', locationError);
                // Continue without location
                location = {
                    latitude: null,
                    longitude: null,
                    accuracy: null,
                    source: 'unknown'
                };
            }
            
            // Send SOS alert
            const response = await fetch('api/trigger_sos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    patient_id: patientId,
                    latitude: location.latitude,
                    longitude: location.longitude,
                    accuracy: location.accuracy,
                    source: location.source,
                    timestamp: new Date().toISOString()
                })
            });
            
            const result = await response.json();
            console.log('SOS Response:', result);
            
            if (result.success) {
                // Show success message
                sosConfirmation.style.display = 'none';
                sosSuccess.style.display = 'block';
                
                // Play emergency sound
                playEmergencySound();
                
                // Auto-hide after 30 seconds
                setTimeout(() => {
                    sosSuccess.style.display = 'none';
                    sosBtn.style.display = 'flex';
                }, 30000);
                
            } else {
                // Show error
                alert('Error: ' + result.message);
                resetSOSButtons();
            }
            
        } catch (error) {
            console.error('SOS Error:', error);
            
            // Show fallback message
            const tryAgain = confirm('Failed to send emergency alert. Try again or call emergency services directly?\n\nClick OK to try again, Cancel to call 911.');
            
            if (tryAgain) {
                resetSOSButtons();
            } else {
                // Open phone dialer with emergency number
                window.location.href = 'tel:911';
            }
        }
    });
    
    // Reset button states
    function resetSOSButtons() {
        sosConfirmation.style.display = 'none';
        sosSuccess.style.display = 'none';
        sosBtn.style.display = 'flex';
        confirmSOS.innerHTML = 'YES, SEND EMERGENCY ALERT';
        confirmSOS.disabled = false;
    }
    
    // Play emergency sound
    function playEmergencySound() {
        try {
            // Try to play a sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            // Emergency siren pattern
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(1600, audioContext.currentTime + 0.5);
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1);
            
            oscillator.start();
            
            // Stop after 1 second
            setTimeout(() => {
                oscillator.stop();
                audioContext.close();
            }, 1000);
            
        } catch (e) {
            console.log('Audio not available:', e);
        }
    }
    
    // Optional: Pre-fetch location when hovering over button
    sosBtn.addEventListener('mouseenter', function() {
        getCurrentLocation().catch(() => {
            // Silently fail - we'll handle it when needed
        });
    });
});
</script>