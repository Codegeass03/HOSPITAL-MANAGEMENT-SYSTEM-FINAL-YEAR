<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get patient ID from URL
$patient_id = $_GET['id'] ?? 0;
if (!$patient_id) {
    header("Location: pending_payments.php");
    exit();
}

// Fetch patient basic information
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        a.county,
        a.sub_county,
        a.ward,
        a.village,
        a.postal_address,
        a.postal_code,
        a.residence,
        n.name as next_of_kin_name,
        n.relationship as next_of_kin_relationship,
        n.phone as next_of_kin_phone,
        n.address as next_of_kin_address,
        i.insurance_provider,
        i.membership_number,
        i.principal_member,
        i.expiry_date,
        i.status as insurance_status,
        m.blood_group,
        m.height,
        m.weight,
        m.bmi,
        m.allergies,
        m.chronic_conditions,
        m.current_medications,
        m.notes as medical_notes
    FROM patients p
    LEFT JOIN address a ON p.id = a.patient_id
    LEFT JOIN next_of_kin n ON p.id = n.patient_id
    LEFT JOIN insurance i ON p.id = i.patient_id
    LEFT JOIN medical_history m ON p.id = m.patient_id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found!";
    header("Location: pending_payments.php");
    exit();
}

date_default_timezone_set('Africa/Nairobi');

// Get all outstanding bills for this patient
$outstanding_bills = [];

