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

// Get patient ID from URL or session
$patient_id_param = isset($_GET['id']) ? $_GET['id'] : null;
$user_role = $_SESSION['role'] ?? null;

// Determine how to fetch patient data
if ($patient_id_param) {
    // Admin/Staff viewing specific patient profile
    $patient_stmt = $pdo->prepare("
        SELECT * FROM patients 
        WHERE id = ? OR patient_id = ?
    ");
    $patient_stmt->execute([$patient_id_param, $patient_id_param]);
    $patient = $patient_stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: patients.php");
        exit;
    }
} elseif ($user_role === 'Patient') {
    // Patient viewing their own profile
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Try to match patient by user ID or contact info
    $patient_stmt = $pdo->prepare("
        SELECT * FROM patients 
        WHERE id = ? 
        OR phone = (SELECT phone FROM users WHERE id = ?)
        OR email = (SELECT email FROM users WHERE id = ?)
        LIMIT 1
    ");
    $patient_stmt->execute([$user_id, $user_id, $user_id]);
    $patient = $patient_stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient profile not found. Please contact administration.";
        header("Location: dashboard.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Access denied. Please specify a patient.";
    header("Location: patients.php");
    exit;
}

// Calculate age from date of birth
$age = null;
if (!empty($patient['date_of_birth'])) {
    $dob = new DateTime($patient['date_of_birth']);
    $today = new DateTime();
    $age = $dob->diff($today)->y;
}

// Update age in database if different from calculated
if ($age !== null && $age != $patient['age']) {
    $update_stmt = $pdo->prepare("UPDATE patients SET age = ? WHERE id = ?");
    $update_stmt->execute([$age, $patient['id']]);
    $patient['age'] = $age;
}

// Get additional patient statistics (if tables exist)
try {
    // Count appointments
    $appointments_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_appointments,
               MAX(appointment_date) as last_appointment
        FROM appointments 
        WHERE patient_id = ?
    ");
    $appointments_stmt->execute([$patient['id']]);
    $appointment_stats = $appointments_stmt->fetch();
} catch (Exception $e) {
    $appointment_stats = ['total_appointments' => 0, 'last_appointment' => null];
}

try {
    // Count prescriptions
    $prescriptions_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_prescriptions
        FROM prescriptions 
        WHERE patient_id = ?
    ");
    $prescriptions_stmt->execute([$patient['id']]);
    $prescription_stats = $prescriptions_stmt->fetch();
} catch (Exception $e) {
    $prescription_stats = ['total_prescriptions' => 0];
}

try {
    // Get latest vital signs (if table exists)
    $vitals_stmt = $pdo->prepare("
        SELECT * FROM patient_vitals 
        WHERE patient_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $vitals_stmt->execute([$patient['id']]);
    $latest_vitals = $vitals_stmt->fetch();
} catch (Exception $e) {
    $latest_vitals = null;
}

// Calculate BMI if height and weight are available
$bmi = null;
$bmi_category = null;
if (!empty($patient['height']) && !empty($patient['weight']) && $patient['height'] > 0) {
    $height_m = $patient['height'] / 100;
    $bmi = $patient['weight'] / ($height_m * $height_m);
    
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
    <title><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> - Patient Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primarycolor: #1e40af;
            --primarydark: #1e3a8a;
            --primarylight: #3b82f6;
            --secondarycolor: #0ea5e9;
            --accentcolor: #60a5fa;
            --bgcolor: #f0f9ff;
            --textcolor: #1e3a8a;
            --card-bg: #ffffff;
            --border-color: #dbeafe;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
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
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .page-title-section h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--primarydark);
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .patient-id-badge {
            background: var(--primarycolor);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
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

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fef3c7;
            color: #92400e;
        }

        .status-deceased {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons */
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
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--primarycolor);
            border: 2px solid var(--primarycolor);
        }

        .btn-secondary:hover {
            background: var(--primarycolor);
            color: white;
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

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
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
            box-shadow: 0 4px 20px rgba(30, 64, 175, 0.08);
            border: 1px solid var(--border-color);
        }

        .summary-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .patient-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primarycolor), var(--secondarycolor));
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            font-weight: 600;
        }

        .patient-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--textcolor);
            margin-bottom: 0.25rem;
        }

        .patient-age-gender {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: #f0f9ff;
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Quick Info */
        .quick-info {
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-label {
            color: #64748b;
            font-size: 0.875rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--textcolor);
            text-align: right;
        }

        /* Emergency Contact */
        .emergency-contact {
            background: #fef3c7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            border-left: 4px solid #f59e0b;
        }

        .emergency-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .emergency-details p {
            font-size: 0.875rem;
            color: #92400e;
            margin-bottom: 0.25rem;
        }

        /* Details Section */
        .details-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .section-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(30, 64, 175, 0.08);
            border: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primarydark);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: var(--primarylight);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Health Metrics */
        .health-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .metric-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .metric-card:hover {
            background: #f0f9ff;
            border-color: var(--primarycolor);
        }

        .metric-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 0.25rem;
        }

        .metric-unit {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-left: 2px;
        }

        .metric-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
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

        /* Two Column Grid for Info */
        .two-column-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .two-column-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-group {
            margin-bottom: 1rem;
        }

        .info-group-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primarycolor);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-group-content {
            font-size: 0.875rem;
            color: var(--textcolor);
            line-height: 1.6;
        }

        .info-group-content p {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-group-content i {
            color: var(--primarycolor);
            width: 16px;
        }

        /* Tags for conditions, allergies, etc */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .tag {
            background: #dbeafe;
            color: var(--primarycolor);
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

        /* Lifestyle Factors */
        .lifestyle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .lifestyle-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .lifestyle-icon {
            font-size: 1.5rem;
            color: var(--primarycolor);
            margin-bottom: 0.5rem;
        }

        .lifestyle-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .lifestyle-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--textcolor);
        }

        /* Insurance Info */
        .insurance-card {
            background: linear-gradient(135deg, var(--primarycolor), var(--secondarycolor));
            color: white;
            border-radius: 8px;
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .insurance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .insurance-title {
            font-size: 1rem;
            font-weight: 600;
        }

        .insurance-id {
            font-family: monospace;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 1px;
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
            margin-bottom: 1.5rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: flex-start;
            }
        }

        @media (max-width: 768px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .health-metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .lifestyle-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .health-metrics-grid,
            .lifestyle-grid {
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

        /* Print Styles */
        @media print {
            .action-buttons,
            .btn {
                display: none;
            }
            
            .main-content {
                margin: 0;
                padding: 0;
            }
            
            .section-card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
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
            <div class="page-title-section">
                <h1>Patient Profile</h1>
                <div class="page-subtitle">
                    <span class="patient-id-badge"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                    <span class="patient-status status-<?php echo strtolower($patient['status'] ?? 'active'); ?>">
                        <i class="fas fa-circle" style="font-size: 8px;"></i>
                        <?php echo htmlspecialchars($patient['status'] ?? 'Active'); ?>
                    </span>
                </div>
            </div>
            <div class="action-buttons">
                <?php if ($user_role !== 'Patient'): ?>
                    <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="new_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-calendar-plus"></i> New Appointment
                    </a>
                <?php endif; ?>
                <a href="patient_medical_records.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-file-medical"></i> Medical Records
                </a>
               
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column: Patient Summary -->
            <div class="summary-card">
                <div class="summary-header">
                    <div class="patient-avatar">
                        <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
                    </div>
                    <h2 class="patient-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                    <div class="patient-age-gender">
                        <?php echo htmlspecialchars($patient['age']); ?> years • <?php echo htmlspecialchars($patient['gender']); ?>
                    </div>
                    <?php if ($patient['marital_status']): ?>
                        <div style="color: #64748b; font-size: 0.875rem;">
                            <i class="fas fa-heart"></i> <?php echo ucfirst($patient['marital_status']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Summary Statistics -->
                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $appointment_stats['total_appointments'] ?? 0; ?></div>
                        <div class="stat-label">Appointments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $prescription_stats['total_prescriptions'] ?? 0; ?></div>
                        <div class="stat-label">Prescriptions</div>
                    </div>
                    <?php if ($appointment_stats['last_appointment']): ?>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php echo date('M Y', strtotime($appointment_stats['last_appointment'])); ?>
                            </div>
                            <div class="stat-label">Last Visit</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($patient['last_visit']): ?>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php echo date('M Y', strtotime($patient['last_visit'])); ?>
                            </div>
                            <div class="stat-label">Last Record</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Contact Information -->
                <div class="quick-info">
                    <h3 style="font-size: 1rem; color: var(--primarydark); margin-bottom: 1rem;">Contact Information</h3>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
                    </div>
                    <?php if ($patient['email']): ?>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['email']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($patient['preferred_contact']): ?>
                        <div class="info-item">
                            <span class="info-label">Preferred Contact</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['preferred_contact']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($patient['address']): ?>
                        <div class="info-item">
                            <span class="info-label">Address</span>
                            <span class="info-value" style="text-align: left;"><?php echo nl2br(htmlspecialchars($patient['address'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Emergency Contact -->
                <?php if ($patient['emergency_contact_name'] || $patient['emergency_contact_phone']): ?>
                    <div class="emergency-contact">
                        <div class="emergency-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Emergency Contact
                        </div>
                        <div class="emergency-details">
                            <?php if ($patient['emergency_contact_name']): ?>
                                <p><strong><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></strong></p>
                            <?php endif; ?>
                            <?php if ($patient['relationship']): ?>
                                <p><?php echo htmlspecialchars($patient['relationship']); ?></p>
                            <?php endif; ?>
                            <?php if ($patient['emergency_contact_phone']): ?>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></p>
                            <?php endif; ?>
                            <?php if ($patient['emergency_email']): ?>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['emergency_email']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Detailed Information -->
            <div class="details-section">
                <!-- Health Metrics Section -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h2>Health Metrics</h2>
                    </div>
                    <div class="health-metrics-grid">
                        <?php if ($patient['height']): ?>
                            <div class="metric-card">
                                <div class="metric-label">Height</div>
                                <div class="metric-value"><?php echo htmlspecialchars($patient['height']); ?><span class="metric-unit">cm</span></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['weight']): ?>
                            <div class="metric-card">
                                <div class="metric-label">Weight</div>
                                <div class="metric-value"><?php echo htmlspecialchars($patient['weight']); ?><span class="metric-unit">kg</span></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($bmi): ?>
                            <div class="metric-card">
                                <div class="metric-label">Body Mass Index</div>
                                <div class="metric-value"><?php echo number_format($bmi, 1); ?></div>
                                <div class="metric-status status-<?php echo strtolower($bmi_category); ?>">
                                    <?php echo $bmi_category; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['blood_type']): ?>
                            <div class="metric-card">
                                <div class="metric-label">Blood Type</div>
                                <div class="metric-value"><?php echo htmlspecialchars($patient['blood_type']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>

                <!-- Medical Information Section -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-file-medical"></i>
                        </div>
                        <h2>Medical Information</h2>
                    </div>
                    <div class="two-column-grid">
                        <?php if ($patient['medical_history']): ?>
                            <div class="info-group">
                                <div class="info-group-title">Medical History</div>
                                <div class="info-group-content">
                                    <?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['chronic_conditions']): ?>
                            <div class="info-group">
                                <div class="info-group-title">Chronic Conditions</div>
                                <div class="info-group-content">
                                    <?php 
                                    $conditions = explode(',', $patient['chronic_conditions']);
                                    foreach ($conditions as $condition):
                                        $condition = trim($condition);
                                        if (!empty($condition)):
                                    ?>
                                        <span class="tag tag-chronic"><?php echo htmlspecialchars($condition); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['past_surgeries']): ?>
                            <div class="info-group">
                                <div class="info-group-title">Past Surgeries</div>
                                <div class="info-group-content">
                                    <?php echo nl2br(htmlspecialchars($patient['past_surgeries'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['allergies']): ?>
                            <div class="info-group">
                                <div class="info-group-title">Allergies</div>
                                <div class="info-group-content">
                                    <?php 
                                    $allergies = explode(',', $patient['allergies']);
                                    foreach ($allergies as $allergy):
                                        $allergy = trim($allergy);
                                        if (!empty($allergy)):
                                    ?>
                                        <span class="tag tag-allergy"><?php echo htmlspecialchars($allergy); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['current_medications']): ?>
                            <div class="info-group">
                                <div class="info-group-title">Current Medications</div>
                                <div class="info-group-content">
                                    <?php echo nl2br(htmlspecialchars($patient['current_medications'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['family_history']): ?>
                            <div class="info-group">
                                <div class="info-group-title">Family Medical History</div>
                                <div class="info-group-content">
                                    <?php echo nl2br(htmlspecialchars($patient['family_history'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lifestyle Section -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-running"></i>
                        </div>
                        <h2>Lifestyle Factors</h2>
                    </div>
                    <div class="lifestyle-grid">
                        <?php if ($patient['smoking_status']): ?>
                            <div class="lifestyle-item">
                                <div class="lifestyle-icon">
                                    <i class="fas fa-smoking"></i>
                                </div>
                                <div class="lifestyle-label">Smoking</div>
                                <div class="lifestyle-value"><?php echo ucfirst($patient['smoking_status']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['alcohol_consumption']): ?>
                            <div class="lifestyle-item">
                                <div class="lifestyle-icon">
                                    <i class="fas fa-wine-glass-alt"></i>
                                </div>
                                <div class="lifestyle-label">Alcohol</div>
                                <div class="lifestyle-value"><?php echo ucfirst($patient['alcohol_consumption']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['exercise_frequency']): ?>
                            <div class="lifestyle-item">
                                <div class="lifestyle-icon">
                                    <i class="fas fa-dumbbell"></i>
                                </div>
                                <div class="lifestyle-label">Exercise</div>
                                <div class="lifestyle-value">
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
                            <div class="lifestyle-item">
                                <div class="lifestyle-icon">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div class="lifestyle-label">Diet</div>
                                <div class="lifestyle-value"><?php echo htmlspecialchars($patient['dietary_habits']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Insurance Information -->
                <?php if ($patient['insurance_provider'] || $patient['insurance_id']): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h2>Insurance Information</h2>
                        </div>
                        <div class="insurance-card">
                            <div class="insurance-header">
                                <div class="insurance-title">
                                    <?php echo $patient['insurance_provider'] ? htmlspecialchars($patient['insurance_provider']) : 'Insurance Provider'; ?>
                                </div>
                                <?php if ($patient['insurance_id']): ?>
                                    <div class="insurance-id"><?php echo htmlspecialchars($patient['insurance_id']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($patient['primary_physician']): ?>
                                <div style="margin-top: 1rem; font-size: 0.875rem; opacity: 0.9;">
                                    Primary Physician: <?php echo htmlspecialchars($patient['primary_physician']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Additional Notes -->
                <?php if ($patient['additional_family_notes']): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-sticky-note"></i>
                            </div>
                            <h2>Additional Notes</h2>
                        </div>
                        <div class="info-group-content">
                            <?php echo nl2br(htmlspecialchars($patient['additional_family_notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Print functionality
        function printProfile() {
            window.print();
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Add any interactive functionality here
            console.log('Patient profile loaded for: <?php echo htmlspecialchars($patient["first_name"] . " " . $patient["last_name"]); ?>');
        });

        // Smooth scrolling for print
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('printing');
        });

        window.addEventListener('afterprint', function() {
            document.body.classList.remove('printing');
        });
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>