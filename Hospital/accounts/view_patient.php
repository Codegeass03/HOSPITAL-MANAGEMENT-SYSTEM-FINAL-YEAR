<?php
session_start();
include "includes/db_connect.php";


if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get success/error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Check if user is authorized (Admin, Staff, or Doctor)
$user_role = $_SESSION['role'] ?? null;
$allowed_roles = ['Super Admin', 'Staff', 'Doctor', 'Super_Admin'];
if (!isset($_SESSION['user_id']) || !in_array($user_role, $allowed_roles)) {
    $_SESSION['error'] = "Access denied. You don't have permission to view patient details.";
    header("Location: index.php");
    exit;
}

// Get patient ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: patient_management.php");
    exit;
}

$patient_id = $_GET['id'];

// Fetch patient basic information
$patient_stmt = $pdo->prepare("
    SELECT p.*, 
           u.username, 
           u.status as user_status, 
           u.last_login,
           u.created_at as account_created
    FROM patients p
    LEFT JOIN users u ON (p.email = u.email OR p.phone = u.phone) AND u.role = 'Patient'
    WHERE p.id = ? OR p.patient_id = ?
    LIMIT 1
");
$patient_stmt->execute([$patient_id, $patient_id]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: patient_management.php");
    exit;
}

// Calculate age
$age = null;
if (!empty($patient['date_of_birth'])) {
    $dob = new DateTime($patient['date_of_birth']);
    $today = new DateTime();
    $age = $dob->diff($today)->y;
}

// Update age in database if different
if ($age !== null && $age != $patient['age']) {
    $update_stmt = $pdo->prepare("UPDATE patients SET age = ? WHERE id = ?");
    $update_stmt->execute([$age, $patient['id']]);
    $patient['age'] = $age;
}

// Get patient statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT a.doctor_id) as doctors_seen,
        COUNT(DISTINCT p.id) as total_prescriptions,
        COUNT(DISTINCT mr.id) as medical_records_count,
        MIN(a.appointment_date) as first_visit,
        MAX(a.appointment_date) as last_visit
    FROM patients pat
    LEFT JOIN appointments a ON pat.id = a.patient_id AND a.status IN ('completed', 'confirmed')
    LEFT JOIN prescriptions p ON pat.id = p.patient_id
    LEFT JOIN medical_records mr ON pat.id = mr.patient_id
    WHERE pat.id = ?
    GROUP BY pat.id
");
$stats_stmt->execute([$patient['id']]);
$stats = $stats_stmt->fetch();

// Get latest vitals
$vitals_stmt = $pdo->prepare("
    SELECT * FROM recent_patient_vitals 
    WHERE patient_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 1
");
$vitals_stmt->execute([$patient['id']]);
$latest_vitals = $vitals_stmt->fetch();

// Get recent appointments (last 5)
$appointments_stmt = $pdo->prepare("
    SELECT 
        a.*,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        dp.specialty as doctor_specialty
    FROM appointments a
    LEFT JOIN users d ON a.doctor_id = d.id
    LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
$appointments_stmt->execute([$patient['id']]);
$recent_appointments = $appointments_stmt->fetchAll();

// Get active prescriptions
$prescriptions_stmt = $pdo->prepare("
    SELECT 
        p.*,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        DATE_FORMAT(p.prescription_date, '%M %d, %Y') as formatted_date
    FROM prescriptions p
    LEFT JOIN users d ON p.doctor_id = d.id
    WHERE p.patient_id = ? AND p.status = 'active'
    ORDER BY p.prescription_date DESC
    LIMIT 10
");
$prescriptions_stmt->execute([$patient['id']]);
$active_prescriptions = $prescriptions_stmt->fetchAll();

// Get vitals history (last 10 readings)
$vitals_history_stmt = $pdo->prepare("
    SELECT * FROM recent_patient_vitals 
    WHERE patient_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 10
");
$vitals_history_stmt->execute([$patient['id']]);
$vitals_history = $vitals_history_stmt->fetchAll();

// Get medical records (diagnoses)
$medical_records_stmt = $pdo->prepare("
    SELECT 
        mr.*,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        DATE_FORMAT(mr.created_at, '%M %d, %Y') as formatted_date
    FROM medical_records mr
    LEFT JOIN users d ON mr.doctor_id = d.id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
    LIMIT 5
");
$medical_records_stmt->execute([$patient['id']]);
$medical_records = $medical_records_stmt->fetchAll();

// Get allergies and chronic conditions
$allergies = !empty($patient['allergies']) ? explode(',', $patient['allergies']) : [];
$chronic_conditions = !empty($patient['chronic_conditions']) ? explode(',', $patient['chronic_conditions']) : [];

// Calculate BMI if available
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
    <title><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> - Patient Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0891b2;
            --primary-dark: #0e7490;
            --primary-light: #06b6d4;
            --secondary-color: #14b8a6;
            --accent-color: #22d3ee;
            --bg-color: #f0fdfa;
            --text-color: #134e4a;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            line-height: 1.5;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            animation: fadeIn 0.5s ease;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Header Section */
        .page-header {
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .patient-title h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .patient-subtitle {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        .patient-id-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .patient-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fef3c7; color: #92400e; }
        .status-deceased { background: #fee2e2; color: #991b1b; }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(8, 145, 178, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
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

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Patient Summary Card */
        .summary-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(8, 145, 178, 0.08);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .summary-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .summary-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid var(--border-color);
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
            color: var(--text-color);
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Section Cards */
        .section-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(8, 145, 178, 0.08);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Vitals Display */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
        }

        .vital-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid var(--border-color);
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
            color: var(--primary-color);
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

        .status-normal { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-danger { background: #fee2e2; color: #991b1b; }

        /* Lists */
        .item-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .list-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .list-item:hover {
            background: #f0f9ff;
            border-color: var(--primary-color);
        }

        .item-info h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }

        .item-details {
            font-size: 0.75rem;
            color: #64748b;
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Tags */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag {
            background: #dbeafe;
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .tag-allergy {
            background: #fee2e2;
            color: #dc2626;
        }

        .tag-chronic {
            background: #fef3c7;
            color: #d97706;
        }

        .tag-medication {
            background: #d1fae5;
            color: #059669;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
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
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-dot {
            position: absolute;
            left: -1.6875rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid white;
            box-shadow: 0 0 0 3px var(--primary-color);
        }

        .timeline-content {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .timeline-date {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .timeline-title {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        /* Charts */
        .chart-container {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .chart-placeholder {
            height: 200px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            position: relative;
        }

        .tab.active {
            color: var(--primary-color);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .vitals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .vitals-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
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
            <div class="header-content">
                <div class="patient-title">
                    <h1>
                        <i class="fas fa-user-injured"></i>
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </h1>
                    <div class="patient-subtitle">
                        <span class="patient-id-badge">ID: <?php echo htmlspecialchars($patient['patient_id']); ?></span>
                        <span class="patient-status status-<?php echo strtolower($patient['status'] ?? 'active'); ?>">
                            <i class="fas fa-circle" style="font-size: 8px;"></i>
                            <?php echo htmlspecialchars($patient['status'] ?? 'Active'); ?>
                        </span>
                        <span style="color: #64748b;">
                            <i class="fas fa-birthday-cake"></i>
                            <?php echo $age ?? 'Age not recorded'; ?> years
                        </span>
                        <?php if ($patient['last_login']): ?>
                            <span style="color: #10b981;">
                                <i class="fas fa-user-check"></i>
                                Last login: <?php echo date('M d, Y', strtotime($patient['last_login'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="patient_medical_records.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-file-medical"></i> View Complete EHR
                    </a>
                    <?php if ($patient['username']): ?>
                        <a href="reset_patient_password.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-key"></i> Reset Password
                        </a>
                    <?php else: ?>
                        <a href="setup_patient_login.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Setup Portal Access
                        </a>
                    <?php endif; ?>
                    <a href="new_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> New Appointment
                    </a>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_appointments'] ?? 0; ?></div>
                    <div class="stat-label">Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['doctors_seen'] ?? 0; ?></div>
                    <div class="stat-label">Doctors Seen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_prescriptions'] ?? 0; ?></div>
                    <div class="stat-label">Prescriptions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['medical_records_count'] ?? 0; ?></div>
                    <div class="stat-label">Medical Records</div>
                </div>
                <?php if ($stats['first_visit']): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo date('M Y', strtotime($stats['first_visit'])); ?></div>
                        <div class="stat-label">First Visit</div>
                    </div>
                <?php endif; ?>
                <?php if ($stats['last_visit']): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo date('M Y', strtotime($stats['last_visit'])); ?></div>
                        <div class="stat-label">Last Visit</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column: Detailed Information -->
            <div>
                <!-- Tabs Navigation -->
                <div class="tabs">
                    <button class="tab active" data-tab="overview">Overview</button>
                    <button class="tab" data-tab="medical">Medical</button>
                    <button class="tab" data-tab="appointments">Appointments</button>
                    <button class="tab" data-tab="medications">Medications</button>
                    <button class="tab" data-tab="vitals">Vitals</button>
                    <button class="tab" data-tab="timeline">Timeline</button>
                </div>

                <!-- Overview Tab -->
                <div id="overview-tab" class="tab-content active">
                    <!-- Patient Information -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-user-circle"></i>
                                Patient Information
                            </h2>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?php echo !empty($patient['date_of_birth']) ? date('F j, Y', strtotime($patient['date_of_birth'])) : 'Not recorded'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?php echo ucfirst($patient['gender'] ?? 'Not specified'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['phone'] ?? 'Not recorded'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['email'] ?? 'Not recorded'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($patient['address'] ?? 'Not recorded')); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Latest Vitals -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-heartbeat"></i>
                                Latest Vital Signs
                            </h2>
                            <?php if ($latest_vitals): ?>
                                <span class="item-details">
                                    Recorded: <?php echo date('M j, Y g:i A', strtotime($latest_vitals['recorded_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($latest_vitals): ?>
                            <div class="vitals-grid">
                                <?php if ($latest_vitals['blood_pressure']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Blood Pressure</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['blood_pressure']); ?><span class="vital-unit">mmHg</span></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($latest_vitals['heart_rate']): ?>
                                    <div class="vital-item">
                                        <div class="vital-label">Heart Rate</div>
                                        <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['heart_rate']); ?><span class="vital-unit">bpm</span></div>
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
                                        <div class="vital-status status-<?php echo strtolower($bmi_category); ?>">
                                            <?php echo $bmi_category; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-heartbeat"></i>
                                <h3 class="empty-title">No Vitals Recorded</h3>
                                <p class="empty-subtitle">Vital signs will appear here after they are recorded during appointments.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical Tab -->
                <div id="medical-tab" class="tab-content">
                    <!-- Medical History -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-file-medical"></i>
                                Medical History
                            </h2>
                        </div>
                        
                        <?php if (!empty($patient['medical_history'])): ?>
                            <div style="white-space: pre-line; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid var(--border-color);">
                                <?php echo htmlspecialchars($patient['medical_history']); ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-medical"></i>
                                <h3 class="empty-title">No Medical History Recorded</h3>
                                <p class="empty-subtitle">Medical history information will appear here when recorded by doctors.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Allergies -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-allergies"></i>
                                Allergies
                            </h2>
                        </div>
                        
                        <?php if (!empty($allergies)): ?>
                            <div class="tags-container">
                                <?php foreach ($allergies as $allergy): ?>
                                    <span class="tag tag-allergy"><?php echo htmlspecialchars(trim($allergy)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-allergies"></i>
                                <h3 class="empty-title">No Allergies Recorded</h3>
                                <p class="empty-subtitle">No known allergies documented for this patient.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Chronic Conditions -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-stethoscope"></i>
                                Chronic Conditions
                            </h2>
                        </div>
                        
                        <?php if (!empty($chronic_conditions)): ?>
                            <div class="tags-container">
                                <?php foreach ($chronic_conditions as $condition): ?>
                                    <span class="tag tag-chronic"><?php echo htmlspecialchars(trim($condition)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-stethoscope"></i>
                                <h3 class="empty-title">No Chronic Conditions</h3>
                                <p class="empty-subtitle">No chronic conditions documented for this patient.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Diagnoses -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-diagnoses"></i>
                                Recent Diagnoses
                            </h2>
                            <a href="patient_medical_records.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                View All Records
                            </a>
                        </div>
                        
                        <?php if (!empty($medical_records)): ?>
                            <div class="item-list">
                                <?php foreach ($medical_records as $record): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h4><?php echo htmlspecialchars($record['diagnosis'] ?? 'Diagnosis not specified'); ?></h4>
                                            <div class="item-details">
                                                <span>Dr. <?php echo htmlspecialchars($record['doctor_name'] ?? 'Unknown'); ?></span>
                                                <span> • </span>
                                                <span><?php echo $record['formatted_date']; ?></span>
                                            </div>
                                        </div>
                                        <div class="item-actions">
                                            <button class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" 
                                                    onclick="viewRecordDetails(<?php echo $record['id']; ?>)">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-diagnoses"></i>
                                <h3 class="empty-title">No Diagnoses Recorded</h3>
                                <p class="empty-subtitle">Diagnoses will appear here after medical consultations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Appointments Tab -->
                <div id="appointments-tab" class="tab-content">
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-calendar-alt"></i>
                                Recent Appointments
                            </h2>
                            <div class="section-actions">
                                <a href="patient_appointments.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    View All
                                </a>
                                <a href="new_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    <i class="fas fa-plus"></i> New
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($recent_appointments)): ?>
                            <div class="item-list">
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h4>Appointment #<?php echo htmlspecialchars($appointment['appointment_number']); ?></h4>
                                            <div class="item-details">
                                                <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></span>
                                                <span> • </span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
                                                <span> • </span>
                                                <span><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Unknown'); ?></span>
                                                <?php if ($appointment['doctor_specialty']): ?>
                                                    <span> • </span>
                                                    <span><?php echo htmlspecialchars($appointment['doctor_specialty']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="item-actions">
                                            <span class="vital-status status-<?php echo strtolower($appointment['status']); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <h3 class="empty-title">No Appointments Found</h3>
                                <p class="empty-subtitle">This patient has no appointment history.</p>
                                <a href="new_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-calendar-plus"></i> Schedule First Appointment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medications Tab -->
                <div id="medications-tab" class="tab-content">
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-pills"></i>
                                Active Medications
                            </h2>
                            <div class="section-actions">
                                <a href="patient_medical_records.php?id=<?php echo $patient['id']; ?>#prescriptions-tab" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    View All
                                </a>
                                <a href="new_prescription.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    <i class="fas fa-plus"></i> New
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($active_prescriptions)): ?>
                            <div class="item-list">
                                <?php foreach ($active_prescriptions as $prescription): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h4><?php echo htmlspecialchars($prescription['medication_name']); ?></h4>
                                            <div class="item-details">
                                                <span><i class="fas fa-prescription-bottle-alt"></i> <?php echo htmlspecialchars($prescription['dosage']); ?></span>
                                                <span> • </span>
                                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($prescription['frequency']); ?></span>
                                                <span> • </span>
                                                <span><i class="fas fa-calendar"></i> <?php echo $prescription['formatted_date']; ?></span>
                                                <span> • </span>
                                                <span><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($prescription['doctor_name'] ?? 'Unknown'); ?></span>
                                            </div>
                                        </div>
                                        <div class="item-actions">
                                            <span class="vital-status status-<?php echo strtolower($prescription['status']); ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-pills"></i>
                                <h3 class="empty-title">No Active Medications</h3>
                                <p class="empty-subtitle">No active prescriptions for this patient.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Vitals Tab -->
                <div id="vitals-tab" class="tab-content">
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-heartbeat"></i>
                                Vitals History
                            </h2>
                            <div class="section-actions">
                                <a href="patient_medical_records.php?id=<?php echo $patient['id']; ?>#vitals-tab" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    View Full History
                                </a>
                                <a href="record_vitals.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    <i class="fas fa-plus"></i> Record New
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($vitals_history)): ?>
                            <div class="item-list">
                                <?php foreach ($vitals_history as $index => $vital): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h4>Vital Signs Reading</h4>
                                            <div class="item-details">
                                                <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($vital['recorded_at'])); ?></span>
                                                <span> • </span>
                                                <span>BP: <?php echo htmlspecialchars($vital['blood_pressure'] ?? '--'); ?></span>
                                                <span> • </span>
                                                <span>HR: <?php echo htmlspecialchars($vital['heart_rate'] ?? '--'); ?> bpm</span>
                                                <span> • </span>
                                                <span>Temp: <?php echo htmlspecialchars($vital['temperature'] ?? '--'); ?>°F</span>
                                                <?php if ($vital['oxygen_saturation']): ?>
                                                    <span> • </span>
                                                    <span>SpO2: <?php echo htmlspecialchars($vital['oxygen_saturation']); ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($index === 0): ?>
                                                <span class="vital-status status-normal">
                                                    <i class="fas fa-star"></i> Latest
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-heartbeat"></i>
                                <h3 class="empty-title">No Vitals History</h3>
                                <p class="empty-subtitle">No vital signs recorded for this patient.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Timeline Tab -->
                <div id="timeline-tab" class="tab-content">
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-history"></i>
                                Health Timeline
                            </h2>
                        </div>
                        
                        <?php 
                        // Fetch timeline events from various tables
                        $timeline_events = [];
                        
                        // Get appointments for timeline
                        $timeline_appointments = $pdo->prepare("
                            SELECT appointment_date as date, 
                                   CONCAT('Appointment with Dr. ', doctor_name) as title,
                                   'appointment' as type,
                                   CONCAT('Status: ', status) as description
                            FROM (
                                SELECT a.appointment_date,
                                       CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                                       a.status
                                FROM appointments a
                                LEFT JOIN users d ON a.doctor_id = d.id
                                WHERE a.patient_id = ?
                                ORDER BY a.appointment_date DESC
                                LIMIT 10
                            ) as appointments
                        ");
                        $timeline_appointments->execute([$patient['id']]);
                        $appointments_timeline = $timeline_appointments->fetchAll();
                        
                        // Get prescriptions for timeline
                        $timeline_prescriptions = $pdo->prepare("
                            SELECT prescription_date as date,
                                   CONCAT('Prescription: ', medication_name) as title,
                                   'prescription' as type,
                                   CONCAT('Dosage: ', dosage, ', Frequency: ', frequency) as description
                            FROM prescriptions
                            WHERE patient_id = ?
                            ORDER BY prescription_date DESC
                            LIMIT 10
                        ");
                        $timeline_prescriptions->execute([$patient['id']]);
                        $prescriptions_timeline = $timeline_prescriptions->fetchAll();
                        
                        // Get vitals for timeline
                        $timeline_vitals = $pdo->prepare("
                            SELECT recorded_at as date,
                                   'Vital Signs Recorded' as title,
                                   'vitals' as type,
                                   CONCAT('BP: ', blood_pressure, ', HR: ', heart_rate, ', Temp: ', temperature) as description
                            FROM recent_patient_vitals
                            WHERE patient_id = ?
                            ORDER BY recorded_at DESC
                            LIMIT 10
                        ");
                        $timeline_vitals->execute([$patient['id']]);
                        $vitals_timeline = $timeline_vitals->fetchAll();
                        
                        // Merge all events
                        $timeline_events = array_merge($appointments_timeline, $prescriptions_timeline, $vitals_timeline);
                        
                        // Sort by date
                        usort($timeline_events, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                        ?>
                        
                        <?php if (!empty($timeline_events)): ?>
                            <div class="timeline">
                                <?php foreach ($timeline_events as $event): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-content">
                                            <div class="timeline-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('F j, Y', strtotime($event['date'])); ?>
                                            </div>
                                            <div class="timeline-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                            <?php if ($event['description']): ?>
                                                <div style="font-size: 0.875rem; color: #64748b;">
                                                    <?php echo htmlspecialchars($event['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3 class="empty-title">No Timeline Events</h3>
                                <p class="empty-subtitle">Timeline will populate as health events occur.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Information -->
            <div>
                <!-- Emergency Contact -->
                <?php if ($patient['emergency_contact_name'] || $patient['emergency_contact_phone']): ?>
                    <div class="summary-card" style="border-left: 4px solid #f59e0b;">
                        <div class="summary-header">
                            <div class="summary-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="summary-title">Emergency Contact</div>
                        </div>
                        <div class="info-grid">
                            <?php if ($patient['emergency_contact_name']): ?>
                                <div class="info-item">
                                    <div class="info-label">Contact Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($patient['relationship']): ?>
                                <div class="info-item">
                                    <div class="info-label">Relationship</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient['relationship']); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($patient['emergency_contact_phone']): ?>
                                <div class="info-item">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($patient['emergency_email']): ?>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient['emergency_email']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Insurance Information -->
                <?php if ($patient['insurance_provider'] || $patient['insurance_id']): ?>
                    <div class="summary-card" style="border-left: 4px solid #10b981;">
                        <div class="summary-header">
                            <div class="summary-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="summary-title">Insurance Information</div>
                        </div>
                        <div class="info-grid">
                            <?php if ($patient['insurance_provider']): ?>
                                <div class="info-item">
                                    <div class="info-label">Provider</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient['insurance_provider']); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($patient['insurance_id']): ?>
                                <div class="info-item">
                                    <div class="info-label">Policy Number</div>
                                    <div class="info-value" style="font-family: monospace;"><?php echo htmlspecialchars($patient['insurance_id']); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($patient['primary_physician']): ?>
                                <div class="info-item">
                                    <div class="info-label">Primary Physician</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient['primary_physician']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Lifestyle Factors -->
                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-icon">
                            <i class="fas fa-running"></i>
                        </div>
                        <div class="summary-title">Lifestyle Factors</div>
                    </div>
                    <div class="info-grid">
                        <?php if ($patient['smoking_status']): ?>
                            <div class="info-item">
                                <div class="info-label">Smoking Status</div>
                                <div class="info-value"><?php echo ucfirst($patient['smoking_status']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['alcohol_consumption']): ?>
                            <div class="info-item">
                                <div class="info-label">Alcohol Consumption</div>
                                <div class="info-value"><?php echo ucfirst($patient['alcohol_consumption']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['exercise_frequency']): ?>
                            <div class="info-item">
                                <div class="info-label">Exercise Frequency</div>
                                <div class="info-value">
                                    <?php 
                                    $exercise_map = [
                                        'none' => 'None',
                                        'rarely' => 'Rarely',
                                        '1-2' => '1-2 times/week',
                                        '3+' => '3+ times/week'
                                    ];
                                    echo $exercise_map[$patient['exercise_frequency']] ?? ucfirst($patient['exercise_frequency']);
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['dietary_habits']): ?>
                            <div class="info-item">
                                <div class="info-label">Dietary Habits</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['dietary_habits']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-icon">
                            <i class="fas fa-notes-medical"></i>
                        </div>
                        <div class="summary-title">Medical Details</div>
                    </div>
                    <div class="info-grid">
                        <?php if ($patient['blood_type']): ?>
                            <div class="info-item">
                                <div class="info-label">Blood Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['blood_type']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['marital_status']): ?>
                            <div class="info-item">
                                <div class="info-label">Marital Status</div>
                                <div class="info-value"><?php echo ucfirst($patient['marital_status']); ?></div>
                            </div>
                        <?php endif; ?>
                        <!-- <?php if ($patient['occupation']): ?>
                            <div class="info-item">
                                <div class="info-label">Occupation</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['occupation']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['preferred_language']): ?>
                            <div class="info-item">
                                <div class="info-label">Preferred Language</div>
                                <div class="info-value"><?php echo htmlspecialchars($patient['preferred_language']); ?></div>
                            </div>
                        <?php endif; ?> -->
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-icon">
                            <i class="fas fa-link"></i>
                        </div>
                        <div class="summary-title">Quick Actions</div>
                    </div>
                    <div class="item-list">
                        <a href="new_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="list-item" style="text-decoration: none;">
                            <div class="item-info">
                                <h4 style="color: var(--primary-color);">
                                    <i class="fas fa-calendar-plus"></i> New Appointment
                                </h4>
                            </div>
                        </a>
                        <a href="new_prescription.php?patient_id=<?php echo $patient['id']; ?>" class="list-item" style="text-decoration: none;">
                            <div class="item-info">
                                <h4 style="color: var(--primary-color);">
                                    <i class="fas fa-prescription-bottle-alt"></i> New Prescription
                                </h4>
                            </div>
                        </a>
                        <a href="record_vitals.php?patient_id=<?php echo $patient['id']; ?>" class="list-item" style="text-decoration: none;">
                            <div class="item-info">
                                <h4 style="color: var(--primary-color);">
                                    <i class="fas fa-heartbeat"></i> Record Vitals
                                </h4>
                            </div>
                        </a>
                        <a href="add_clinical_note.php?patient_id=<?php echo $patient['id']; ?>" class="list-item" style="text-decoration: none;">
                            <div class="item-info">
                                <h4 style="color: var(--primary-color);">
                                    <i class="fas fa-sticky-note"></i> Add Clinical Note
                                </h4>
                            </div>
                        </a>
                        <a href="patient_medical_records.php?id=<?php echo $patient['id']; ?>" class="list-item" style="text-decoration: none;">
                            <div class="item-info">
                                <h4 style="color: var(--primary-color);">
                                    <i class="fas fa-file-medical"></i> Complete EHR
                                </h4>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Set first tab as active
            if (tabs.length > 0) {
                tabs[0].classList.add('active');
            }
            if (tabContents.length > 0) {
                tabContents[0].classList.add('active');
            }
            
            // Add click event listeners to tabs
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const targetTab = document.getElementById(tabName + '-tab');
                    if (targetTab) {
                        targetTab.classList.add('active');
                    }
                });
            });
            
            // Restore active tab from URL hash
            if (window.location.hash) {
                const hash = window.location.hash.substring(1);
                const tabToActivate = document.querySelector(`.tab[data-tab="${hash}"]`);
                if (tabToActivate) {
                    tabToActivate.click();
                }
            }
            
            // Update URL hash when tab changes
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    window.location.hash = tabName;
                });
            });
            
            // Print functionality
            document.querySelector('.print-btn')?.addEventListener('click', function() {
                window.print();
            });
        });

        // View record details modal (placeholder function)
        function viewRecordDetails(recordId) {
            // This would typically open a modal or redirect to record details page
            alert('Record details would open for record ID: ' + recordId);
            // Example: window.location.href = 'view_medical_record.php?id=' + recordId;
        }

        // Export patient data
        function exportPatientData() {
            if (confirm('Export patient data? This will generate a PDF summary.')) {
                // Simulate export - in real implementation, this would call a server-side script
                alert('Export initiated. The file will download shortly.');
                // Example: window.location.href = 'export_patient.php?id=<?php echo $patient['id']; ?>';
            }
        }

        // Quick actions
        function scheduleFollowup() {
            window.location.href = 'new_appointment.php?patient_id=<?php echo $patient['id']; ?>&type=followup';
        }

        function sendReminder() {
            if (confirm('Send appointment reminder to patient?')) {
                alert('Reminder sent to patient.');
                // Example: fetch('send_reminder.php?patient_id=<?php echo $patient['id']; ?>');
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + E to edit
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'edit_patient.php?id=<?php echo $patient['id']; ?>';
            }
            
            // Ctrl/Cmd + A for new appointment
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                e.preventDefault();
                window.location.href = 'new_appointment.php?patient_id=<?php echo $patient['id']; ?>';
            }
            
            // Ctrl/Cmd + P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            // Escape to go back
            if (e.key === 'Escape') {
                window.history.back();
            }
        });

        // Responsive adjustments
        function handleResize() {
            const width = window.innerWidth;
            const actionButtons = document.querySelectorAll('.action-buttons .btn span');
            
            if (width < 768) {
                actionButtons.forEach(btn => {
                    if (!btn.querySelector('i')) {
                        btn.style.display = 'none';
                    }
                });
            } else {
                actionButtons.forEach(btn => {
                    btn.style.display = 'inline';
                });
            }
        }

        window.addEventListener('resize', handleResize);
        handleResize(); // Initial call
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
      
      </div>
      <ul style="list-style: none; padding: 0; margin: 0;">
         <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
    
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="section-label">Main Menu</div>
      <ul class="sidebar-menu">
        <li>
          <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
            <!-- <span class="menu-badge">New</span> -->
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Employee Administartion</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-users"></i>
            <span>Staff Management</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="manage_users.php"><i class="fas fa-user-md"></i>Employee List</a></li>
            <li><a href="departments.php"><i class="fas fa-building"></i>Departments</a></li>
            <li><a href="doctors_schedule.php"><i class="fas fa-calendar-alt"></i>Doctor Schedule</a></li>
            <li><a href="services.php"><i class="fas fa-concierge-bell"></i>Services</a></li>
          </ul>
        </li>
    </div>

    <div class="nav-section">
      <div class="section-label">Patient Care</div>
      <ul class="sidebar-menu"> 
        <li>
          <a href="add_new_patients.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>Patient Management</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="appointment_scheduling.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="calendar_view.php"><i class="fas fa-calendar-week"></i>Calendar View</a></li>
            <li><a href="appointment_requests.php"><i class="fas fa-clock"></i>Requests & Approvals</a></li>
          </ul>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-file-medical"></i>
            <span>EHR System</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="ehr_dashboard.php"><i class="fas fa-tachometer-alt"></i>EHR Dashboard</a></li>
            <li><a href="patient_search.php"><i class="fas fa-search"></i>Patient Search</a></li>
            <li><a href="clinical_notes.php"><i class="fas fa-file-medical-alt"></i>Clinical Notes</a></li>
            <li><a href="lab_orders.php"><i class="fas fa-vial"></i>Lab Orders</a></li>
            <li><a href="prescriptions.php"><i class="fas fa-prescription"></i>Medication Orders</a></li>
            <li><a href="medical_history.php"><i class="fas fa-history"></i>Medical History</a></li>
            <li><a href="vitals_history.php"><i class="fas fa-heartbeat"></i>Vitals History</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Clinical Modules</div>
      <ul class="sidebar-menu">
        <li>
          <a href="pharmacy.php" class="menu-item">
            <i class="fas fa-capsules"></i>
            <span>Pharmacy</span>
          </a>
        </li>
        
        <li>
          <a href="lab_module.php" class="menu-item">
            <i class="fas fa-vials"></i>
            <span>Laboratory</span>
          </a>
        </li>

        <li>
          <a href="billing_dashboard.php" class="menu-item">
            <i class="fas fa-money-bill-wave"></i>
            <span>Billing & Finance</span>
          </a>
        </li>
        
        <li>
          <a href="wards.php" class="menu-item">
            <i class="fas fa-bed"></i>
            <span>Ward & Bed Management</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-warehouse"></i>
            <span>Inventory & Supply</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="supplier.php"><i class="fas fa-truck"></i>Manage Suppliers</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-boxes"></i>Manage Inventory</a></li>
          </ul>
        </li>

        <li>
          <a href="reports.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-chart-line"></i>
            <span>Reports & Analytics</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="purchase_report.php"><i class="fas fa-shopping-cart"></i>PURCHASE REPORT</a></li>
            <li><a href="bills.php"><i class="fas fa-receipt"></i>SALES REPORT</a></li>
            <li><a href="inventory.php"><i class="fas fa-box-open"></i>STOCK REPORT</a></li>
            <li><a href="supplier_report.php"><i class="fas fa-truck-loading"></i>SUPPLIERS REPORT</a></li>
            <li><a href="return_product_list.php"><i class="fas fa-undo-alt"></i>RETURNS PRODUCTS REPORT</a></li>
            <li><a href="expiry_report.php"><i class="fas fa-calendar-times"></i>EXPIRED PRODUCTS REPORT</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">System</div>
      <ul class="sidebar-menu">
        <li>
          <a href="settings.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>
        <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="user-info">
        <span class="user-name">Administrator</span>
        <span class="user-role">Super Admin</span>
      </div>
    </div>
  </div>
</aside>

<style>
/* Sidebar Base Styles */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 290px;
  height: 100vh;
  background: linear-gradient(180deg, #0c4da2 0%, #07377c 100%);
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 800;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
}

/* Compact Sidebar */
.sidebar.compact {
  width: 90px;
}

.sidebar.compact .logo-text,
.sidebar.compact .section-label,
.sidebar.compact .menu-item span,
.sidebar.compact .user-info,
.sidebar.compact .menu-badge,
.sidebar.compact .dropdown-arrow {
  display: none !important;
}

.sidebar.compact .submenu {
  display: none !important;
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Sidebar Header */
.sidebar-header {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.logo-icon {
  width: 40px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.logo-text h2 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 900;
  color: white;
  line-height: 1.5;
  letter-spacing: 0.8px;
}

.logo-text small {
  display: block;
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
  margin-top: 0.8rem;
  letter-spacing: 0.3px;
}

.sidebar-toggle {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.sidebar-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: rotate(90deg);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-section {
  margin-bottom: 1rem;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1.5rem 0.25rem;
  margin-bottom: 0.25rem;
}

/* Menu List */
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
  position: relative;
}

/* Menu Item Styles */
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.9rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  position: relative;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.12);
  border-left-color: #4dabf7;
  color: white;
  padding-left: 1.75rem;
}

.menu-item.active {
  background: rgba(255, 255, 255, 0.15);
  border-left-color: #51cf66;
  color: white;
  font-weight: 600;
}

.menu-item i:first-child {
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-badge {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
}

/* Submenu Styles */
.has-submenu > .menu-item {
  position: relative;
}

.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.7);
}

.has-submenu.active .dropdown-arrow {
  transform: rotate(180deg);
  color: white;
}

.submenu {
  list-style: none;
  padding: 0;
  margin: 0;
  background: rgba(0, 0, 0, 0.2);
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.has-submenu.active .submenu {
  max-height: 500px;
}

.submenu li a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem 0.75rem 3.5rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  border-left: 3px solid transparent;
}

.submenu li a:hover {
  background: rgba(255, 255, 255, 0.08);
  border-left-color: #ffd43b;
  color: white;
  padding-left: 4rem;
}

.submenu li a i {
  font-size: 0.9rem;
  width: 16px;
  text-align: center;
}

/* Logout Item */
.logout-item {
  color: #ff6b6b !important;
}

.logout-item:hover {
  background: rgba(255, 107, 107, 0.15) !important;
  border-left-color: #ff6b6b !important;
  color: #ff6b6b !important;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: white;
  margin-bottom: 0.1rem;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Main Content Adjustment */
.main-content {
  margin-left: 300px;
  min-height: 100vh;
  padding: 2rem;
  background-color: #f8f9fa;
  transition: margin-left 0.3s ease;
}

.main-content.expanded {
  margin-left: 80px;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .sidebar {
    width: 80px;
    transform: translateX(-100%);
  }
  
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
  
  .mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
  
  .mobile-overlay.active {
    display: block;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 1rem;
  }
}

/* Animation for menu items */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.sidebar-menu li {
  animation: slideIn 0.3s ease forwards;
}

.sidebar-menu li:nth-child(1) { animation-delay: 0.05s; }
.sidebar-menu li:nth-child(2) { animation-delay: 0.1s; }
.sidebar-menu li:nth-child(3) { animation-delay: 0.15s; }
.sidebar-menu li:nth-child(4) { animation-delay: 0.2s; }
.sidebar-menu li:nth-child(5) { animation-delay: 0.25s; }

/* Enhanced hover effects */
.menu-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 0;
  background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);
  transition: width 0.3s ease;
}

.menu-item:hover::before {
  width: 100%;
}

/* Status indicators */
.menu-item.active::after {
  content: '';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  background: #51cf66;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const currentPath = window.location.pathname.split('/').pop();
    const mobileOverlay = document.createElement('div');
    
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);

    // Toggle sidebar compact mode
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('compact');
        mainContent.classList.toggle('expanded');
        
        // Update toggle icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('compact')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-bars';
        }
    });

    // Mobile overlay click to close sidebar
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        this.classList.remove('active');
    });

    // Handle dynamic 'active' class on page load
    document.querySelectorAll('.menu-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href !== '#' && currentPath === href.split('/').pop()) {
            // Remove active class from all items
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.has-submenu').forEach(i => i.classList.remove('active'));
            
            // Add active class to current item
            item.classList.add('active');
            
            // If it's in a submenu, activate parent
            const parentSubmenu = item.closest('.has-submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('active');
            }
        }
    });

    // Handle Submenu Toggle
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.closest('.has-submenu');
            
            // Close other submenus
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            parentLi.classList.toggle('active');
        });
    });

    // Close submenus when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && !sidebar.contains(e.target)) {
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                item.classList.remove('active');
            });
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
        }
    });

    // Add smooth loading animation
    setTimeout(() => {
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 100);
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

// Initialize sidebar with fade-in effect
window.addEventListener('load', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        sidebar.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 300);
});
</script>