// Hospital bills
$stmt = $pdo->prepare("
    SELECT 
        'hospital' as bill_type,
        b.id,
        b.bill_number,
        b.bill_date,
        b.total_amount,
        b.paid_amount,
        b.balance_amount,
        b.status,
        b.department,
        GROUP_CONCAT(DISTINCT CONCAT(bi.item_description, ' (', bi.item_type, ')') ORDER BY bi.id SEPARATOR '; ') as items
    FROM billing b
    LEFT JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.patient_id = ?
    AND b.balance_amount > 0
    GROUP BY b.id
    ORDER BY b.bill_date DESC
");
$stmt->execute([$patient_id]);
$hospital_bills = $stmt->fetchAll();
$outstanding_bills = array_merge($outstanding_bills, $hospital_bills);

// Lab orders
$stmt = $pdo->prepare("
    SELECT 
        'lab' as bill_type,
        lo.id,
        lo.order_number as bill_number,
        lo.order_date as bill_date,
        lo.total_amount,
        lo.paid_amount,
        (lo.total_amount - lo.paid_amount) as balance_amount,
        lo.payment_status as status,
        'laboratory' as department,
        GROUP_CONCAT(DISTINCT loi.test_name ORDER BY loi.id SEPARATOR '; ') as items
    FROM lab_orders lo
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE lo.patient_id = ?
    AND (lo.total_amount - lo.paid_amount) > 0
    AND lo.payment_status IN ('unpaid', 'partial')
    GROUP BY lo.id
    ORDER BY lo.order_date DESC
");
$stmt->execute([$patient_id]);
$lab_bills = $stmt->fetchAll();
$outstanding_bills = array_merge($outstanding_bills, $lab_bills);

// Pharmacy bills
mysqli_select_db($link, "hospital");
$pharmacy_query = "
    SELECT 
        'pharmacy' as bill_type,
        pb.id,
        pb.bill_no as bill_number,
        pb.date as bill_date,
        pb.total_amount,
        pb.paid_amount,
        pb.balance_amount,
        CASE 
            WHEN pb.balance_amount > 0 AND pb.paid_amount > 0 THEN 'partial'
            WHEN pb.balance_amount > 0 THEN 'pending'
            ELSE 'paid'
        END as status,
        'pharmacy' as department,
        GROUP_CONCAT(DISTINCT pbd.product_name ORDER BY pbd.id SEPARATOR '; ') as items
    FROM pharmacy_billing pb
    LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
    WHERE pb.patient_id = " . intval($patient_id) . "
    AND pb.balance_amount > 0
    GROUP BY pb.id
    ORDER BY pb.date DESC
";
$pharmacy_result = mysqli_query($link, $pharmacy_query);
if ($pharmacy_result) {
    while ($row = mysqli_fetch_assoc($pharmacy_result)) {
        $outstanding_bills[] = $row;
    }
}

// Get payment history
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        pa.service_type,
        pa.reference_id,
        pa.allocated_amount
    FROM payments p
    LEFT JOIN payment_allocations pa ON p.id = pa.payment_id
    WHERE p.patient_id = ?
    AND p.status = 'completed'
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute([$patient_id]);
$payment_history = $stmt->fetchAll();

// Get appointment history
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.first_name as doctor_first_name,
        u.last_name as doctor_last_name,
        d.department_name
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    LEFT JOIN departments d ON u.department = d.id
    WHERE a.patient_id = ?
    AND a.status != 'cancelled'
    ORDER BY a.appointment_date DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll();

// Get recent lab results
$stmt = $pdo->prepare("
    SELECT 
        lo.id as order_id,
        lo.order_date,
        loi.test_name,
        loi.result_value,
        loi.result_unit,
        loi.reference_range,
        loi.result_notes,
        loi.status as test_status,
        loi.tested_at,
        u.first_name as technician_first_name,
        u.last_name as technician_last_name
    FROM lab_orders lo
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    LEFT JOIN users u ON loi.tested_by = u.id
    WHERE lo.patient_id = ?
    AND loi.status = 'completed'
    AND loi.result_value IS NOT NULL
    ORDER BY loi.tested_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$lab_results = $stmt->fetchAll();

// Calculate totals
$total_outstanding = 0;
$total_paid = 0;
foreach ($outstanding_bills as $bill) {
    $total_outstanding += $bill['balance_amount'];
    $total_paid += $bill['paid_amount'];
}

// Calculate visit statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_visits,
        MIN(visit_date) as first_visit,
        MAX(visit_date) as last_visit,
        DATEDIFF(CURDATE(), MAX(visit_date)) as days_since_last_visit
    FROM visits
    WHERE patient_id = ?
");
$stmt->execute([$patient_id]);
$visit_stats = $stmt->fetch();

// Get current medications
$stmt = $pdo->prepare("
    SELECT 
        pm.medication_name,
        pm.dosage,
        pm.frequency,
        pm.start_date,
        pm.end_date,
        pm.prescribed_by,
        pm.instructions,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name
    FROM patient_medications pm
    LEFT JOIN users u ON pm.prescribed_by = u.id
    WHERE pm.patient_id = ?
    AND pm.status = 'active'
    AND (pm.end_date IS NULL OR pm.end_date >= CURDATE())
    ORDER BY pm.start_date DESC
");
$stmt->execute([$patient_id]);
$current_medications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0891b2;
            --primary-light: #06b6d4;
            --primary-dark: #0e7490;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #134e4a;
            --light: #f0fdfa;
            --gray: #64748b;
            --gray-light: #f1f5f9;
            --border: #e5e7eb;
        }
        
        .main-content {
            margin-left: 300px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        /* Patient Header */
        .patient-header {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border-left: 5px solid var(--primary);
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: start;
        }
        
        @media (max-width: 768px) {
            .patient-header {
                grid-template-columns: 1fr;
            }
        }
        
        .patient-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }
        
        .patient-info h1 {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            font-size: 2rem;
            font-weight: 700;
        }
        
        .patient-id {
            color: var(--primary);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .patient-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-light);
            border-radius: 0.5rem;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .stat-icon.purple { background: var(--primary); }
        .stat-icon.orange { background: var(--warning); }
        .stat-icon.green { background: var(--success); }
        .stat-icon.red { background: var(--danger); }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            color: var(--gray);
            font-size: 0.75rem;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(6, 182, 212, 0.2);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .tab-btn {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(6, 182, 212, 0.05);
        }
        
        .tab-btn:hover {
            background: var(--gray-light);
        }
        
        .tab-content {
            display: none;
            padding: 1.5rem;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Cards */
        .info-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
            margin-bottom: 1.5rem;
        }
        
        .info-card h3 {
            margin: 0 0 1rem 0;
            color: var(--dark);
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .info-value {
            font-size: 0.875rem;
            color: var(--dark);
            font-weight: 500;
        }
        
        /* Tables */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--gray-light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            font-size: 0.875rem;
            vertical-align: top;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { 
            background: #fef3c7; 
            color: #d97706; 
        }
        
        .status-paid { 
            background: #dcfce7; 
            color: #166534; 
        }
        
        .status-partial { 
            background: #dbeafe; 
            color: #1d4ed8; 
        }
        
        .status-overdue { 
            background: #fee2e2; 
            color: #dc2626; 
        }
        
        .status-active { 
            background: #dcfce7; 
            color: #166534; 
        }
        
        .status-inactive { 
            background: #f3f4f6; 
            color: #6b7280; 
        }
        
        /* Service Badges */
        .service-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .service-badge.hospital { background: #dbeafe; color: var(--primary); }
        .service-badge.lab { background: #fef3c7; color: var(--warning); }
        .service-badge.pharmacy { background: #f3e8ff; color: #8b5cf6; }
        
        /* Medical Alerts */
        .medical-alert {
            background: #fee2e2;
            border-left: 4px solid var(--danger);
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .medical-alert.warning {
            background: #fef3c7;
            border-left-color: var(--warning);
        }
        
        .medical-alert.info {
            background: #dbeafe;
            border-left-color: var(--info);
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
        }
        
        .timeline-content {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        /* Responsive Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        
        /* Print Styles */
        @media print {
            .main-content {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .quick-actions,
            .tabs-header,
            .btn {
                display: none !important;
            }
            
            .tab-content {
                display: block !important;
            }
            
            .info-card {
                box-shadow: none;
                border: 1px solid var(--border);
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Patient Header -->
        <div class="patient-header">
            <div class="patient-avatar">
                <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
            </div>
            
            <div class="patient-info">
                <h1><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h1>
                
                <div class="patient-id">
                    <i class="fas fa-id-card"></i>
                    Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?>
                    <?php if ($patient['patient_type']): ?>
                        <span class="status-badge status-active" style="margin-left: 0.5rem;">
                            <?php echo ucfirst($patient['patient_type']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="patient-stats">
                    <div class="stat-item">
                        <div class="stat-icon purple">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $visit_stats ? $visit_stats['total_visits'] : 0; ?></h3>
                            <p>Total Visits</p>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon red">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Ksh <?php echo number_format($total_outstanding, 2); ?></h3>
                            <p>Outstanding Balance</p>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon green">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>
                                <?php echo $visit_stats && $visit_stats['last_visit'] ? 
                                    date('M j, Y', strtotime($visit_stats['last_visit'])) : 'N/A'; ?>
                            </h3>
                            <p>Last Visit</p>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon orange">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($current_medications); ?></h3>
                            <p>Active Medications</p>
                        </div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="window.location.href='pending_payments.php?patient_id=<?php echo $patient_id; ?>'">
                        <i class="fas fa-credit-card"></i> View Payments
                    </button>
                    <button class="btn btn-success" onclick="window.location.href='add_appointment.php?patient_id=<?php echo $patient_id; ?>'">
                        <i class="fas fa-calendar-plus"></i> New Appointment
                    </button>
                    <button class="btn btn-warning" onclick="window.location.href='add_lab_order.php?patient_id=<?php echo $patient_id; ?>'">
                        <i class="fas fa-vial"></i> Lab Order
                    </button>
                    <button class="btn btn-info" onclick="printPatientProfile()">
                        <i class="fas fa-print"></i> Print Profile
                    </button>
                    <button class="btn btn-danger" onclick="window.location.href='edit_patient.php?id=<?php echo $patient_id; ?>'">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Medical Alerts -->
        <?php if (!empty($patient['allergies']) || !empty($patient['chronic_conditions'])): ?>
        <div class="medical-alert warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Medical Alerts:</strong>
                <?php 
                $alerts = [];
                if ($patient['allergies']) $alerts[] = "Allergies: " . htmlspecialchars($patient['allergies']);
                if ($patient['chronic_conditions']) $alerts[] = "Chronic Conditions: " . htmlspecialchars($patient['chronic_conditions']);
                echo implode(' | ', $alerts);
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($total_outstanding > 0): ?>
        <div class="medical-alert">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Financial Alert:</strong> 
                This patient has Ksh <?php echo number_format($total_outstanding, 2); ?> in outstanding payments.
                <?php if (count($outstanding_bills) > 3): ?>
                    <br><small>There are <?php echo count($outstanding_bills); ?> unpaid bills across multiple services.</small>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('overview')">
                    <i class="fas fa-user"></i> Overview
                </button>
                <button class="tab-btn" onclick="switchTab('billing')">
                    <i class="fas fa-file-invoice-dollar"></i> Billing & Payments
                </button>
                <button class="tab-btn" onclick="switchTab('medical')">
                    <i class="fas fa-heartbeat"></i> Medical History
                </button>
                <button class="tab-btn" onclick="switchTab('appointments')">
                    <i class="fas fa-calendar-alt"></i> Appointments
                </button>
                <button class="tab-btn" onclick="switchTab('lab')">
                    <i class="fas fa-vial"></i> Lab Results
                </button>
                <button class="tab-btn" onclick="switchTab('insurance')">
                    <i class="fas fa-shield-alt"></i> Insurance
                </button>
            </div>
            
            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <div class="grid-2">
                    <!-- Personal Information -->
                    <div class="info-card">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Date of Birth</span>
                                <span class="info-value">
                                    <?php echo $patient['date_of_birth'] ? date('M j, Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?>
                                    <?php if ($patient['date_of_birth']): 
                                        $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                                    ?>
                                        (<?php echo $age; ?> years)
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Gender</span>
                                <span class="info-value"><?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Marital Status</span>
                                <span class="info-value"><?php echo $patient['marital_status'] ? ucfirst($patient['marital_status']) : 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Occupation</span>
                                <span class="info-value"><?php echo $patient['occupation'] ? htmlspecialchars($patient['occupation']) : 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">ID Number</span>
                                <span class="info-value"><?php echo $patient['id_number'] ? htmlspecialchars($patient['id_number']) : 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Phone</span>
                                <span class="info-value">
                                    <a href="tel:<?php echo htmlspecialchars($patient['phone']); ?>">
                                        <?php echo $patient['phone'] ? htmlspecialchars($patient['phone']) : 'N/A'; ?>
                                    </a>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value">
                                    <?php if ($patient['email']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($patient['email']); ?>">
                                        <?php echo htmlspecialchars($patient['email']); ?>
                                    </a>
                                    <?php else: ?>
                                    N/A
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Emergency Contact</span>
                                <span class="info-value">
                                    <?php echo $patient['emergency_contact'] ? htmlspecialchars($patient['emergency_contact']) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="info-card">
                        <h3><i class="fas fa-home"></i> Contact Information</h3>
                        <div class="info-grid">
                            <?php if ($patient['county']): ?>
                            <div class="info-item">
                                <span class="info-label">County</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['county']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($patient['sub_county']): ?>
                            <div class="info-item">
                                <span class="info-label">Sub-County</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['sub_county']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($patient['ward']): ?>
                            <div class="info-item">
                                <span class="info-label">Ward</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['ward']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($patient['village']): ?>
                            <div class="info-item">
                                <span class="info-label">Village/ Estate</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['village']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($patient['residence']): ?>
                            <div class="info-item">
                                <span class="info-label">Residence</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['residence']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($patient['postal_address']): ?>
                            <div class="info-item">
                                <span class="info-label">Postal Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['postal_address']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Next of Kin -->
                    <?php if ($patient['next_of_kin_name']): ?>
                    <div class="info-card">
                        <h3><i class="fas fa-users"></i> Next of Kin</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_name']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Relationship</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_relationship']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Phone</span>
                                <span class="info-value">
                                    <a href="tel:<?php echo htmlspecialchars($patient['next_of_kin_phone']); ?>">
                                        <?php echo htmlspecialchars($patient['next_of_kin_phone']); ?>
                                    </a>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['next_of_kin_address']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Visit Statistics -->
                    <div class="info-card">
                        <h3><i class="fas fa-chart-line"></i> Visit Statistics</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Total Visits</span>
                                <span class="info-value"><?php echo $visit_stats ? $visit_stats['total_visits'] : 0; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">First Visit</span>
                                <span class="info-value">
                                    <?php echo $visit_stats && $visit_stats['first_visit'] ? 
                                        date('M j, Y', strtotime($visit_stats['first_visit'])) : 'N/A'; ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Last Visit</span>
                                <span class="info-value">
                                    <?php echo $visit_stats && $visit_stats['last_visit'] ? 
                                        date('M j, Y', strtotime($visit_stats['last_visit'])) : 'N/A'; ?>
                                    <?php if ($visit_stats && $visit_stats['days_since_last_visit'] > 0): ?>
                                        <br><small>(<?php echo $visit_stats['days_since_last_visit']; ?> days ago)</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Billing & Payments Tab -->
            <div id="billing" class="tab-content">
                <!-- Outstanding Bills -->
                <div class="info-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Outstanding Bills (<?php echo count($outstanding_bills); ?>)</h3>
                    <?php if (count($outstanding_bills) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Bill Details</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outstanding_bills as $bill): 
                                    $is_overdue = isset($bill['bill_date']) && 
                                                 (strtotime($bill['bill_date']) < strtotime('-30 days'));
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($bill['bill_number']); ?></div>
                                        <small style="color: var(--gray); font-size: 0.75rem;">
                                            <?php echo htmlspecialchars(substr($bill['items'] ?? '', 0, 50)); ?>
                                            <?php if (strlen($bill['items'] ?? '') > 50): ?>...<?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="service-badge <?php echo $bill['bill_type']; ?>">
                                            <?php echo ucfirst($bill['bill_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $bill['bill_date'] ? date('M j, Y', strtotime($bill['bill_date'])) : 'N/A'; ?>
                                    </td>
                                    <td style="font-weight: 500;">
                                        Ksh <?php echo number_format($bill['total_amount'], 2); ?>
                                    </td>
                                    <td style="color: var(--success);">
                                        Ksh <?php echo number_format($bill['paid_amount'], 2); ?>
                                    </td>
                                    <td style="color: var(--danger); font-weight: 600;">
                                        Ksh <?php echo number_format($bill['balance_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <?php if ($is_overdue): ?>
                                        <span class="status-badge status-overdue">Overdue</span>
                                        <?php else: ?>
                                        <span class="status-badge <?php echo $bill['paid_amount'] > 0 ? 'status-partial' : 'status-pending'; ?>">
                                            <?php echo $bill['paid_amount'] > 0 ? 'Partial' : 'Pending'; ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="window.location.href='process_payment.php?bill_id=<?php echo $bill['id']; ?>&type=<?php echo $bill['bill_type']; ?>'"
                                                style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">
                                            <i class="fas fa-credit-card"></i> Pay
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding: 1rem; background: var(--gray-light); border-radius: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; font-weight: 600;">
                            <span>Total Outstanding Balance:</span>
                            <span style="color: var(--danger);">Ksh <?php echo number_format($total_outstanding, 2); ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No outstanding bills found for this patient.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Payment History -->
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Recent Payments</h3>
                    <?php if (count($payment_history) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Payment #</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Received By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_history as $payment): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></td>
                                    <td style="font-weight: 600; color: var(--success);">
                                        Ksh <?php echo number_format($payment['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.75rem; background: #e5e7eb; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                                            <?php echo ucfirst($payment['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-paid">Completed</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['created_by']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No payment history found for this patient.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Medical History Tab -->
            <div id="medical" class="tab-content">
                <div class="grid-2">
                    <!-- Vital Signs -->
                    <div class="info-card">
                        <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Blood Group</span>
                                <span class="info-value"><?php echo $patient['blood_group'] ?: 'Not recorded'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Height</span>
                                <span class="info-value"><?php echo $patient['height'] ? $patient['height'] . ' cm' : 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Weight</span>
                                <span class="info-value"><?php echo $patient['weight'] ? $patient['weight'] . ' kg' : 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">BMI</span>
                                <span class="info-value">
                                    <?php echo $patient['bmi'] ? number_format($patient['bmi'], 1) : 'N/A'; ?>
                                    <?php if ($patient['bmi']): ?>
                                        <?php 
                                        if ($patient['bmi'] < 18.5) echo '(Underweight)';
                                        elseif ($patient['bmi'] >= 18.5 && $patient['bmi'] < 25) echo '(Normal)';
                                        elseif ($patient['bmi'] >= 25 && $patient['bmi'] < 30) echo '(Overweight)';
                                        else echo '(Obese)';
                                        ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Medications -->
                    <div class="info-card">
                        <h3><i class="fas fa-pills"></i> Current Medications (<?php echo count($current_medications); ?>)</h3>
                        <?php if (count($current_medications) > 0): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($current_medications as $med): ?>
                            <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.75rem; border-left: 3px solid var(--primary);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <strong><?php echo htmlspecialchars($med['medication_name']); ?></strong>
                                    <span class="status-badge status-active">Active</span>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--gray);">
                                    <div style="margin-bottom: 0.25rem;">
                                        <i class="fas fa-prescription-bottle"></i> 
                                        <?php echo htmlspecialchars($med['dosage']); ?> - <?php echo htmlspecialchars($med['frequency']); ?>
                                    </div>
                                    <?php if ($med['start_date']): ?>
                                    <div style="margin-bottom: 0.25rem;">
                                        <i class="far fa-calendar"></i> 
                                        Started: <?php echo date('M j, Y', strtotime($med['start_date'])); ?>
                                        <?php if ($med['end_date']): ?>
                                            to <?php echo date('M j, Y', strtotime($med['end_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($med['doctor_name']): ?>
                                    <div style="margin-bottom: 0.25rem;">
                                        <i class="fas fa-user-md"></i> Prescribed by: <?php echo htmlspecialchars($med['doctor_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($med['instructions']): ?>
                                    <div style="font-size: 0.8rem; background: white; padding: 0.5rem; border-radius: 0.25rem; margin-top: 0.5rem;">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($med['instructions']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding: 1rem;">
                            <i class="fas fa-pills"></i>
                            <p>No active medications recorded.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Medical History -->
                    <div class="info-card">
                        <h3><i class="fas fa-file-medical"></i> Medical History</h3>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php if ($patient['allergies'] || $patient['chronic_conditions'] || $patient['medical_notes']): ?>
                                <?php if ($patient['allergies']): ?>
                                <div style="background: #fef3c7; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border-left: 3px solid var(--warning);">
                                    <strong style="display: block; margin-bottom: 0.5rem; color: #92400e;">
                                        <i class="fas fa-exclamation-triangle"></i> Allergies
                                    </strong>
                                    <p style="margin: 0; font-size: 0.875rem;"><?php echo htmlspecialchars($patient['allergies']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($patient['chronic_conditions']): ?>
                                <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border-left: 3px solid var(--danger);">
                                    <strong style="display: block; margin-bottom: 0.5rem; color: #991b1b;">
                                        <i class="fas fa-heartbeat"></i> Chronic Conditions
                                    </strong>
                                    <p style="margin: 0; font-size: 0.875rem;"><?php echo htmlspecialchars($patient['chronic_conditions']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($patient['medical_notes']): ?>
                                <div style="background: #dbeafe; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border-left: 3px solid var(--info);">
                                    <strong style="display: block; margin-bottom: 0.5rem; color: #1e40af;">
                                        <i class="fas fa-sticky-note"></i> Medical Notes
                                    </strong>
                                    <p style="margin: 0; font-size: 0.875rem;"><?php echo htmlspecialchars($patient['medical_notes']); ?></p>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                            <div class="empty-state" style="padding: 1rem;">
                                <i class="fas fa-file-medical"></i>
                                <p>No medical history recorded.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Appointments Tab -->
            <div id="appointments" class="tab-content">
                <div class="info-card">
                    <h3><i class="fas fa-calendar-alt"></i> Appointment History</h3>
                    <?php if (count($appointments) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Department</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($apt['appointment_date'])); ?>
                                        <br><small style="color: var(--gray);"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($apt['doctor_first_name']): ?>
                                        Dr. <?php echo htmlspecialchars($apt['doctor_first_name'] . ' ' . $apt['doctor_last_name']); ?>
                                        <?php else: ?>
                                        N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($apt['department_name'] ?: $apt['department'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span style="font-size: 0.75rem; background: #e5e7eb; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                                            <?php echo ucfirst($apt['appointment_type'] ?: 'regular'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $apt['status'] == 'completed' ? 'status-paid' : 'status-pending'; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small style="color: var(--gray);">
                                            <?php echo htmlspecialchars(substr($apt['notes'] ?? '', 0, 50)); ?>
                                            <?php if (strlen($apt['notes'] ?? '') > 50): ?>...<?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar"></i>
                        <p>No appointment history found for this patient.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Lab Results Tab -->
            <div id="lab" class="tab-content">
                <div class="info-card">
                    <h3><i class="fas fa-vial"></i> Laboratory Results</h3>
                    <?php if (count($lab_results) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Test Date</th>
                                    <th>Test Name</th>
                                    <th>Result</th>
                                    <th>Reference Range</th>
                                    <th>Status</th>
                                    <th>Technician</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lab_results as $result): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($result['tested_at'])); ?>
                                        <br><small style="color: var(--gray);"><?php echo date('g:i A', strtotime($result['tested_at'])); ?></small>
                                    </td>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($result['test_name']); ?></td>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($result['result_value']); ?>
                                        <?php if ($result['result_unit']): ?>
                                        <small style="color: var(--gray);"><?php echo htmlspecialchars($result['result_unit']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small style="color: var(--gray);">
                                            <?php echo htmlspecialchars($result['reference_range'] ?: 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-paid">Completed</span>
                                    </td>
                                    <td>
                                        <?php if ($result['technician_first_name']): ?>
                                        <?php echo htmlspecialchars($result['technician_first_name'] . ' ' . $result['technician_last_name']); ?>
                                        <?php else: ?>
                                        N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small style="color: var(--gray);">
                                            <?php echo htmlspecialchars(substr($result['result_notes'] ?? '', 0, 50)); ?>
                                            <?php if (strlen($result['result_notes'] ?? '') > 50): ?>...<?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-vial"></i>
                        <p>No laboratory results found for this patient.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Insurance Tab -->
            <div id="insurance" class="tab-content">
                <div class="info-card">
                    <h3><i class="fas fa-shield-alt"></i> Insurance Information</h3>
                    <?php if ($patient['insurance_provider']): ?>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Insurance Provider</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['insurance_provider']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Membership Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['membership_number']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Principal Member</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['principal_member']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                <span class="status-badge <?php echo $patient['insurance_status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ucfirst($patient['insurance_status'] ?: 'inactive'); ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Expiry Date</span>
                            <span class="info-value">
                                <?php echo $patient['expiry_date'] ? date('M j, Y', strtotime($patient['expiry_date'])) : 'N/A'; ?>
                                <?php if ($patient['expiry_date'] && strtotime($patient['expiry_date']) < strtotime('+30 days')): ?>
                                <br><small style="color: var(--danger);">Expires soon</small>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shield-alt"></i>
                        <p>No insurance information recorded.</p>
                    </div>
                    <?php endif; ?>
                </div>
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
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }
        
        // Print patient profile
        function printPatientProfile() {
            const originalContent = document.body.innerHTML;
            const printContent = document.querySelector('.main-content').innerHTML;
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Patient Profile - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px;
                            color: #333;
                        }
                        .patient-header { 
                            border-bottom: 2px solid #0891b2;
                            padding-bottom: 20px;
                            margin-bottom: 20px;
                        }
                        h1 { color: #134e4a; }
                        table { 
                            width: 100%; 
                            border-collapse: collapse;
                            margin: 10px 0;
                        }
                        th, td { 
                            border: 1px solid #ddd; 
                            padding: 8px; 
                            text-align: left;
                        }
                        th { 
                            background-color: #f8fafc;
                            font-weight: bold;
                        }
                        .print-hide { display: none; }
                        .info-card { 
                            border: 1px solid #ddd; 
                            padding: 15px; 
                            margin-bottom: 15px;
                            page-break-inside: avoid;
                        }
                        .status-badge {
                            padding: 2px 6px;
                            border-radius: 10px;
                            font-size: 12px;
                            font-weight: bold;
                        }
                        @page { size: A4; margin: 0.5in; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <div style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
                        Printed on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                    </div>
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Auto-refresh appointment status
        function refreshAppointmentStatus() {
            fetch(`api/check_appointments.php?patient_id=<?php echo $patient_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.upcoming_appointments && data.upcoming_appointments.length > 0) {
                        // Show notification for upcoming appointments
                        data.upcoming_appointments.forEach(apt => {
                            if (apt.status === 'confirmed' && apt.is_soon) {
                                showNotification(`Upcoming appointment with ${apt.doctor_name} at ${apt.time}`);
                            }
                        });
                    }
                })
                .catch(error => console.error('Error checking appointments:', error));
        }
        
        // Show notification
        function showNotification(message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #10b981;
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            `;
            
            notification.innerHTML = `
                <i class="fas fa-bell"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Check for important medical alerts on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add CSS for animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
            
            // Check for critical lab results
            <?php 
            $critical_results = array_filter($lab_results, function($result) {
                return stripos($result['result_notes'] ?? '', 'critical') !== false || 
                       stripos($result['result_notes'] ?? '', 'abnormal') !== false;
            });
            
            if (!empty($critical_results)): ?>
            setTimeout(() => {
                showNotification('⚠️ Patient has critical lab results that require attention');
            }, 1000);
            <?php endif; ?>
            
            // Check for overdue bills
            <?php if ($total_outstanding > 0): ?>
            setTimeout(() => {
                showNotification('💸 Patient has outstanding bills: Ksh <?php echo number_format($total_outstanding, 2); ?>');
            }, 2000);
            <?php endif; ?>
            
            // Auto-refresh every 5 minutes
            setInterval(refreshAppointmentStatus, 300000);
        });
    </script>
</body>
</html>