<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php"; // MySQLi connection for legacy tables

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Get today's stats
$today = date('Y-m-d');
$stats = [
    'today_revenue' => 0,
    'pharmacy_revenue' => 0,
    'hospital_revenue' => 0,
    'pending_bills' => 0,
    'total_outstanding' => 0,
    'department_revenue' => []
];

try {
    // Get unified billing revenue (hospital services)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as hospital_revenue 
        FROM billing 
        WHERE DATE(bill_date) = ?
    ");
    $stmt->execute([$today]);
    $stats['hospital_revenue'] = $stmt->fetchColumn();

    // Get pharmacy revenue from INTEGRATED system (UPDATED)
    $pharmacy_revenue = 0;
    mysqli_select_db($link, "hospital");
    $pharmacy_query = "
        SELECT COALESCE(SUM(pbd.total_amount), 0) as pharmacy_revenue
        FROM pharmacy_billing pb
        INNER JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE DATE(pb.date) = '$today'
    ";
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    if ($pharmacy_result) {
        $pharmacy_row = mysqli_fetch_assoc($pharmacy_result);
        $pharmacy_revenue = $pharmacy_row['pharmacy_revenue'] ?? 0;
    } else {
        // Fallback to old system if new one fails
        mysqli_select_db($link, "php_ims");
        $pharmacy_query_old = "
            SELECT COALESCE(SUM(bd.price * bd.quantity), 0) as pharmacy_revenue
            FROM billing b
            INNER JOIN billing_details bd ON b.id = bd.billing_id
            WHERE DATE(b.date) = '$today'
        ";
        $pharmacy_result_old = mysqli_query($link, $pharmacy_query_old);
        if ($pharmacy_result_old) {
            $pharmacy_row_old = mysqli_fetch_assoc($pharmacy_result_old);
            $pharmacy_revenue = $pharmacy_row_old['pharmacy_revenue'] ?? 0;
        }
    }
    $stats['pharmacy_revenue'] = $pharmacy_revenue;

    // Total revenue
    $stats['today_revenue'] = $stats['hospital_revenue'] + $stats['pharmacy_revenue'];

    // Pending bills from unified system
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_bills 
        FROM billing 
        WHERE balance_amount > 0
    ");
    $stmt->execute();
    $unified_pending = $stmt->fetchColumn();

    // Pending pharmacy bills (unpaid or partially paid) - UPDATED for integrated system
    $pharmacy_pending = 0;
    // Pharmacy bills in integrated system are typically paid immediately, so no pending
    // If you need to track pending pharmacy bills, you'd add logic here

    $stats['pending_bills'] = $unified_pending + $pharmacy_pending;

    // Total outstanding from unified system
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(balance_amount), 0) as total_outstanding 
        FROM billing 
        WHERE balance_amount > 0
    ");
    $stmt->execute();
    $stats['total_outstanding'] = $stmt->fetchColumn();

    // Department-wise revenue (unified system)
    $stmt = $pdo->prepare("
        SELECT 
            bi.department,
            bi.item_type,
            COUNT(DISTINCT b.id) as bill_count,
            SUM(bi.total_amount) as revenue,
            SUM(CASE WHEN b.balance_amount > 0 THEN bi.total_amount ELSE 0 END) as outstanding
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE DATE(b.bill_date) = ?
        GROUP BY bi.department, bi.item_type
        ORDER BY revenue DESC
    ");
    $stmt->execute([$today]);
    $dept_details = $stmt->fetchAll();
    
    // Aggregate by department
    $dept_summary = [];
    foreach ($dept_details as $dept) {
        $dept_name = $dept['department'] ?? 'Other';
        if (!isset($dept_summary[$dept_name])) {
            $dept_summary[$dept_name] = [
                'department' => $dept_name,
                'revenue' => 0,
                'outstanding' => 0,
                'bill_count' => 0
            ];
        }
        $dept_summary[$dept_name]['revenue'] += $dept['revenue'];
        $dept_summary[$dept_name]['outstanding'] += $dept['outstanding'];
        $dept_summary[$dept_name]['bill_count'] += $dept['bill_count'];
    }

    // Add pharmacy revenue to department summary (UPDATED)
    if ($stats['pharmacy_revenue'] > 0) {
        $pharmacy_count = 0;
        
        // Try new integrated system first
        mysqli_select_db($link, "hospital");
        $pharmacy_count_query = "
            SELECT COUNT(DISTINCT pb.id) as bill_count
            FROM pharmacy_billing pb
            INNER JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            WHERE DATE(pb.date) = '$today'
        ";
        $pharmacy_count_result = mysqli_query($link, $pharmacy_count_query);
        if ($pharmacy_count_result) {
            $pharmacy_count_row = mysqli_fetch_assoc($pharmacy_count_result);
            $pharmacy_count = $pharmacy_count_row['bill_count'] ?? 0;
        } else {
            // Fallback to old system
            mysqli_select_db($link, "php_ims");
            $pharmacy_count_query_old = "
                SELECT COUNT(DISTINCT b.id) as bill_count
                FROM billing b
                INNER JOIN billing_details bd ON b.id = bd.billing_id
                WHERE DATE(b.date) = '$today'
            ";
            $pharmacy_count_result_old = mysqli_query($link, $pharmacy_count_query_old);
            if ($pharmacy_count_result_old) {
                $pharmacy_count_row_old = mysqli_fetch_assoc($pharmacy_count_result_old);
                $pharmacy_count = $pharmacy_count_row_old['bill_count'] ?? 0;
            }
        }

        $dept_summary['Pharmacy'] = [
            'department' => 'Pharmacy',
            'revenue' => $stats['pharmacy_revenue'],
            'outstanding' => 0, // Pharmacy bills are typically paid immediately
            'bill_count' => $pharmacy_count
        ];
    }
    
    $stats['department_revenue'] = array_values($dept_summary);

} catch (PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
}

