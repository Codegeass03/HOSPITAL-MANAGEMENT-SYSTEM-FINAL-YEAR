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

date_default_timezone_set('Africa/Nairobi');

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $bill_id = $_POST['bill_id'] ?? null;
        $payment_amount = floatval($_POST['payment_amount']);
        $payment_method = $_POST['payment_method'];
        $patient_id = $_POST['patient_id'] ?? null;
        
        // Validate input
        if ($payment_amount <= 0) {
            throw new Exception("Invalid payment amount");
        }
        
        if ($bill_id) {
            // Process hospital bill payment
            $stmt = $pdo->prepare("SELECT paid_amount, balance_amount FROM billing WHERE id = ?");
            $stmt->execute([$bill_id]);
            $bill = $stmt->fetch();
            
            if ($bill) {
                $new_paid = $bill['paid_amount'] + $payment_amount;
                $new_balance = $bill['balance_amount'] - $payment_amount;
                $status = $new_balance > 0 ? 'pending' : 'paid';
                
                // Update bill
                $stmt = $pdo->prepare("
                    UPDATE billing 
                    SET paid_amount = ?, balance_amount = ?, status = ?, payment_method = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_paid, $new_balance, $status, $payment_method, $bill_id]);
                
                // Record in payments table for unified tracking
                $payment_number = 'PYMT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        patient_id, payment_number, total_amount, 
                        payment_method, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, 'completed', ?, NOW())
                ");
                $stmt->execute([
                    $patient_id, 
                    $payment_number, 
                    $payment_amount, 
                    $payment_method, 
                    $_SESSION['user_id'] ?? $_SESSION['username']
                ]);
                $payment_id = $pdo->lastInsertId();
                
                // Record allocation
                $stmt = $pdo->prepare("
                    INSERT INTO payment_allocations (
                        payment_id, service_type, reference_id, allocated_amount
                    ) VALUES (?, 'general', ?, ?)
                ");
                $stmt->execute([$payment_id, $bill_id, $payment_amount]);
                
                $_SESSION['success'] = "Payment of Ksh " . number_format($payment_amount, 2) . " processed successfully!";
            } else {
                throw new Exception("Bill not found");
            }
        } else {
            throw new Exception("No bill specified");
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    }
    
    header("Location: pending_payments.php");
    exit();
}

// Get all pending bills with detailed information
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        p.first_name,
        p.last_name,
        p.patient_id as patient_number,
        p.phone,
        p.email,
        DATEDIFF(CURDATE(), b.bill_date) as days_pending,
        (
            SELECT GROUP_CONCAT(DISTINCT CONCAT(bi.item_description, ' (', bi.item_type, ')') ORDER BY bi.id SEPARATOR '; ')
            FROM bill_items bi
            WHERE bi.bill_id = b.id
        ) as item_details,
        (
            SELECT GROUP_CONCAT(DISTINCT bi.item_type ORDER BY bi.item_type SEPARATOR ', ')
            FROM bill_items bi
            WHERE bi.bill_id = b.id
        ) as service_types
    FROM billing b
    INNER JOIN patients p ON b.patient_id = p.id
    WHERE b.balance_amount > 0
    ORDER BY b.balance_amount DESC, b.bill_date ASC
");
$stmt->execute();
$pending_bills = $stmt->fetchAll();

