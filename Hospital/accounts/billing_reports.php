<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initialize all variables with default values
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'daily_summary';
$department = $_GET['department'] ?? 'all';

// Initialize arrays with empty defaults
$hospital_daily = $hospital_payments = $hospital_departments = $hospital_services = [];
$pharmacy_daily = $pharmacy_payments = $pharmacy_items = [];
$combined_totals = [
    'total_revenue' => 0,
    'total_bills' => 0,
    'hospital_revenue' => 0,
    'pharmacy_revenue' => 0,
    'hospital_bills' => 0,
    'pharmacy_bills' => 0
];

try {
    // HOSPITAL SERVICES REPORTS
    if (in_array($report_type, ['daily_summary', 'revenue_breakdown', 'all'])) {
        // Daily Revenue Summary
        $stmt = $pdo->prepare("
            SELECT 
                DATE(bill_date) as date,
                COUNT(*) as bill_count,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as paid_amount,
                COALESCE(SUM(balance_amount), 0) as balance_amount,
                COALESCE(AVG(total_amount), 0) as avg_bill_amount
            FROM billing 
            WHERE bill_date BETWEEN ? AND ?
            GROUP BY DATE(bill_date)
            ORDER BY date DESC
        ");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $hospital_daily = $stmt->fetchAll();

        // Payment Method Analysis
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(payment_method, 'cash') as payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(paid_amount), 0) as total_amount,
                COALESCE(AVG(paid_amount), 0) as avg_amount
            FROM billing 
            WHERE bill_date BETWEEN ? AND ?
            GROUP BY COALESCE(payment_method, 'cash')
            ORDER BY total_amount DESC
        ");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $hospital_payments = $stmt->fetchAll();

        // Department-wise Revenue
        $dept_query = "
            SELECT 
                COALESCE(bi.department, 'General') as department,
                COUNT(DISTINCT b.id) as bill_count,
                COALESCE(SUM(bi.total_amount), 0) as total_revenue,
                COALESCE(AVG(bi.total_amount), 0) as avg_revenue
            FROM bill_items bi
            INNER JOIN billing b ON bi.bill_id = b.id
            WHERE b.bill_date BETWEEN ? AND ?
        ";
        
        if ($department !== 'all') {
            $dept_query .= " AND COALESCE(bi.department, 'General') = ?";
            $stmt = $pdo->prepare($dept_query);
            $stmt->execute([$start_date, $end_date . ' 23:59:59', $department]);
        } else {
            $dept_query .= " GROUP BY COALESCE(bi.department, 'General') ORDER BY total_revenue DESC";
            $stmt = $pdo->prepare($dept_query);
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        }
        $hospital_departments = $stmt->fetchAll();

        // Top Services
        $stmt = $pdo->prepare("
            SELECT 
                bi.item_description,
                COUNT(*) as service_count,
                COALESCE(SUM(bi.quantity), 0) as total_quantity,
                COALESCE(SUM(bi.total_amount), 0) as total_revenue,
                COALESCE(AVG(bi.total_amount), 0) as avg_revenue
            FROM bill_items bi
            INNER JOIN billing b ON bi.bill_id = b.id
            WHERE b.bill_date BETWEEN ? AND ?
            GROUP BY bi.item_description
            ORDER BY total_revenue DESC
            LIMIT 10
        ");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $hospital_services = $stmt->fetchAll();
    }

    // PHARMACY REPORTS
    mysqli_select_db($link, "hospital");
    
    if (in_array($report_type, ['daily_summary', 'revenue_breakdown', 'all'])) {
        // Pharmacy Daily Summary
        $pharmacy_daily_query = "
            SELECT 
                DATE(pb.date) as date,
                COUNT(DISTINCT pb.id) as bill_count,
                COALESCE(SUM(pbd.total_amount), 0) as total_amount,
                COALESCE(AVG(pbd.total_amount), 0) as avg_bill_amount
            FROM pharmacy_billing pb
            LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            WHERE pb.date BETWEEN '$start_date' AND '$end_date 23:59:59'
            GROUP BY DATE(pb.date)
            ORDER BY date DESC
        ";
        $pharmacy_daily_result = mysqli_query($link, $pharmacy_daily_query);
        $pharmacy_daily = [];
        if ($pharmacy_daily_result) {
            while ($row = mysqli_fetch_assoc($pharmacy_daily_result)) {
                $pharmacy_daily[] = $row;
            }
        }

        // Pharmacy Payment Analysis
        $pharmacy_payments_query = "
            SELECT 
                COALESCE(pb.payment_method, 'cash') as payment_method,
                COUNT(DISTINCT pb.id) as bill_count,
                COALESCE(SUM(pbd.total_amount), 0) as total_amount
            FROM pharmacy_billing pb
            LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            WHERE pb.date BETWEEN '$start_date' AND '$end_date 23:59:59'
            GROUP BY COALESCE(pb.payment_method, 'cash')
            ORDER BY total_amount DESC
        ";
        $pharmacy_payments_result = mysqli_query($link, $pharmacy_payments_query);
        $pharmacy_payments = [];
        if ($pharmacy_payments_result) {
            while ($row = mysqli_fetch_assoc($pharmacy_payments_result)) {
                $pharmacy_payments[] = $row;
            }
        }

        // Top Pharmacy Items
        $pharmacy_items_query = "
            SELECT 
                pbd.product_name as item_name,
                COALESCE(SUM(pbd.quantity), 0) as total_quantity,
                COALESCE(SUM(pbd.total_amount), 0) as total_revenue,
                COUNT(DISTINCT pbd.billing_id) as bill_count
            FROM pharmacy_billing_details pbd
            INNER JOIN pharmacy_billing pb ON pbd.billing_id = pb.id
            WHERE pb.date BETWEEN '$start_date' AND '$end_date 23:59:59'
            GROUP BY pbd.product_name
            ORDER BY total_revenue DESC
            LIMIT 10
        ";
        $pharmacy_items_result = mysqli_query($link, $pharmacy_items_query);
        $pharmacy_items = [];
        if ($pharmacy_items_result) {
            while ($row = mysqli_fetch_assoc($pharmacy_items_result)) {
                $pharmacy_items[] = $row;
            }
        } else {
            // If query fails, initialize as empty array
            $pharmacy_items = [];
        }
    }

    // COMBINED TOTALS
    // Calculate hospital totals
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as bill_count,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM billing 
        WHERE bill_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $hospital_totals = $stmt->fetch();
    $combined_totals['hospital_revenue'] = $hospital_totals['total_revenue'] ?? 0;
    $combined_totals['hospital_bills'] = $hospital_totals['bill_count'] ?? 0;

    // Calculate pharmacy totals
    $pharmacy_totals_query = "
        SELECT 
            COUNT(DISTINCT pb.id) as bill_count,
            COALESCE(SUM(pbd.total_amount), 0) as total_revenue
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE pb.date BETWEEN '$start_date' AND '$end_date 23:59:59'
    ";
    $pharmacy_totals_result = mysqli_query($link, $pharmacy_totals_query);
    if ($pharmacy_totals_result) {
        $pharmacy_totals = mysqli_fetch_assoc($pharmacy_totals_result);
        $combined_totals['pharmacy_revenue'] = $pharmacy_totals['total_revenue'] ?? 0;
        $combined_totals['pharmacy_bills'] = $pharmacy_totals['bill_count'] ?? 0;
    }

    $combined_totals['total_revenue'] = $combined_totals['hospital_revenue'] + $combined_totals['pharmacy_revenue'];
    $combined_totals['total_bills'] = $combined_totals['hospital_bills'] + $combined_totals['pharmacy_bills'];

} catch (Exception $e) {
    error_log("Error generating reports: " . $e->getMessage());
    $_SESSION['error'] = "Error generating reports: " . $e->getMessage();
}

// Ensure all arrays are initialized even if there was an error
$hospital_daily = $hospital_daily ?? [];
$hospital_payments = $hospital_payments ?? [];
$hospital_departments = $hospital_departments ?? [];
$hospital_services = $hospital_services ?? [];
$pharmacy_daily = $pharmacy_daily ?? [];
$pharmacy_payments = $pharmacy_payments ?? [];
$pharmacy_items = $pharmacy_items ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Reports - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0891b2;
            --primary-light: #06b6d4;
            --primary-dark: #0e7490;
            --secondary: #14b8a6;
            --accent: #22d3ee;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #134e4a;
            --light: #f0fdfa;
            --gray: #64748b;
            --card-bg: #ffffff;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
        }
        
        .main-content {
            margin-left: 260px;
            padding: 1rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 0.75rem;
            }
        }
        
        @media (max-width: 640px) {
            .main-content {
                padding: 0.5rem;
            }
        }
        
        /* Header */
        .page-header {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            margin: 0.5rem 0 0 0;
            color: var(--gray);
            font-size: 0.9375rem;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 1.25rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
        
        /* Filters Card */
        .filters-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
        }
        
        .form-group {
            margin-bottom: 0;
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
            border: 1px solid rgba(6, 182, 212, 0.2);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: var(--light);
            color: var(--dark);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, var(--success) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: rgba(6, 182, 212, 0.05);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.25rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid rgba(6, 182, 212, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(6, 182, 212, 0.1);
        }
        
        .stat-card.revenue { border-top: 4px solid var(--success); }
        .stat-card.hospital { border-top: 4px solid var(--primary); }
        .stat-card.pharmacy { border-top: 4px solid #8b5cf6; }
        .stat-card.bills { border-top: 4px solid var(--warning); }
        
        .stat-card h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-card p {
            margin: 0.5rem 0 0 0;
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .stat-card small {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray);
            opacity: 0.8;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .card:last-child {
            margin-bottom: 0;
        }
        
        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(6, 182, 212, 0.1);
            background: #f8fafc;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        @media (max-width: 768px) {
            .card-header,
            .card-body {
                padding: 1rem;
            }
        }
        
        /* Chart Container */
        .chart-container {
            height: 250px;
            position: relative;
        }
        
        @media (min-width: 768px) {
            .chart-container {
                height: 300px;
            }
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
        
        @media (max-width: 768px) {
            table {
                min-width: 500px;
            }
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        td {
            font-size: 0.875rem;
            color: var(--dark);
        }
        
        tr:hover td {
            background: rgba(6, 182, 212, 0.03);
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Revenue Badges */
        .revenue-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .revenue-hospital { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        .revenue-pharmacy { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.875rem;
        }
        
        /* Export Actions */
        .export-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .export-actions {
                justify-content: stretch;
            }
            
            .export-actions .btn {
                flex: 1;
                min-width: 120px;
            }
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1000;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            cursor: pointer;
        }
        
        @media (max-width: 1024px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
        
        /* Print Styles */
        @media print {
            .mobile-menu-toggle,
            .filters-card,
            .btn,
            .export-actions {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }
            
            table {
                min-width: auto;
            }
        }
        
        /* Loading State */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(6, 182, 212, 0.05);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(6, 182, 212, 0.3);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(6, 182, 212, 0.5);
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Billing Reports</h1>
            <p>Comprehensive financial reports for hospital services and pharmacy</p>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="start_date"><i class="far fa-calendar-alt"></i> Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="end_date"><i class="far fa-calendar-alt"></i> End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="report_type"><i class="fas fa-chart-line"></i> Report Type</label>
                    <select class="form-control" id="report_type" name="report_type">
                        <option value="daily_summary" <?php echo $report_type == 'daily_summary' ? 'selected' : ''; ?>>Daily Summary</option>
                        <option value="revenue_breakdown" <?php echo $report_type == 'revenue_breakdown' ? 'selected' : ''; ?>>Revenue Breakdown</option>
                        <option value="all" <?php echo $report_type == 'all' ? 'selected' : ''; ?>>All Reports</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Generate Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button type="button" class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card revenue">
                <h3>Ksh <?php echo number_format($combined_totals['total_revenue'], 2); ?></h3>
                <p>Total Revenue</p>
                <small><?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></small>
            </div>
            
            <div class="stat-card bills">
                <h3><?php echo number_format($combined_totals['total_bills']); ?></h3>
                <p>Total Bills</p>
                <small>Combined hospital & pharmacy</small>
            </div>
            
            <div class="stat-card hospital">
                <h3>Ksh <?php echo number_format($combined_totals['hospital_revenue'], 2); ?></h3>
                <p>Hospital Services Revenue</p>
                <small><?php echo number_format($combined_totals['hospital_bills']); ?> bills</small>
            </div>
            
            <div class="stat-card pharmacy">
                <h3>Ksh <?php echo number_format($combined_totals['pharmacy_revenue'], 2); ?></h3>
                <p>Pharmacy Sales Revenue</p>
                <small><?php echo number_format($combined_totals['pharmacy_bills']); ?> bills</small>
            </div>
        </div>
        
        <!-- Revenue Distribution Chart -->
        <div class="charts-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Revenue Distribution
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="revenueDistributionChart"></canvas>
                    </div>
                    <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; background: #3b82f6; border-radius: 2px;"></div>
                            <span style="font-size: 0.75rem; color: var(--gray);">Hospital Services</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; background: #8b5cf6; border-radius: 2px;"></div>
                            <span style="font-size: 0.75rem; color: var(--gray);">Pharmacy Sales</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Daily Revenue Trend
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="dailyRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Methods Analysis -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-credit-card"></i>
                    Payment Methods Analysis
                </h2>
                <div class="export-actions">
                    <button type="button" class="btn btn-outline" onclick="exportPaymentTable()">
                        <i class="fas fa-download"></i> Export Table
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-center">Hospital</th>
                                <th class="text-right">Hospital Amount</th>
                                <th class="text-center">Pharmacy</th>
                                <th class="text-right">Pharmacy Amount</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_payment_methods = [];
                            
                            // Collect hospital payment methods
                            foreach ($hospital_payments as $payment) {
                                $all_payment_methods[$payment['payment_method']] = [
                                    'hospital_count' => $payment['transaction_count'],
                                    'hospital_amount' => $payment['total_amount'],
                                    'pharmacy_count' => 0,
                                    'pharmacy_amount' => 0
                                ];
                            }
                            
                            // Collect pharmacy payment methods
                            foreach ($pharmacy_payments as $payment) {
                                $method = $payment['payment_method'];
                                if (isset($all_payment_methods[$method])) {
                                    $all_payment_methods[$method]['pharmacy_count'] = $payment['bill_count'];
                                    $all_payment_methods[$method]['pharmacy_amount'] = $payment['total_amount'];
                                } else {
                                    $all_payment_methods[$method] = [
                                        'hospital_count' => 0,
                                        'hospital_amount' => 0,
                                        'pharmacy_count' => $payment['bill_count'],
                                        'pharmacy_amount' => $payment['total_amount']
                                    ];
                                }
                            }
                            
                            if (count($all_payment_methods) > 0):
                                foreach ($all_payment_methods as $method => $data):
                                    $total_amount = $data['hospital_amount'] + $data['pharmacy_amount'];
                            ?>
                            <tr>
                                <td><strong><?php echo ucfirst($method); ?></strong></td>
                                <td class="text-center"><?php echo number_format($data['hospital_count']); ?></td>
                                <td class="text-right">Ksh <?php echo number_format($data['hospital_amount'], 2); ?></td>
                                <td class="text-center"><?php echo number_format($data['pharmacy_count']); ?></td>
                                <td class="text-right">Ksh <?php echo number_format($data['pharmacy_amount'], 2); ?></td>
                                <td class="text-right"><strong>Ksh <?php echo number_format($total_amount, 2); ?></strong></td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-credit-card"></i>
                                        <h3>No Payment Data</h3>
                                        <p>No payment transactions found in the selected period.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Top Services/Products -->
        <div class="charts-grid">
            <!-- Top Hospital Services -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-stethoscope"></i>
                        Top Hospital Services
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (count($hospital_services) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-right">Revenue</th>
                                    <th class="text-right">Avg/Service</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospital_services as $service): ?>
                                <tr>
                                    <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($service['item_description']); ?>
                                    </td>
                                    <td class="text-center"><?php echo number_format($service['total_quantity']); ?></td>
                                    <td class="text-right">Ksh <?php echo number_format($service['total_revenue'], 2); ?></td>
                                    ; ?></td>
<td class="text-right">Ksh <?php echo number_format($service['avg_revenue'], 2); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-stethoscope"></i>
<h3>No Hospital Services Data</h3>
<p>No hospital services found in the selected period.</p>
</div>
<?php endif; ?>
</div>
</div>
        <!-- Top Pharmacy Products -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-pills"></i>
                    Top Pharmacy Products
                </h2>
            </div>
            <div class="card-body">
                <?php if (count($pharmacy_items) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">Avg/Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($pharmacy_items as $item):
                                $avg_per_unit = $item['total_quantity'] > 0 ? $item['total_revenue'] / $item['total_quantity'] : 0;
                            ?>
                            <tr>
                                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                </td>
                                <td class="text-center"><?php echo number_format($item['total_quantity']); ?></td>
                                <td class="text-right">Ksh <?php echo number_format($item['total_revenue'], 2); ?></td>
                                <td class="text-right">Ksh <?php echo number_format($avg_per_unit, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <h3>No Pharmacy Products Data</h3>
                    <p>No pharmacy products found in the selected period.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Department-wise Revenue -->
    <?php if ($report_type == 'revenue_breakdown' || $report_type == 'all'): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-building"></i>
                Department-wise Revenue Breakdown
            </h2>
            <div class="export-actions">
                <button type="button" class="btn btn-outline" onclick="exportDepartmentTable()">
                    <i class="fas fa-download"></i> Export Table
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($hospital_departments) > 0): ?>
            <div class="chart-container">
                <canvas id="departmentRevenueChart"></canvas>
            </div>
            <div class="table-container" style="margin-top: 1.5rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th class="text-center">Bills</th>
                            <th class="text-right">Total Revenue</th>
                            <th class="text-right">Avg/Bill</th>
                            <th class="text-right">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_dept_revenue = 0;
                        foreach ($hospital_departments as $dept) {
                            $total_dept_revenue += $dept['total_revenue'];
                        }
                        
                        foreach ($hospital_departments as $dept):
                            $percentage = $total_dept_revenue > 0 ? ($dept['total_revenue'] / $total_dept_revenue) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dept['department']); ?></strong></td>
                            <td class="text-center"><?php echo number_format($dept['bill_count']); ?></td>
                            <td class="text-right">Ksh <?php echo number_format($dept['total_revenue'], 2); ?></td>
                            <td class="text-right">Ksh <?php echo number_format($dept['avg_revenue'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <h3>No Department Data</h3>
                <p>No department revenue data found in the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Daily Revenue Table -->
    <?php if ($report_type == 'daily_summary' || $report_type == 'all'): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-calendar-alt"></i>
                Daily Revenue Summary
            </h2>
            <div class="export-actions">
                <button type="button" class="btn btn-outline" onclick="exportDailyTable()">
                    <i class="fas fa-download"></i> Export Table
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php 
            $all_dates = [];
            $date_data = [];
            
            // Combine hospital and pharmacy daily data
            foreach ($hospital_daily as $day) {
                $date = $day['date'];
                $all_dates[$date] = $date;
                $date_data[$date] = [
                    'hospital_bills' => $day['bill_count'],
                    'hospital_revenue' => $day['total_amount'],
                    'pharmacy_bills' => 0,
                    'pharmacy_revenue' => 0
                ];
            }
            
            foreach ($pharmacy_daily as $day) {
                $date = $day['date'];
                $all_dates[$date] = $date;
                if (isset($date_data[$date])) {
                    $date_data[$date]['pharmacy_bills'] = $day['bill_count'];
                    $date_data[$date]['pharmacy_revenue'] = $day['total_amount'];
                } else {
                    $date_data[$date] = [
                        'hospital_bills' => 0,
                        'hospital_revenue' => 0,
                        'pharmacy_bills' => $day['bill_count'],
                        'pharmacy_revenue' => $day['total_amount']
                    ];
                }
            }
            
            krsort($all_dates); // Sort dates descending
            
            if (count($all_dates) > 0):
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-center">Hospital Bills</th>
                            <th class="text-right">Hospital Revenue</th>
                            <th class="text-center">Pharmacy Bills</th>
                            <th class="text-right">Pharmacy Revenue</th>
                            <th class="text-right">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total_hospital = 0;
                        $grand_total_pharmacy = 0;
                        $grand_total_bills_hospital = 0;
                        $grand_total_bills_pharmacy = 0;
                        
                        foreach ($all_dates as $date):
                            $data = $date_data[$date];
                            $total_revenue = $data['hospital_revenue'] + $data['pharmacy_revenue'];
                            
                            $grand_total_hospital += $data['hospital_revenue'];
                            $grand_total_pharmacy += $data['pharmacy_revenue'];
                            $grand_total_bills_hospital += $data['hospital_bills'];
                            $grand_total_bills_pharmacy += $data['pharmacy_bills'];
                        ?>
                        <tr>
                            <td><strong><?php echo date('M j, Y', strtotime($date)); ?></strong></td>
                            <td class="text-center"><?php echo number_format($data['hospital_bills']); ?></td>
                            <td class="text-right">Ksh <?php echo number_format($data['hospital_revenue'], 2); ?></td>
                            <td class="text-center"><?php echo number_format($data['pharmacy_bills']); ?></td>
                            <td class="text-right">Ksh <?php echo number_format($data['pharmacy_revenue'], 2); ?></td>
                            <td class="text-right">
                                <strong>Ksh <?php echo number_format($total_revenue, 2); ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Grand Total Row -->
                        <tr style="background: #f8fafc; font-weight: 600;">
                            <td><strong>GRAND TOTAL</strong></td>
                            <td class="text-center"><?php echo number_format($grand_total_bills_hospital); ?></td>
                            <td class="text-right">Ksh <?php echo number_format($grand_total_hospital, 2); ?></td>
                            <td class="text-center"><?php echo number_format($grand_total_bills_pharmacy); ?></td>
                            <td class="text-right">Ksh <?php echo number_format($grand_total_pharmacy, 2); ?></td>
                            <td class="text-right">
                                Ksh <?php echo number_format($grand_total_hospital + $grand_total_pharmacy, 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-alt"></i>
                <h3>No Daily Data</h3>
                <p>No revenue data found for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // Mobile menu toggle
    document.getElementById('menuToggle').addEventListener('click', function() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('active');
        }
    });
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        // Revenue Distribution Chart
        const revenueCtx = document.getElementById('revenueDistributionChart')?.getContext('2d');
        if (revenueCtx) {
            const hospitalRevenue = <?php echo $combined_totals['hospital_revenue']; ?>;
            const pharmacyRevenue = <?php echo $combined_totals['pharmacy_revenue']; ?>;
            
            new Chart(revenueCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Hospital Services', 'Pharmacy Sales'],
                    datasets: [{
                        data: [hospitalRevenue, pharmacyRevenue],
                        backgroundColor: ['#3b82f6', '#8b5cf6'],
                        borderColor: ['#2563eb', '#7c3aed'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: Ksh ${value.toLocaleString('en-US', {minimumFractionDigits: 2})} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }
        
        // Daily Revenue Trend Chart
        const dailyCtx = document.getElementById('dailyRevenueChart')?.getContext('2d');
        if (dailyCtx) {
            <?php
            $sorted_dates = array_keys($all_dates);
            rsort($sorted_dates);
            $sorted_dates = array_slice($sorted_dates, -15); // Last 15 days
            sort($sorted_dates); // Sort ascending for chart
            
            $hospital_daily_data = [];
            $pharmacy_daily_data = [];
            $labels = [];
            
            foreach ($sorted_dates as $date) {
                $data = $date_data[$date] ?? ['hospital_revenue' => 0, 'pharmacy_revenue' => 0];
                $labels[] = date('M j', strtotime($date));
                $hospital_daily_data[] = $data['hospital_revenue'];
                $pharmacy_daily_data[] = $data['pharmacy_revenue'];
            }
            ?>
            
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [
                        {
                            label: 'Hospital Services',
                            data: <?php echo json_encode($hospital_daily_data); ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Pharmacy Sales',
                            data: <?php echo json_encode($pharmacy_daily_data); ?>,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: Ksh ${context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Ksh ' + value.toLocaleString('en-US', {minimumFractionDigits: 0});
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        // Department Revenue Chart
        const deptCtx = document.getElementById('departmentRevenueChart')?.getContext('2d');
        if (deptCtx) {
            <?php
            $dept_labels = [];
            $dept_data = [];
            $dept_colors = [
                '#3b82f6', '#06b6d4', '#8b5cf6', '#10b981', '#f59e0b',
                '#ef4444', '#ec4899', '#14b8a6', '#84cc16', '#f97316'
            ];
            
            $counter = 0;
            foreach ($hospital_departments as $dept) {
                $dept_labels[] = $dept['department'];
                $dept_data[] = $dept['total_revenue'];
                $counter++;
            }
            ?>
            
            new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($dept_labels); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode($dept_data); ?>,
                        backgroundColor: <?php echo json_encode(array_slice($dept_colors, 0, count($dept_labels))); ?>,
                        borderColor: <?php echo json_encode(array_slice($dept_colors, 0, count($dept_labels))); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Ksh ' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Ksh ' + value.toLocaleString('en-US', {minimumFractionDigits: 0});
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        }
    });
    
    // Export functions
    function exportToExcel() {
        alert('Excel export functionality would be implemented here. This would typically generate a CSV or Excel file with all report data.');
        // In a real implementation, this would make an AJAX call to a PHP script
        // that generates and downloads an Excel file
    }
    
    function exportPaymentTable() {
        const data = [];
        const rows = document.querySelectorAll('table:first-of-type tbody tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('td');
            if (cols.length > 0) {
                const rowData = Array.from(cols).map(col => col.textContent.trim());
                data.push(rowData);
            }
        });
        
        // Convert to CSV and download
        downloadCSV(data, 'payment_methods.csv', ['Payment Method', 'Hospital Count', 'Hospital Amount', 'Pharmacy Count', 'Pharmacy Amount', 'Total']);
    }
    
    function exportDepartmentTable() {
        const data = [];
        const rows = document.querySelectorAll('table:nth-of-type(2) tbody tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('td');
            if (cols.length > 0) {
                const rowData = Array.from(cols).map(col => col.textContent.trim());
                data.push(rowData);
            }
        });
        
        downloadCSV(data, 'department_revenue.csv', ['Department', 'Bills', 'Total Revenue', 'Avg/Bill', '% of Total']);
    }
    
    function exportDailyTable() {
        const data = [];
        const rows = document.querySelectorAll('table:last-of-type tbody tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('td');
            if (cols.length > 0) {
                const rowData = Array.from(cols).map(col => col.textContent.trim());
                data.push(rowData);
            }
        });
        
        downloadCSV(data, 'daily_revenue.csv', ['Date', 'Hospital Bills', 'Hospital Revenue', 'Pharmacy Bills', 'Pharmacy Revenue', 'Total Revenue']);
    }
    
    function downloadCSV(data, filename, headers = null) {
        let csvContent = '';
        
        // Add headers if provided
        if (headers) {
            csvContent += headers.join(',') + '\n';
        }
        
        // Add data rows
        data.forEach(row => {
            csvContent += row.join(',') + '\n';
        });
        
        // Create and trigger download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        
        if (startDate > endDate) {
            e.preventDefault();
            alert('Start date cannot be after end date.');
            return false;
        }
        
        // Add loading state
        this.classList.add('loading');
    });
    
    // Responsive adjustments
    window.addEventListener('resize', function() {
        // Reinitialize charts on resize for better responsiveness
        if (typeof Chart !== 'undefined') {
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.resize();
            });
        }
    });
</script>