// Get recent bills - COMBINED from both systems
$recent_bills = [];
try {
    // Get unified system bills (hospital services) - KEEP THIS AS IS
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.bill_number,
            b.bill_date,
            b.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            b.total_amount,
            b.amount_paid,
            b.balance_amount,
            b.status,
            'hospital' as source,
            COUNT(DISTINCT bi.id) as item_count,
            GROUP_CONCAT(DISTINCT bi.item_type) as service_types
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        GROUP BY b.id
        ORDER BY b.bill_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $unified_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pharmacy bills from INTEGRATED system (UPDATED)
    mysqli_select_db($link, "hospital");
    $pharmacy_bills_query = "
        SELECT 
            pb.id,
            pb.bill_no as bill_number,
            pb.date as bill_date,
            pb.patient_id,
            pb.CustomerName as patient_name,
            COALESCE(p.patient_id, '') as patient_number,
            COALESCE(SUM(pbd.total_amount), 0) as total_amount,
            COALESCE(SUM(pbd.total_amount), 0) as amount_paid,
            0 as balance_amount,
            'paid' as status,
            'pharmacy' as source,
            COUNT(pbd.id) as item_count,
            'medication' as service_types
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        LEFT JOIN patients p ON pb.patient_id = p.id
        GROUP BY pb.id
        ORDER BY pb.date DESC
        LIMIT 5
    ";
    
    $pharmacy_bills_result = mysqli_query($link, $pharmacy_bills_query);
    $pharmacy_bills = [];
    if ($pharmacy_bills_result) {
        while ($row = mysqli_fetch_assoc($pharmacy_bills_result)) {
            $pharmacy_bills[] = $row;
        }
    } else {
        // Fallback to old pharmacy system if new one fails
        mysqli_select_db($link, "php_ims");
        $pharmacy_bills_query_old = "
            SELECT 
                b.id,
                b.bill_no as bill_number,
                b.date as bill_date,
                b.CustomerName as patient_name,
                '' as patient_number,
                COALESCE(SUM(bd.price * bd.quantity), 0) as total_amount,
                0 as amount_paid,
                0 as balance_amount,
                'paid' as status,
                'pharmacy' as source,
                COUNT(bd.id) as item_count,
                'medication' as service_types
            FROM billing b
            LEFT JOIN billing_details bd ON b.id = bd.billing_id
            GROUP BY b.id
            ORDER BY b.date DESC
            LIMIT 5
        ";
        $pharmacy_bills_result_old = mysqli_query($link, $pharmacy_bills_query_old);
        if ($pharmacy_bills_result_old) {
            while ($row = mysqli_fetch_assoc($pharmacy_bills_result_old)) {
                $pharmacy_bills[] = $row;
            }
        }
    }

    // Merge and sort by date (KEEP THIS AS IS)
    $recent_bills = array_merge($unified_bills, $pharmacy_bills);
    usort($recent_bills, function($a, $b) {
        return strtotime($b['bill_date']) - strtotime($a['bill_date']);
    });
    $recent_bills = array_slice($recent_bills, 0, 10);

} catch (PDOException $e) {
    error_log("Error fetching recent bills: " . $e->getMessage());
}