// Get lab orders with outstanding balances
$stmt = $pdo->prepare("
    SELECT 
        lo.*,
        p.first_name,
        p.last_name,
        p.patient_id as patient_number,
        p.phone,
        p.email,
        DATEDIFF(CURDATE(), lo.order_date) as days_pending,
        'lab' as bill_type,
        lo.order_number as bill_number,
        lo.order_date as bill_date,
        lo.total_amount,
        lo.paid_amount,
        (lo.total_amount - lo.paid_amount) as balance_amount,
        lo.payment_status as status,
        (
            SELECT GROUP_CONCAT(DISTINCT loi.test_name ORDER BY loi.id SEPARATOR '; ')
            FROM lab_order_items loi
            WHERE loi.lab_order_id = lo.id
        ) as item_details,
        'lab_test' as service_types
    FROM lab_orders lo
    INNER JOIN patients p ON lo.patient_id = p.id
    WHERE lo.payment_status IN ('unpaid', 'partial')
    AND (lo.total_amount - lo.paid_amount) > 0
    ORDER BY (lo.total_amount - lo.paid_amount) DESC, lo.order_date ASC
");
$stmt->execute();
$pending_lab_orders = $stmt->fetchAll();

// Get pharmacy bills with outstanding balances
mysqli_select_db($link, "hospital");
$check_columns = "SHOW COLUMNS FROM pharmacy_billing LIKE 'balance_amount'";
$column_result = mysqli_query($link, $check_columns);

if (mysqli_num_rows($column_result) > 0) {
    $pharmacy_query = "
        SELECT 
            pb.*,
            p.first_name,
            p.last_name,
            p.patient_id as patient_number,
            p.phone,
            p.email,
            DATEDIFF(CURDATE(), pb.date) as days_pending,
            'pharmacy' as bill_type,
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
            (
                SELECT GROUP_CONCAT(DISTINCT pbd.product_name ORDER BY pbd.id SEPARATOR '; ')
                FROM pharmacy_billing_details pbd
                WHERE pbd.billing_id = pb.id
            ) as item_details,
            'pharmacy' as service_types
        FROM pharmacy_billing pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        WHERE pb.balance_amount > 0
        ORDER BY pb.balance_amount DESC, pb.date ASC
    ";
} else {
    $pharmacy_query = "
        SELECT 
            pb.*,
            p.first_name,
            p.last_name,
            p.patient_id as patient_number,
            p.phone,
            p.email,
            DATEDIFF(CURDATE(), pb.date) as days_pending,
            'pharmacy' as bill_type,
            pb.bill_no as bill_number,
            pb.date as bill_date,
            pb.total_amount,
            pb.paid_amount,
            (pb.total_amount - pb.paid_amount) as balance_amount,
            CASE 
                WHEN (pb.total_amount - pb.paid_amount) > 0 AND pb.paid_amount > 0 THEN 'partial'
                WHEN (pb.total_amount - pb.paid_amount) > 0 THEN 'pending'
                ELSE 'paid'
            END as status,
            (
                SELECT GROUP_CONCAT(DISTINCT pbd.product_name ORDER BY pbd.id SEPARATOR '; ')
                FROM pharmacy_billing_details pbd
                WHERE pbd.billing_id = pb.id
            ) as item_details,
            'pharmacy' as service_types
        FROM pharmacy_billing pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        WHERE (pb.total_amount - pb.paid_amount) > 0
        ORDER BY (pb.total_amount - pb.paid_amount) DESC, pb.date ASC
    ";
}

$pharmacy_result = mysqli_query($link, $pharmacy_query);
$pending_pharmacy_bills = [];
if ($pharmacy_result) {
    while ($row = mysqli_fetch_assoc($pharmacy_result)) {
        $pending_pharmacy_bills[] = $row;
    }
}

// Combine all pending bills
$all_pending_bills = array_merge($pending_bills, $pending_lab_orders, $pending_pharmacy_bills);

// Sort by balance amount (descending)
usort($all_pending_bills, function($a, $b) {
    return $b['balance_amount'] <=> $a['balance_amount'];
});

// Calculate totals
$total_outstanding = 0;
$total_bills = count($all_pending_bills);
$total_patients = count(array_unique(array_column($all_pending_bills, 'patient_id')));

foreach ($all_pending_bills as $bill) {
    $total_outstanding += $bill['balance_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0891b2;
            --primary-light: #06b6d4;
            --primary-dark: #0e7490;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #134e4a;
            --light: #f0fdfa;
            --gray: #64748b;
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
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .page-header h1 {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            font-size: 1.875rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            margin: 0;
            color: var(--gray);
            font-size: 1rem;
        }
        
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(6, 182, 212, 0.1);
        }
        
        .stat-card:nth-child(1) { border-left-color: var(--danger); }
        .stat-card:nth-child(2) { border-left-color: var(--warning); }
        .stat-card:nth-child(3) { border-left-color: var(--success); }
        
        .stat-card h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-card p {
            margin: 0.5rem 0 0 0;
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        /* Bills Table */
        .bills-table {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Table Styling */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
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
        
        .status-partial { 
            background: #dbeafe; 
            color: #1d4ed8; 
        }
        
        .status-overdue { 
            background: #fee2e2; 
            color: #dc2626; 
        }
        
        /* Service Badges */
        .service-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin: 0.25rem 0.25rem 0 0;
        }
        
        .service-badge.hospital { background: #dbeafe; color: var(--primary); }
        .service-badge.lab { background: #fef3c7; color: var(--warning); }
        .service-badge.pharmacy { background: #f3e8ff; color: #8b5cf6; }
        .service-badge.consultation { background: #dcfce7; color: var(--success); }
        
        /* Buttons */
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
            gap: 0.25rem;
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
        
        .btn-success:hover {
            background: #059669;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }
        
        .close:hover {
            background: #f1f5f9;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }
        
        .payment-summary {
            background: #f0f9ff;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            border-left: 4px solid var(--primary);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid;
        }
        
        .alert-success {
            background: #dcfce7;
            color: var(--success);
            border-color: #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: var(--danger);
            border-color: #fecaca;
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
        
        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.875rem;
        }
        
        /* Patient Info */
        .patient-info {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        .patient-info span {
            display: block;
            margin-top: 0.125rem;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        /* Days Overdue */
        .days-pending {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .days-normal { background: #f0f9ff; color: var(--primary); }
        .days-warning { background: #fef3c7; color: #d97706; }
        .days-danger { background: #fee2e2; color: #dc2626; }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1><i class="fas fa-credit-card"></i> Pending Payments Management</h1>
            <p>Track and manage all outstanding payments across all services</p>
        </div>
        
        <!-- Stats Summary -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $total_bills; ?></h3>
                <p>Total Pending Bills</p>
            </div>
            <div class="stat-card">
                <h3>Ksh <?php echo number_format($total_outstanding, 2); ?></h3>
                <p>Total Outstanding Amount</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_patients; ?></h3>
                <p>Patients with Dues</p>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: var(--dark);">
                <i class="fas fa-filter"></i> Filter Bills
            </h3>
            <div class="filter-grid">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--gray);">
                        Service Type
                    </label>
                    <select class="form-control" onchange="filterByService(this.value)">
                        <option value="">All Services</option>
                        <option value="hospital">Hospital Bills</option>
                        <option value="lab">Lab Orders</option>
                        <option value="pharmacy">Pharmacy Bills</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--gray);">
                        Status
                    </label>
                    <select class="form-control" onchange="filterByStatus(this.value)">
                        <option value="">All Status</option>
                        <option value="pending">Unpaid</option>
                        <option value="partial">Partially Paid</option>
                        <option value="overdue">Overdue (30+ days)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Bills Table -->
        <div class="bills-table">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Outstanding Bills Details</h2>
                <div style="font-size: 0.875rem; color: var(--gray);">
                    Last updated: <?php echo date('g:i A'); ?>
                </div>
            </div>
            
            <?php if (count($all_pending_bills) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Bill Details</th>
                            <th>Patient Information</th>
                            <th>Amount Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="billsTableBody">
                        <?php foreach ($all_pending_bills as $bill): 
                            $is_overdue = isset($bill['days_pending']) && $bill['days_pending'] > 30;
                            $status_class = $is_overdue ? 'status-overdue' : ($bill['paid_amount'] > 0 ? 'status-partial' : 'status-pending');
                            $status_text = $is_overdue ? 'Overdue' : ($bill['paid_amount'] > 0 ? 'Partial' : 'Pending');
                            
                            // Determine service type
                            $service_type = $bill['bill_type'] ?? 'hospital';
                        ?>
                        <tr class="bill-row" 
                            data-service="<?php echo $service_type; ?>"
                            data-status="<?php echo $bill['paid_amount'] > 0 ? 'partial' : 'pending'; ?>"
                            data-days="<?php echo $bill['days_pending'] ?? 0; ?>">
                            <td>
                                <div style="font-weight: 600; color: var(--dark);">
                                    <?php echo htmlspecialchars($bill['bill_number']); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--gray); margin-top: 0.25rem;">
                                    <i class="far fa-calendar"></i> 
                                    <?php echo date('M j, Y', strtotime($bill['bill_date'])); ?>
                                </div>
                                
                                <!-- Service Type Badge -->
                                <div style="margin-top: 0.5rem;">
                                    <span class="service-badge <?php echo $service_type; ?>">
                                        <?php echo ucfirst($service_type); ?>
                                    </span>
                                </div>
                                
                                <!-- Item Details -->
                                <?php if (!empty($bill['item_details'])): ?>
                                <div style="font-size: 0.7rem; color: var(--gray); margin-top: 0.5rem;">
                                    <i class="fas fa-info-circle"></i>
                                    <?php 
                                    $items = explode('; ', $bill['item_details']);
                                    echo htmlspecialchars(implode(', ', array_slice($items, 0, 2)));
                                    if (count($items) > 2) echo '...';
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Days Pending -->
                                <?php if (isset($bill['days_pending']) && $bill['days_pending'] > 0): ?>
                                <div style="margin-top: 0.5rem;">
                                    <span class="days-pending <?php echo $is_overdue ? 'days-danger' : ($bill['days_pending'] > 15 ? 'days-warning' : 'days-normal'); ?>">
                                        <i class="far fa-clock"></i> <?php echo $bill['days_pending']; ?> days
                                    </span>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div style="font-weight: 600; color: var(--dark);">
                                    <?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?>
                                </div>
                                <div class="patient-info">
                                    <?php if (!empty($bill['patient_number'])): ?>
                                    <span>
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($bill['patient_number']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($bill['phone'])): ?>
                                    <span>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($bill['phone']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($bill['email'])): ?>
                                    <span>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($bill['email']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div>
                                    <div style="font-weight: 600; color: var(--dark);">
                                        Total: Ksh <?php echo number_format($bill['total_amount'], 2); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--success); margin-top: 0.25rem;">
                                        Paid: Ksh <?php echo number_format($bill['paid_amount'], 2); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--danger); font-weight: 600; margin-top: 0.25rem;">
                                        Balance: Ksh <?php echo number_format($bill['balance_amount'], 2); ?>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="action-buttons">
                                    <?php if ($bill['balance_amount'] > 0): ?>
                                    <button class="btn btn-primary" 
                                            onclick="openPaymentModal(
                                                <?php echo $bill['id']; ?>,
                                                <?php echo $bill['balance_amount']; ?>,
                                                '<?php echo addslashes($bill['bill_number']); ?>',
                                                <?php echo $bill['patient_id']; ?>,
                                                '<?php echo addslashes($bill['first_name'] . ' ' . $bill['last_name']); ?>'
                                            )">
                                        <i class="fas fa-credit-card"></i> Pay
                                    </button>
                                    <?php endif; ?>
                                    <a href="patient_profile.php?id=<?php echo $bill['patient_id']; ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Outstanding Payments</h3>
                <p>All bills have been fully paid.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Process Payment</h3>
                <button class="close" onclick="closePaymentModal()">&times;</button>
            </div>
            <form method="POST" id="paymentForm">
                <input type="hidden" name="bill_id" id="modal_bill_id">
                <input type="hidden" name="patient_id" id="modal_patient_id">
                
                <div class="payment-summary">
                    <div class="summary-row">
                        <span>Patient:</span>
                        <strong id="modal_patient_name"></strong>
                    </div>
                    <div class="summary-row">
                        <span>Bill Number:</span>
                        <strong id="modal_bill_number"></strong>
                    </div>
                    <div class="summary-row">
                        <span>Outstanding Balance:</span>
                        <strong id="modal_balance">Ksh 0.00</strong>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_amount">Payment Amount (Ksh)</label>
                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                           step="0.01" min="0.01" required oninput="validatePayment()"
                           placeholder="Enter payment amount">
                    <small style="color: var(--gray); font-size: 0.75rem;">
                        Maximum: <span id="max_amount">Ksh 0.00</span>
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method" required>
                        <option value="">Select Payment Method</option>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="insurance">Insurance</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_reference">Reference/Receipt Number (Optional)</label>
                    <input type="text" class="form-control" id="payment_reference" name="payment_reference"
                           placeholder="e.g., M-Pesa confirmation code">
                </div>
                
                <div class="payment-summary" id="payment_summary" style="display: none;">
                    <div class="summary-row">
                        <span>Payment Amount:</span>
                        <strong id="payment_amount_display">Ksh 0.00</strong>
                    </div>
                    <div class="summary-row">
                        <span>New Balance:</span>
                        <strong id="new_balance">Ksh 0.00</strong>
                    </div>
                    <div class="summary-row">
                        <span>Payment Status:</span>
                        <strong id="payment_status">Pending</strong>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" name="process_payment" style="flex: 2;">
                        <i class="fas fa-check"></i> Process Payment
                    </button>
                    <button type="button" class="btn btn-success" onclick="closePaymentModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter functions
        function filterByService(service) {
            const rows = document.querySelectorAll('.bill-row');
            rows.forEach(row => {
                if (!service || row.dataset.service === service) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function filterByStatus(status) {
            const rows = document.querySelectorAll('.bill-row');
            rows.forEach(row => {
                if (!status) {
                    row.style.display = '';
                } else if (status === 'overdue') {
                    if (parseInt(row.dataset.days) > 30) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                } else {
                    if (row.dataset.status === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
        
        // Payment Modal Functions
        function openPaymentModal(billId, balance, billNumber, patientId, patientName) {
            document.getElementById('modal_bill_id').value = billId;
            document.getElementById('modal_patient_id').value = patientId;
            document.getElementById('modal_patient_name').textContent = patientName;
            document.getElementById('modal_balance').textContent = 'Ksh ' + balance.toLocaleString();
            document.getElementById('modal_bill_number').textContent = billNumber;
            document.getElementById('max_amount').textContent = 'Ksh ' + balance.toLocaleString();
            
            const paymentAmount = document.getElementById('payment_amount');
            paymentAmount.max = balance;
            paymentAmount.value = balance;
            
            document.getElementById('paymentModal').style.display = 'flex';
            validatePayment();
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('paymentForm').reset();
            document.getElementById('payment_summary').style.display = 'none';
        }
        
        function validatePayment() {
            const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const currentBalance = parseFloat(document.getElementById('modal_balance').textContent.replace('Ksh ', '').replace(/,/g, ''));
            
            if (paymentAmount > 0 && paymentAmount <= currentBalance) {
                const newBalance = currentBalance - paymentAmount;
                
                document.getElementById('payment_amount_display').textContent = 'Ksh ' + paymentAmount.toLocaleString();
                document.getElementById('new_balance').textContent = 'Ksh ' + newBalance.toLocaleString();
                document.getElementById('payment_status').textContent = newBalance > 0 ? 'Partially Paid' : 'Fully Paid';
                document.getElementById('payment_summary').style.display = 'block';
                
                // Enable/disable submit button
                document.querySelector('button[name="process_payment"]').disabled = paymentAmount <= 0;
            } else {
                document.getElementById('payment_summary').style.display = 'none';
                document.querySelector('button[name="process_payment"]').disabled = true;
            }
        }
        
        // Form submission validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const currentBalance = parseFloat(document.getElementById('modal_balance').textContent.replace('Ksh ', '').replace(/,/g, ''));
            
            if (paymentAmount <= 0) {
                e.preventDefault();
                alert('Please enter a valid payment amount.');
                return false;
            }
            
            if (paymentAmount > currentBalance) {
                e.preventDefault();
                alert('Payment amount cannot exceed outstanding balance.');
                return false;
            }
            
            if (!document.getElementById('payment_method').value) {
                e.preventDefault();
                alert('Please select a payment method.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[name="process_payment"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        });
        
        // Auto-refresh pending bills every 30 seconds
let refreshTimer;
function startAutoRefresh() {
    refreshTimer = setInterval(() => {
        fetch('api/get_pending_bills.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updatePendingBillsTable(data.pending_bills);
                }
            })
            .catch(error => console.error('Error refreshing:', error));
    }, 30000); // 30 seconds
}

function updatePendingBillsTable(bills) {
    // Update the table with fresh data
    // This is a simplified version - you'd implement based on your UI
    console.log('Pending bills updated:', bills);
}

// Start auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Stop auto-refresh when leaving page
window.addEventListener('beforeunload', function() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>