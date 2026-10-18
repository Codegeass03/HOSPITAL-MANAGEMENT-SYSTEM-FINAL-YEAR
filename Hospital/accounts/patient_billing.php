<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php"; // MySQLi connection for pharmacy

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get patient ID from URL
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$patient_id) {
    $_SESSION['error'] = "Patient ID is required.";
    // header("Location: billing_dashboard.php");
    // exit();
}

// Get patient information
$patient = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            CONCAT(p.first_name, ' ', p.last_name) as full_name,
            p.patient_id as patient_number,
            (SELECT COALESCE(SUM(balance_amount), 0) FROM billing WHERE patient_id = p.id AND balance_amount > 0) as total_outstanding
        FROM patients p 
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        // header("Location: billing_dashboard.php");
        // exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    $_SESSION['error'] = "Error loading patient information.";
    header("Location: billing_dashboard.php");
    exit();
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Get patient's bills from unified system
$patient_bills = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            COUNT(bi.id) as item_count,
            GROUP_CONCAT(DISTINCT bi.item_type) as service_types,
            'hospital' as source
        FROM billing b
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE b.patient_id = ?
        GROUP BY b.id
        ORDER BY b.bill_date DESC
    ");
    $stmt->execute([$patient_id]);
    $patient_bills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patient bills: " . $e->getMessage());
}

// Get patient's pharmacy bills from INTEGRATED system WITH PAYMENT TRACKING
$patient_pharmacy_bills = [];
if ($patient_id) {
    mysqli_select_db($link, "hospital");
    
    // Query with payment status tracking
    $pharmacy_query = "
        SELECT 
            pb.id,
            pb.bill_no as bill_number,
            pb.date as bill_date,
            pb.CustomerName as patient_name,
            COALESCE(SUM(pbd.total_amount), 0) as total_amount,
            COALESCE(pb.paid_amount, 0) as amount_paid,
            COALESCE(pb.balance_amount, 0) as balance_amount,
            COALESCE(pb.payment_status, 'pending') as status,
            'pharmacy' as source,
            COUNT(pbd.id) as item_count,
            GROUP_CONCAT(DISTINCT pbd.product_name) as product_names
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE pb.patient_id = '$patient_id'
        GROUP BY pb.id, pb.paid_amount, pb.balance_amount, pb.payment_status
        ORDER BY pb.date DESC
    ";
    
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    if ($pharmacy_result) {
        while ($row = mysqli_fetch_assoc($pharmacy_result)) {
            $patient_pharmacy_bills[] = $row;
        }
    } else {
        // Fallback if new columns don't exist yet
        error_log("Pharmacy query failed: " . mysqli_error($link));
        
        // Try simpler query without payment tracking
        $pharmacy_query_simple = "
            SELECT 
                pb.id,
                pb.bill_no as bill_number,
                pb.date as bill_date,
                pb.CustomerName as patient_name,
                COALESCE(SUM(pbd.total_amount), 0) as total_amount,
                COALESCE(SUM(pbd.total_amount), 0) as amount_paid,
                0 as balance_amount,
                'paid' as status,
                'pharmacy' as source,
                COUNT(pbd.id) as item_count,
                GROUP_CONCAT(DISTINCT pbd.product_name) as product_names
            FROM pharmacy_billing pb
            LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            WHERE pb.patient_id = '$patient_id'
            GROUP BY pb.id
            ORDER BY pb.date DESC
        ";
        
        $pharmacy_result_simple = mysqli_query($link, $pharmacy_query_simple);
        if ($pharmacy_result_simple) {
            while ($row = mysqli_fetch_assoc($pharmacy_result_simple)) {
                $patient_pharmacy_bills[] = $row;
            }
        }
    }
}

// Merge hospital and pharmacy bills
$all_patient_bills = array_merge($patient_bills, $patient_pharmacy_bills);
usort($all_patient_bills, function($a, $b) {
    return strtotime($b['bill_date']) - strtotime($a['bill_date']);
});

// Get bill items for detailed view (hospital only)
$bill_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            bi.*,
            b.bill_number,
            b.bill_date,
            b.status as bill_status
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE b.patient_id = ?
        ORDER BY b.bill_date DESC, bi.id DESC
    ");
    $stmt->execute([$patient_id]);
    $bill_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching bill items: " . $e->getMessage());
}