// Get outstanding bills by service type (unified system only)
$outstanding_by_service = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            bi.item_type,
            COUNT(DISTINCT b.id) as bill_count,
            SUM(bi.total_amount) as total_amount,
            SUM(b.balance_amount) as outstanding_amount
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE b.balance_amount > 0
        GROUP BY bi.item_type
        ORDER BY outstanding_amount DESC
    ");
    $stmt->execute();
    $outstanding_by_service = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching outstanding by service: " . $e->getMessage());
}

// Get patients with outstanding balances (unified system)
$patients_with_dues = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.phone,
            COUNT(b.id) as bill_count,
            SUM(b.balance_amount) as total_due
        FROM patients p
        INNER JOIN billing b ON p.id = b.patient_id
        WHERE b.balance_amount > 0
        GROUP BY p.id, p.patient_id, p.first_name, p.last_name, p.phone
        ORDER BY total_due DESC
        LIMIT 10
    ");
    $stmt->execute();
    $patients_with_dues = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patients with dues: " . $e->getMessage());
}

// Get top services/items by revenue - COMBINED
$top_items = [];
try {
    // Get from unified system (KEEP THIS AS IS)
    $stmt = $pdo->prepare("
        SELECT 
            bi.item_type,
            bi.item_description,
            'hospital' as source,
            COUNT(bi.id) as usage_count,
            SUM(bi.quantity) as total_quantity,
            SUM(bi.total_amount) as total_revenue
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE DATE(b.bill_date) = ?
        GROUP BY bi.item_type, bi.item_description
    ");
    $stmt->execute([$today]);
    $unified_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get from INTEGRATED pharmacy system (UPDATED)
    mysqli_select_db($link, "hospital");
    $pharmacy_items_query = "
        SELECT 
            'medication' as item_type,
            CONCAT(pbd.product_name, ' - ', pbd.product_company) as item_description,
            'pharmacy' as source,
            COUNT(*) as usage_count,
            SUM(pbd.quantity) as total_quantity,
            SUM(pbd.total_amount) as total_revenue
        FROM pharmacy_billing_details pbd
        INNER JOIN pharmacy_billing pb ON pbd.billing_id = pb.id
        WHERE DATE(pb.date) = '$today'
        GROUP BY pbd.product_name, pbd.product_company
    ";
    $pharmacy_items_result = mysqli_query($link, $pharmacy_items_query);
    $pharmacy_items = [];
    if ($pharmacy_items_result) {
        while ($row = mysqli_fetch_assoc($pharmacy_items_result)) {
            $pharmacy_items[] = $row;
        }
    } else {
        // Fallback to old pharmacy system
        mysqli_select_db($link, "php_ims");
        $pharmacy_items_query_old = "
            SELECT 
                'medication' as item_type,
                CONCAT(bd.product_name, ' - ', bd.product_company) as item_description,
                'pharmacy' as source,
                COUNT(*) as usage_count,
                SUM(bd.quantity) as total_quantity,
                SUM(bd.price * bd.quantity) as total_revenue
            FROM billing_details bd
            INNER JOIN billing b ON bd.billing_id = b.id
            WHERE DATE(b.date) = '$today'
            GROUP BY bd.product_name, bd.product_company
        ";
        $pharmacy_items_result_old = mysqli_query($link, $pharmacy_items_query_old);
        if ($pharmacy_items_result_old) {
            while ($row = mysqli_fetch_assoc($pharmacy_items_result_old)) {
                $pharmacy_items[] = $row;
            }
        }
    }

    // Merge and sort by revenue (KEEP THIS AS IS)
    $top_items = array_merge($unified_items, $pharmacy_items);
    usort($top_items, function($a, $b) {
        return $b['total_revenue'] - $a['total_revenue'];
    });
    $top_items = array_slice($top_items, 0, 5);

} catch (PDOException $e) {
    error_log("Error fetching top items: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidated Billing & Finance - AuraCare HMS</title>
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
            justify-content: between;
            align-items: center;
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
        
        .page-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.revenue { background: #dcfce7; color: #166534; }
        .stat-icon.pending { background: #fef3c7; color: #d97706; }
        .stat-icon.overdue { background: #fee2e2; color: #dc2626; }
        .stat-icon.department { background: #e0e7ff; color: #4338ca; }
        .stat-icon.pharmacy { background: #dbeafe; color: #1e40af; }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
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
        
        .bill-list, .item-list, .patient-list, .service-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .bill-item, .item-row, .patient-item, .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .bill-item:hover, .item-row:hover, .patient-item:hover, .service-item:hover {
            background: #f9fafb;
            border-color: #3b82f6;
        }
        
        .patient-info h4, .item-info h4, .service-info h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
        }
        
        .patient-info p, .item-info p, .service-info p {
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
        
        .outstanding-amount {
            font-size: 0.75rem;
            color: #dc2626;
            font-weight: 600;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #374151;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            transform: translateX(4px);
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .action-icon.pharmacy { background: #dcfce7; color: #166534; }
        .action-icon.bills { background: #fef3c7; color: #d97706; }
        .action-icon.reports { background: #e0e7ff; color: #4338ca; }
        .action-icon.patients { background: #dbeafe; color: #1e40af; }
        .action-icon.payment { background: #10b981; color: white; }
        
        .action-text h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .action-text p {
            margin: 0.25rem 0 0 0;
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #dcfce7; color: #166534; }
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
        
        .department-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #3b82f6;
            transition: transform 0.2s;
        }
        
        .department-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .department-card h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
        }
        
        .department-card .revenue {
            font-size: 1.5rem;
            font-weight: 700;
            color: #059669;
            margin: 0.5rem 0;
        }
        
        .department-card .outstanding {
            font-size: 0.875rem;
            color: #dc2626;
            margin: 0.25rem 0;
        }
        
        .department-card .bill-count {
            font-size: 0.875rem;
            color: #6b7280;
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
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
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
        
        .outstanding-section {
            margin-bottom: 2rem;
        }
        
        .outstanding-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid,
            .outstanding-grid {
                grid-template-columns: 1fr;
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
                    <i class="fas fa-money-bill-wave"></i>
                    Consolidated Billing Dashboard
                </h1>
                <p class="page-subtitle">Unified billing for all hospital services including Pharmacy Sales</p>
            </div>
            <div style="color: #6b7280; font-size: 0.875rem;">
                <i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['today_revenue'], 2); ?></h3>
                    <p>Today's Total Revenue</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pharmacy">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['pharmacy_revenue'], 2); ?></h3>
                    <p>Pharmacy Sales Today</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_bills']; ?></h3>
                    <p>Pending Bills</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon overdue">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['total_outstanding'], 2); ?></h3>
                    <p>Total Outstanding</p>
                </div>
            </div>
        </div>

        <!-- Outstanding Dues Section -->
        <?php if (count($patients_with_dues) > 0 || count($outstanding_by_service) > 0): ?>
        <div class="outstanding-section">
            <div class="outstanding-grid">
                <!-- Patients with Dues -->
                <?php if (count($patients_with_dues) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-users"></i>
                            Patients with Outstanding Dues
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="patient-list">
                            <?php foreach ($patients_with_dues as $patient): ?>
                            <div class="patient-item">
                                <div class="patient-info">
                                    <h4><i class="fas fa-user"></i> <?php echo htmlspecialchars($patient['patient_name']); ?></h4>
                                    <p>
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?> • 
                                        <i class="fas fa-file-invoice"></i> <?php echo $patient['bill_count']; ?> bills
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div class="outstanding-amount">
                                        Ksh <?php echo number_format($patient['total_due'], 2); ?>
                                    </div>
                                    <div style="margin-top: 0.5rem;">
                                        <a href="patient_billing.php?id=<?php echo $patient['id']; ?>" class="view-details">
                                            <i class="fas fa-eye"></i> View Bills
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Outstanding by Service Type -->
                <?php if (count($outstanding_by_service) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Outstanding by Service Type
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="service-list">
                            <?php foreach ($outstanding_by_service as $service): ?>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4><i class="fas fa-box"></i> <?php echo ucfirst(str_replace('_', ' ', $service['item_type'])); ?></h4>
                                    <p>
                                        <i class="fas fa-file-invoice"></i> <?php echo $service['bill_count']; ?> bills • 
                                        <i class="fas fa-money-bill"></i> Ksh <?php echo number_format($service['total_amount'], 2); ?> total
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div class="outstanding-amount">
                                        Ksh <?php echo number_format($service['outstanding_amount'], 2); ?>
                                    </div>
                                    <div class="bill-count" style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                        Outstanding
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Department Revenue Section -->
        <?php if (count($stats['department_revenue']) > 0): ?>
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    Department Revenue Today (Including Pharmacy)
                </h2>
            </div>
            <div class="card-body">
                                <div class="department-grid">
                    <?php foreach ($stats['department_revenue'] as $dept): ?>
                    <div class="department-card" style="border-left-color: <?php 
                        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                        echo $colors[array_rand($colors)];
                    ?>;">
                        <h4>
                            <i class="fas fa-<?php 
                                switch($dept['department']) {
                                    case 'Pharmacy': echo 'pills'; break;
                                    case 'Laboratory': echo 'flask'; break;
                                    case 'Radiology': echo 'x-ray'; break;
                                    case 'Consultation': echo 'stethoscope'; break;
                                    case 'Procedure': echo 'procedures'; break;
                                    default: echo 'hospital';
                                }
                            ?>"></i>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </h4>
                        <div class="revenue">
                            Ksh <?php echo number_format($dept['revenue'], 2); ?>
                        </div>
                        <?php if ($dept['outstanding'] > 0): ?>
                        <div class="outstanding">
                            <i class="fas fa-exclamation-circle"></i>
                            Ksh <?php echo number_format($dept['outstanding'], 2); ?> outstanding
                        </div>
                        <?php endif; ?>
                        <div class="bill-count">
                            <i class="fas fa-file-invoice"></i>
                            <?php echo $dept['bill_count']; ?> bills today
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Recent Bills & Top Items -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <!-- Recent Bills -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-clock"></i>
                            Recent Bills (Hospital & Pharmacy)
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_bills) > 0): ?>
                        <div class="bill-list">
                            <?php foreach ($recent_bills as $bill): ?>
                            <div class="bill-item">
                                <div class="patient-info">
                                    <h4>
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($bill['patient_name']); ?>
                                        <span class="source-badge <?php echo $bill['source']; ?>">
                                            <?php echo $bill['source']; ?>
                                        </span>
                                    </h4>
                                    <p>
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($bill['bill_number']); ?> • 
                                        <i class="far fa-calendar"></i> <?php echo date('M j, g:i A', strtotime($bill['bill_date'])); ?>
                                        <?php if ($bill['patient_number']): ?>
                                        • <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($bill['patient_number']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($bill['service_types']): ?>
                                    <div class="service-types">
                                        <?php 
                                        $services = explode(',', $bill['service_types']);
                                        foreach (array_slice($services, 0, 3) as $service):
                                        ?>
                                        <span class="service-tag"><?php echo htmlspecialchars(trim($service)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($services) > 3): ?>
                                        <span class="service-tag">+<?php echo count($services) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="bill-details">
                                    <div class="bill-amount">
                                        <div>Ksh <?php echo number_format($bill['total_amount'], 2); ?></div>
                                        <?php if ($bill['balance_amount'] > 0): ?>
                                        <div class="bill-balance">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Ksh <?php echo number_format($bill['balance_amount'], 2); ?> due
                                        </div>
                                        <?php endif; ?>
                                        <div style="margin-top: 0.5rem;">
                                            <span class="status-badge status-<?php echo $bill['status']; ?>">
                                                <?php echo ucfirst($bill['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="<?php echo $bill['source'] === 'pharmacy' ? 'pharmacy_billing_details.php?id=' . $bill['id'] : 'billing_details.php?id=' . $bill['id']; ?>" 
                                       class="view-details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h3>No Bills Today</h3>
                            <p>No bills have been generated today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Services/Items -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Top Services & Items Today
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($top_items) > 0): ?>
                        <div class="item-list">
                            <?php foreach ($top_items as $item): ?>
                            <div class="item-row">
                                <div class="item-info">
                                    <h4>
                                        <?php echo htmlspecialchars($item['item_description']); ?>
                                        <span class="source-badge <?php echo $item['source']; ?>">
                                            <?php echo $item['source']; ?>
                                        </span>
                                    </h4>
                                    <p>
                                        <i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $item['item_type'])); ?> • 
                                        <i class="fas fa-chart-bar"></i> <?php echo $item['usage_count']; ?> sales • 
                                        <i class="fas fa-box"></i> <?php echo $item['total_quantity']; ?> units
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div style="font-weight: 600; color: #059669;">
                                        Ksh <?php echo number_format($item['total_revenue'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h3>No Sales Data</h3>
                            <p>No service or item sales recorded today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="create_bill.php" class="action-btn">
                            <div class="action-icon bills">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="action-text">
                                <h4>Create New Bill</h4>
                                <p>Generate hospital service bill</p>
                            </div>
                            <i class="fas fa-chevron-right" style="margin-left: auto; color: #9ca3af;"></i>
                        </a>
                        
                        <a href="pharmacy_billing.php" class="action-btn">
                            <div class="action-icon pharmacy">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="action-text">
                                <h4>Pharmacy Sales</h4>
                                <p>Process medication sales</p>
                            </div>
                            <i class="fas fa-chevron-right" style="margin-left: auto; color: #9ca3af;"></i>
                        </a>
                        
                        <a href="billing_reports.php" class="action-btn">
                            <div class="action-icon reports">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="action-text">
                                <h4>View Reports</h4>
                                <p>Financial analytics & insights</p>
                            </div>
                            <i class="fas fa-chevron-right" style="margin-left: auto; color: #9ca3af;"></i>
                        </a>
                        
                        <a href="patients.php" class="action-btn">
                            <div class="action-icon patients">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="action-text">
                                <h4>Manage Patients</h4>
                                <p>Patient records & billing history</p>
                            </div>
                            <i class="fas fa-chevron-right" style="margin-left: auto; color: #9ca3af;"></i>
                        </a>
                        
                        <a href="pending_payments.php" class="action-btn">
                            <div class="action-icon payment">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="action-text">
                                <h4>Process Payments</h4>
                                <p>Collect outstanding payments</p>
                            </div>
                            <i class="fas fa-chevron-right" style="margin-left: auto; color: #9ca3af;"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
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