// Get payment history - using your existing payments table structure
$payment_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.first_name as received_by_first,
            u.last_name as received_by_last
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.patient_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$patient_id]);
    $payment_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching payment history: " . $e->getMessage());
}

// Get patient appointments for service history
$patient_appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            s.name as service_name,
            s.price as service_price
        FROM appointments a
        LEFT JOIN users d ON a.doctor_id = d.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$patient_id]);
    $patient_appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patient appointments: " . $e->getMessage());
}

// Get lab orders for service history
$patient_lab_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            lo.*,
            CONCAT(u.first_name, ' ', u.last_name) as doctor_name
        FROM lab_orders lo
        LEFT JOIN users u ON lo.ordered_by = u.id
        WHERE lo.patient_id = ?
        ORDER BY lo.order_date DESC
    ");
    $stmt->execute([$patient_id]);
    $patient_lab_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patient lab orders: " . $e->getMessage());
}

// Get bed assignments for service history
$patient_admissions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            ba.*,
            b.bed_number,
            w.ward_name,
            w.ward_type
        FROM bed_assignments ba
        LEFT JOIN beds b ON ba.bed_id = b.id
        LEFT JOIN ward w ON b.ward_id = w.id
        WHERE ba.patient_id = ?
        ORDER BY ba.admission_date DESC
    ");
    $stmt->execute([$patient_id]);
    $patient_admissions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patient admissions: " . $e->getMessage());
}

// Calculate statistics - INCLUDE ALL SERVICES (like unified_payment.php)
$stats = [
    'total_bills' => 0,
    'total_amount' => 0,
    'total_paid' => 0,
    'total_outstanding' => 0,
    'bills_paid' => 0,
    'bills_pending' => 0,
    'total_appointments' => count($patient_appointments),
    'total_lab_orders' => count($patient_lab_orders),
    'total_admissions' => count($patient_admissions),
    'total_pharmacy_bills' => count($patient_pharmacy_bills),
    'pharmacy_outstanding' => 0,
    'lab_outstanding' => 0,
    'consultation_outstanding' => 0,
    'admission_outstanding' => 0
];

// Calculate from ALL bills (hospital + pharmacy)
foreach ($all_patient_bills as $bill) {
    $stats['total_bills']++;
    $stats['total_amount'] += $bill['total_amount'];
    
    if ($bill['balance_amount'] <= 0) {
        $stats['bills_paid']++;
    } else {
        $stats['bills_pending']++;
        // Add to outstanding balance for pharmacy bills too
        if ($bill['source'] === 'pharmacy') {
            $stats['pharmacy_outstanding'] += $bill['balance_amount'];
        }
    }
}

// GET OUTSTANDING BALANCES FROM ALL SERVICES (like unified_payment.php)
$outstanding_balances = [];
try {
    // 1. Lab Orders Outstanding
    $stmt = $pdo->prepare("
        SELECT 
            SUM(lo.total_amount - lo.paid_amount) as lab_outstanding
        FROM lab_orders lo
        WHERE lo.patient_id = ? 
        AND lo.payment_status IN ('unpaid', 'partial')
        AND (lo.total_amount - lo.paid_amount) > 0
    ");
    $stmt->execute([$patient_id]);
    $lab_outstanding = $stmt->fetchColumn();
    $stats['lab_outstanding'] = $lab_outstanding ?: 0;

    // 2. Appointment Consultation Fees Outstanding
    $stmt = $pdo->prepare("
        SELECT 
            SUM(a.consultation_fee) as consultation_outstanding
        FROM appointments a
        WHERE a.patient_id = ? 
        AND a.status = 'completed'
        AND a.consultation_fee > 0
        AND NOT EXISTS (
            SELECT 1 FROM bill_items bi 
            WHERE bi.bill_id IN (SELECT id FROM billing WHERE patient_id = ?)
            AND bi.item_type = 'consultation'
            AND bi.service_date = a.appointment_date
        )
    ");
    $stmt->execute([$patient_id, $patient_id]);
    $consultation_outstanding = $stmt->fetchColumn();
    $stats['consultation_outstanding'] = $consultation_outstanding ?: 0;

    // 3. Bed Charges Outstanding
    $stmt = $pdo->prepare("
        SELECT 
            SUM(DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day) as admission_outstanding
        FROM bed_assignments ba
        INNER JOIN beds b ON ba.bed_id = b.id
        INNER JOIN ward w ON b.ward_id = w.id
        WHERE ba.patient_id = ?
        AND ba.status = 'active'
        AND DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day > 0
    ");
    $stmt->execute([$patient_id]);
    $admission_outstanding = $stmt->fetchColumn();
    $stats['admission_outstanding'] = $admission_outstanding ?: 0;

    // Calculate TOTAL outstanding from all sources
    $stats['total_outstanding'] = 
        $stats['pharmacy_outstanding'] + 
        $stats['lab_outstanding'] + 
        $stats['consultation_outstanding'] + 
        $stats['admission_outstanding'] +
        ($patient['total_outstanding'] ?? 0); // From existing billing table

} catch (PDOException $e) {
    error_log("Error calculating outstanding balances: " . $e->getMessage());
    // Fallback to original calculation
    $stats['total_outstanding'] = $patient['total_outstanding'] ?? 0;
}

// Get outstanding bills for quick payment (BOTH hospital and pharmacy)
$outstanding_bills = [];

// Hospital outstanding bills
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            bill_number,
            total_amount,
            paid_amount,
            balance_amount,
            bill_date,
            'hospital' as source
        FROM billing 
        WHERE patient_id = ? AND balance_amount > 0
        ORDER BY bill_date ASC
    ");
    $stmt->execute([$patient_id]);
    $hospital_outstanding = $stmt->fetchAll();
    $outstanding_bills = array_merge($outstanding_bills, $hospital_outstanding);
} catch (PDOException $e) {
    error_log("Error fetching outstanding bills: " . $e->getMessage());
}

// Pharmacy outstanding bills
if ($patient_id) {
    mysqli_select_db($link, "hospital");
    $pharmacy_outstanding_query = "
        SELECT 
            pb.id,
            pb.bill_no as bill_number,
            COALESCE(SUM(pbd.total_amount), 0) as total_amount,
            COALESCE(pb.paid_amount, 0) as paid_amount,
            COALESCE(pb.balance_amount, 0) as balance_amount,
            pb.date as bill_date,
            'pharmacy' as source
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE pb.patient_id = '$patient_id'
        AND COALESCE(pb.balance_amount, 0) > 0
        GROUP BY pb.id, pb.paid_amount, pb.balance_amount
        ORDER BY pb.date ASC
    ";
    
    $pharmacy_outstanding_result = mysqli_query($link, $pharmacy_outstanding_query);
    if ($pharmacy_outstanding_result) {
        while ($row = mysqli_fetch_assoc($pharmacy_outstanding_result)) {
            $outstanding_bills[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Billing - <?php echo htmlspecialchars($patient['full_name']); ?> - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            color: #334155;
            font-size: 1.875rem;
            font-weight: 600;
        }
        
        .patient-info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 0.875rem;
            color: #111827;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card.outstanding {
            border-left: 4px solid #dc2626;
        }
        
        .stat-card.paid {
            border-left: 4px solid #059669;
        }
        
        .stat-card.pending {
            border-left: 4px solid #d97706;
        }
        
        .stat-card.total {
            border-left: 4px solid #3b82f6;
        }
        
        .stat-card.services {
            border-left: 4px solid #8b5cf6;
        }
        
        .stat-card.pharmacy {
            border-left: 4px solid #f59e0b;
        }
        
        .stat-card.pharmacy-outstanding {
            border-left: 4px solid #ef4444;
        }
        
        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-value.outstanding { color: #dc2626; }
        .stat-value.paid { color: #059669; }
        .stat-value.pending { color: #d97706; }
        .stat-value.total { color: #3b82f6; }
        .stat-value.services { color: #8b5cf6; }
        .stat-value.pharmacy { color: #f59e0b; }
        .stat-value.pharmacy-outstanding { color: #ef4444; }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .bill-list, .payment-list, .outstanding-list, .service-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .bill-item, .payment-item, .outstanding-item, .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .bill-item:hover, .payment-item:hover, .outstanding-item:hover, .service-item:hover {
            background: #f9fafb;
            border-color: #3b82f6;
        }
        
        .bill-info h4, .payment-info h4, .service-info h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
        }
        
        .bill-info p, .payment-info p, .service-info p {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .bill-details {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .bill-amount {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            text-align: right;
        }
        
        .bill-balance {
            font-size: 0.75rem;
            color: #dc2626;
            margin-top: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        
        .source-badge {
            padding: 0.125rem 0.5rem;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 0.25rem;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .source-badge.pharmacy {
            background: #dcfce7;
            color: #166534;
        }
        
        .source-badge.hospital {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }
        
        .btn-primary:hover, .btn-success:hover, .btn-danger:hover {
            transform: translateY(-1px);
        }
        
        .view-details {
            font-size: 0.75rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .view-details:hover {
            color: #2563eb;
            text-decoration: underline;
        }
        
        .service-types {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .service-tag {
            padding: 0.125rem 0.5rem;
            background: #f3f4f6;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .payment-method {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .outstanding-alert {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #d97706;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .service-icon {
            width: 32px;
            height: 32px;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
        
        .service-icon.consultation { background: #dbeafe; color: #1e40af; }
        .service-icon.lab { background: #fce7f3; color: #be185d; }
        .service-icon.admission { background: #dcfce7; color: #166534; }
        .service-icon.medication { background: #fef3c7; color: #d97706; }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-user-injured"></i>
                    Patient Billing - <?php echo htmlspecialchars($patient['full_name']); ?>
                </h1>
                <p class="page-subtitle">Complete billing history and payment information</p>
            </div>
            <div>
                <a href="billing_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Patient Information Card -->
        <div class="patient-info-card">
            <div class="patient-header">
                <h3 style="margin: 0; color: #111827;">
                    <i class="fas fa-user-circle"></i> Patient Information
                </h3>
                <?php if ($stats['total_outstanding'] > 0): ?>
                <div style="color: #dc2626; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Outstanding Balance: Ksh <?php echo number_format($stats['total_outstanding'], 2); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="patient-details">
                <div class="detail-item">
                    <span class="detail-label">Patient ID</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['patient_number']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Full Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date of Birth</span>
                    <span class="detail-value"><?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Age</span>
                    <span class="detail-value"><?php echo $patient['age']; ?> years</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Gender</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['gender']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Insurance</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['insurance_provider'] ?? 'None'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="unified_payment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-success">
                <i class="fas fa-cash-register"></i> Process Payment
            </a>
            <!-- <a href="create_bill.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                <i class="fas fa-file-medical"></i> Create Hospital Bill
            </a>
            <a href="create_pharmacy_bill.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                <i class="fas fa-pills"></i> Create Pharmacy Bill
            </a> -->
            <a href="patient_search.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Find Another Patient
            </a>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-value total"><?php echo $stats['total_bills']; ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
            <div class="stat-card paid">
                <div class="stat-value paid">Ksh <?php echo number_format($stats['total_paid'], 2); ?></div>
                <div class="stat-label">Total Paid</div>
            </div>
            <div class="stat-card outstanding">
                <div class="stat-value outstanding">Ksh <?php echo number_format($stats['total_outstanding'], 2); ?></div>
                <div class="stat-label">Total Outstanding</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-value pending"><?php echo $stats['bills_pending']; ?></div>
                <div class="stat-label">Pending Bills</div>
            </div>
            <div class="stat-card services">
                <div class="stat-value services"><?php echo $stats['total_appointments'] + $stats['total_lab_orders'] + $stats['total_admissions']; ?></div>
                <div class="stat-label">Total Services</div>
            </div>
            <div class="stat-card pharmacy">
                <div class="stat-value pharmacy"><?php echo $stats['total_pharmacy_bills']; ?></div>
                <div class="stat-label">Pharmacy Bills</div>
            </div>
            
            <!-- Add these new stat cards for different service types -->
            <?php if ($stats['lab_outstanding'] > 0): ?>
            <div class="stat-card" style="border-left-color: #be185d;">
                <div class="stat-value" style="color: #be185d;">Ksh <?php echo number_format($stats['lab_outstanding'], 2); ?></div>
                <div class="stat-label">Lab Outstanding</div>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['consultation_outstanding'] > 0): ?>
            <div class="stat-card" style="border-left-color: #7c3aed;">
                <div class="stat-value" style="color: #7c3aed;">Ksh <?php echo number_format($stats['consultation_outstanding'], 2); ?></div>
                <div class="stat-label">Consultation Due</div>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['admission_outstanding'] > 0): ?>
            <div class="stat-card" style="border-left-color: #059669;">
                <div class="stat-value" style="color: #059669;">Ksh <?php echo number_format($stats['admission_outstanding'], 2); ?></div>
                <div class="stat-label">Admission Due</div>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['pharmacy_outstanding'] > 0): ?>
            <div class="stat-card pharmacy-outstanding">
                <div class="stat-value pharmacy-outstanding">Ksh <?php echo number_format($stats['pharmacy_outstanding'], 2); ?></div>
                <div class="stat-label">Pharmacy Due</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Outstanding Bills Alert -->
        <?php if ($stats['total_outstanding'] > 0): ?>
        <div class="outstanding-alert">
            <i class="fas fa-exclamation-triangle fa-lg"></i>
            <div>
                <strong>Outstanding Bills Notice</strong>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">
                    Total Outstanding: <strong>Ksh <?php echo number_format($stats['total_outstanding'], 2); ?></strong>
                    <?php if ($stats['lab_outstanding'] > 0): ?>
                    <br>• Lab: Ksh <?php echo number_format($stats['lab_outstanding'], 2); ?>
                    <?php endif; ?>
                    <?php if ($stats['consultation_outstanding'] > 0): ?>
                    <br>• Consultation: Ksh <?php echo number_format($stats['consultation_outstanding'], 2); ?>
                    <?php endif; ?>
                    <?php if ($stats['admission_outstanding'] > 0): ?>
                    <br>• Admission: Ksh <?php echo number_format($stats['admission_outstanding'], 2); ?>
                    <?php endif; ?>
                    <?php if ($stats['pharmacy_outstanding'] > 0): ?>
                    <br>• Pharmacy: Ksh <?php echo number_format($stats['pharmacy_outstanding'], 2); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div>
                <!-- Bills Section -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Billing History (Hospital & Pharmacy)
                        </h2>
                        <span class="stat-label"><?php echo count($all_patient_bills); ?> bills</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($all_patient_bills) > 0): ?>
                        <div class="bill-list">
                            <?php foreach ($all_patient_bills as $bill): ?>
                            <div class="bill-item">
                                <div class="bill-info">
                                    <h4>
                                        <i class="fas fa-<?php echo $bill['source'] === 'pharmacy' ? 'pills' : 'receipt'; ?>"></i> 
                                        <?php echo htmlspecialchars($bill['bill_number']); ?>
                                        <span class="source-badge <?php echo $bill['source']; ?>" style="margin-left: 0.5rem;">
                                            <?php echo $bill['source']; ?>
                                        </span>
                                    </h4>
                                    <p>
                                        <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?> • 
                                        <i class="fas fa-boxes"></i> <?php echo $bill['item_count']; ?> items
                                        <?php if ($bill['source'] === 'pharmacy' && !empty($bill['product_names'])): ?>
                                        • <i class="fas fa-tags"></i> 
                                        <?php 
                                        $products = explode(',', $bill['product_names']);
                                        echo implode(', ', array_slice($products, 0, 3));
                                        if (count($products) > 3) echo '...';
                                        ?>
                                        <?php elseif ($bill['service_types']): ?>
                                        • <i class="fas fa-tags"></i> 
                                        <?php echo implode(', ', array_map(function($type) {
                                            return ucfirst(str_replace('_', ' ', trim($type)));
                                        }, explode(',', $bill['service_types']))); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="bill-details">
                                    <div class="bill-amount">
                                        Ksh <?php echo number_format($bill['total_amount'], 2); ?>
                                        <?php if ($bill['balance_amount'] > 0): ?>
                                        <div class="bill-balance">
                                            Due: Ksh <?php echo number_format($bill['balance_amount'], 2); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge status-<?php echo $bill['status']; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <h3>No Bills Found</h3>
                            <p>This patient has no billing history yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service History -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-history"></i>
                            Service History
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="service-list">
                            <!-- Appointments -->
                            <?php foreach ($patient_appointments as $appointment): ?>
                            <div class="service-item">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="service-icon consultation">
                                        <i class="fas fa-stethoscope"></i>
                                    </div>
                                    <div class="service-info">
                                        <h4>Appointment - <?php echo htmlspecialchars($appointment['appointment_type']); ?></h4>
                                        <p>
                                            <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?> • 
                                            <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?> • 
                                            <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Unknown'); ?>
                                            <?php if ($appointment['service_name']): ?>
                                            • <i class="fas fa-tag"></i> <?php echo htmlspecialchars($appointment['service_name']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>

                            <!-- Lab Orders -->
                            <?php foreach ($patient_lab_orders as $lab_order): ?>
                            <div class="service-item">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="service-icon lab">
                                        <i class="fas fa-flask"></i>
                                    </div>
                                    <div class="service-info">
                                        <h4>Lab Order - <?php echo htmlspecialchars($lab_order['order_number']); ?></h4>
                                        <p>
                                            <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($lab_order['order_date'])); ?> • 
                                            <i class="fas fa-user-md"></i> Ordered by Dr. <?php echo htmlspecialchars($lab_order['doctor_name'] ?? 'Unknown'); ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $lab_order['status']; ?>">
                                    <?php echo ucfirst($lab_order['status']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>

                            <!-- Admissions -->
                            <?php foreach ($patient_admissions as $admission): ?>
                            <div class="service-item">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="service-icon admission">
                                        <i class="fas fa-procedures"></i>
                                    </div>
                                    <div class="service-info">
                                        <h4>Hospital Admission</h4>
                                        <p>
                                            <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($admission['admission_date'])); ?> • 
                                            <i class="fas fa-bed"></i> <?php echo htmlspecialchars($admission['bed_number'] ?? 'Unknown'); ?> • 
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($admission['ward_name'] ?? 'Unknown'); ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $admission['status']; ?>">
                                    <?php echo ucfirst($admission['status']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($patient_appointments) === 0 && count($patient_lab_orders) === 0 && count($patient_admissions) === 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Service History</h3>
                            <p>No appointments, lab orders, or admissions found.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Sidebar -->
            <div>
                <!-- Outstanding Bills -->
                <?php if (count($outstanding_bills) > 0): ?>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-exclamation-circle"></i>
                            Outstanding Bills (Hospital & Pharmacy)
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="outstanding-list">
                            <?php foreach ($outstanding_bills as $bill): ?>
                            <div class="outstanding-item">
                                <div class="bill-info">
                                    <h4>
                                        <?php echo htmlspecialchars($bill['bill_number']); ?>
                                        <span class="source-badge <?php echo $bill['source']; ?>" style="margin-left: 0.5rem;">
                                            <?php echo $bill['source']; ?>
                                        </span>
                                    </h4>
                                    <p><?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></p>
                                </div>
                                <div class="bill-amount">
                                    <div style="color: #dc2626; font-weight: 600;">
                                        Ksh <?php echo number_format($bill['balance_amount'], 2); ?>
                                    </div>
                                    <a href="unified_payment.php?bill_id=<?php echo $bill['id']; ?>&patient_id=<?php echo $patient_id; ?>&source=<?php echo $bill['source']; ?>" 
                                       class="view-details" style="font-size: 0.7rem;">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Payment History -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-history"></i>
                            Payment History
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($payment_history) > 0): ?>
                        <div class="payment-list">
                            <?php foreach ($payment_history as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-info">
                                    <h4>
                                        <i class="fas fa-receipt"></i> 
                                        Ksh <?php echo number_format($payment['total_amount'], 2); ?>
                                    </h4>
                                    <p>
                                        <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                        <span class="payment-method">
                                            • <?php echo ucfirst($payment['payment_method']); ?>
                                            <?php if ($payment['received_by_first']): ?>
                                            • by <?php echo htmlspecialchars($payment['received_by_first'] . ' ' . $payment['received_by_last']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </p>
                                </div>
                                <span class="status-badge status-paid">Paid</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Payments</h3>
                            <p>No payment history found.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php include "includes/footer.php"; ?>