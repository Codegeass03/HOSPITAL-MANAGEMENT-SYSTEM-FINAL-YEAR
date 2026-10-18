<?php
session_start();

include("config/database.php");
include("includes/sidebar.php");

// Set default dates
date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// Get filter parameters
$reportType = isset($_GET['report']) ? $_GET['report'] : 'financial';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $thirtyDaysAgo;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
$supplierId = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : '';

// Function to safely execute queries
function fetchData($query, $params = [], $database) {
    $stmt = $database->prepare($query);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Smart Clinic Management System</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
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

        .menu-container { width: 100%; }
        .profile-container { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px; }
        .profile-title { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
        .profile-subtitle { font-size: 13px; opacity: 0.8; }
        .logout-btn { width: 100%; padding: 10px; margin-top: 15px; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .menu-row { margin: 5px 0; }
        .menu-btn { padding: 15px 20px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 15px; }
        .menu-btn:hover { background: rgba(255,255,255,0.1); }
        .menu-btn.menu-active { background: rgba(255,255,255,0.2); border-left: 4px solid white; }
        .menu-text { font-size: 15px; color: white; text-decoration: none; }
        .non-style-link-menu { text-decoration: none; color: white; display: block; }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px 50px;
        }

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

        /* Report Filters */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 8px;
        }

        .filter-group input, .filter-group select {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .btn-primary {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-success {
            padding: 12px 24px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        /* Report Content */
        .report-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .summary-card.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .summary-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .summary-card.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .summary-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 13px;
            opacity: 0.9;
        }

        /* Tables */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .report-table th {
            background: var(--primarycolor);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .report-table tr:hover {
            background: #f0fdfa;
        }

        .scroll {
            overflow-y: auto;
            max-height: 500px;
        }

        .scroll::-webkit-scrollbar {
            width: 8px;
        }

        .scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .scroll::-webkit-scrollbar-thumb {
            background: var(--primarycolor);
            border-radius: 4px;
        }

        /* Status badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-expiring { background: #fef3c7; color: #92400e; }
        .status-expired { background: #fecaca; color: #991b1b; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #f0fdfa; color: #115e59; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Export Icon */
        .export-icon {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* Print Styles */
        @media print {
            .filter-section, .nav-bar, .menu, .btn-success, .report-header { display: none; }
            .dash-body { margin-left: 0; padding: 20px; }
            .report-content { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Main Content -->
        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Analytics & Reports</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Generate and view comprehensive Hospital reports</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Reports</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php echo date('M j, Y H:i'); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <!-- Report Filters -->
                <tr>
                    <td colspan="4">
                        <div class="filter-section">
                            <form method="GET" action="reports.php">
                                <div class="filter-grid">
                                    <div class="filter-group">
                                        <label>Report Type</label>
                                        <select name="report" onchange="this.form.submit()">
                                            <option value="financial" <?php echo $reportType=='financial'?'selected':''; ?>>Financial Summary</option>
                                            <option value="revenue" <?php echo $reportType=='revenue'?'selected':''; ?>>Revenue Analysis</option>
                                            <option value="expenses" <?php echo $reportType=='expenses'?'selected':''; ?>>Expense Report</option>
                                            <option value="profit" <?php echo $reportType=='profit'?'selected':''; ?>>Profit & Loss</option>
                                            <option value="expiry" <?php echo $reportType=='expiry'?'selected':''; ?>>Expiry Report</option>
                                            <option value="purchase" <?php echo $reportType=='purchase'?'selected':''; ?>>Purchase Report</option>
                                            <option value="supplier" <?php echo $reportType=='supplier'?'selected':''; ?>>Supplier Report</option>
                                            <option value="sales" <?php echo $reportType=='sales'?'selected':''; ?>>Sales Report</option>
                                            <option value="inventory" <?php echo $reportType=='inventory'?'selected':''; ?>>Inventory Report</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label>Start Date</label>
                                        <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                                    </div>
                                    <div class="filter-group">
                                        <label>End Date</label>
                                        <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                                    </div>
                                    <?php if($reportType == 'purchase' || $reportType == 'supplier'): ?>
                                    <div class="filter-group">
                                        <label>Supplier</label>
                                        <select name="supplier_id">
                                            <option value="">All Suppliers</option>
                                            <?php
                                            $suppliers = $database->query("SELECT id, businessname FROM suppliers ORDER BY businessname");
                                            while($row = $suppliers->fetch_assoc()) {
                                                $selected = $supplierId == $row['id'] ? 'selected' : '';
                                                echo "<option value='{$row['id']}' $selected>{$row['businessname']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div class="filter-group">
                                        <button type="submit" class="btn-primary" style="width: 100%;">Generate Report</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <td colspan="4">
                        <div class="report-content">
                            <div class="report-header">
                                <h2 style="margin: 0; color: var(--primarydark);">
                                    <?php 
                                    $reportTitles = [
                                        'financial' => 'Financial Summary',
                                        'revenue' => 'Revenue Analysis',
                                        'expenses' => 'Expense Report',
                                        'profit' => 'Profit & Loss Statement',
                                        'expiry' => 'Product Expiry Report',
                                        'purchase' => 'Purchase Report',
                                        'supplier' => 'Supplier Report',
                                        'sales' => 'Sales Report',
                                        'inventory' => 'Inventory Report'
                                    ];
                                    echo $reportTitles[$reportType] ?? 'Report';
                                    ?>
                                </h2>
                                <?php
                                // Build export URL with current filters
                                $exportUrl = "export_report.php?report=" . urlencode($reportType) . 
                                           "&start_date=" . urlencode($startDate) . 
                                           "&end_date=" . urlencode($endDate);
                                if (!empty($supplierId)) {
                                    $exportUrl .= "&supplier_id=" . urlencode($supplierId);
                                }
                                ?>
                                <a href="<?php echo $exportUrl; ?>" class="btn-success">
                                    <svg class="export-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                        <path d="M14 2v6h6"/>
                                        <path d="M12 18v-6"/>
                                        <path d="m9 15 3 3 3-3"/>
                                    </svg>
                                    Export to Excel
                                </a>
                            </div>
                            
                            <?php
                            // Financial Summary Report
if ($reportType == 'financial' || $reportType == 'revenue' || $reportType == 'expenses' || $reportType == 'profit') {
    
    // Calculate Total Revenue from BILLING_PAYMENTS table
    $revenueQuery = "
        SELECT 
            COALESCE(SUM(amount), 0) as total_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE payment_date BETWEEN ? AND ?
        AND status = 'completed'
    ";
    $stmt = $database->prepare($revenueQuery);
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bind_param("ss", $startDateTime, $endDateTime);
    $stmt->execute();
    $revenueResult = $stmt->get_result()->fetch_assoc();
    $totalRevenue = $revenueResult['total_revenue'];
    $paymentCount = $revenueResult['payment_count'];

    // Calculate Revenue Breakdown by Service Type from BILLING_PAYMENTS
    $revenueBreakdownQuery = "
        SELECT 
            service_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as revenue
        FROM billing_payments 
        WHERE payment_date BETWEEN ? AND ?
        AND status = 'completed'
        GROUP BY service_type
        ORDER BY revenue DESC
    ";
    $stmt = $database->prepare($revenueBreakdownQuery);
    $stmt->bind_param("ss", $startDateTime, $endDateTime);
    $stmt->execute();
    $revenueBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Map service types to readable names
    $serviceNames = [
        'consultation' => 'Consultation',
        'lab' => 'Laboratory Tests',
        'pharmacy' => 'Pharmacy Sales',
        'admission' => 'Admission Charges',
        'consolidated' => 'Consolidated Bills'
    ];

    // Initialize revenue breakdown variables
    $consultationRevenue = 0;
    $labRevenue = 0;
    $pharmacyRevenue = 0;
    $admissionRevenue = 0;
    $consolidatedRevenue = 0;

    // Calculate revenue for each service type
    foreach ($revenueBreakdown as $service) {
        switch ($service['service_type']) {
            case 'consultation':
                $consultationRevenue = $service['revenue'];
                break;
            case 'lab':
                $labRevenue = $service['revenue'];
                break;
            case 'pharmacy':
                $pharmacyRevenue = $service['revenue'];
                break;
            case 'admission':
                $admissionRevenue = $service['revenue'];
                break;
            case 'consolidated':
                $consolidatedRevenue = $service['revenue'];
                break;
        }
    }

    // Calculate Total Expenses from PURCHASE_MASTER table
    $expensesQuery = "
        SELECT 
            COALESCE(SUM(total), 0) as total_expenses,
            COUNT(*) as purchase_count
        FROM purchase_master 
        WHERE purchase_date BETWEEN ? AND ?
        AND status = 'received'
    ";
    $stmt = $database->prepare($expensesQuery);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $expensesResult = $stmt->get_result()->fetch_assoc();
    $totalExpenses = $expensesResult['total_expenses'];
    $purchaseCount = $expensesResult['purchase_count'];

    // Calculate Net Profit
    $netProfit = $totalRevenue - $totalExpenses;
    $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

    // Display summary based on report type
    if ($reportType == 'financial' || $reportType == 'profit') {
        echo '<p style="color: #64748b; margin-bottom: 25px;">Period: ' . date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate)) . '</p>';
        
        echo '<div class="summary-grid">';
        echo '<div class="summary-card success">';
        echo '<div class="summary-value">Ksh ' . number_format($totalRevenue, 2) . '</div>';
        echo '<div class="summary-label">Total Revenue</div>';
        echo '<small style="display: block; margin-top: 10px; opacity: 0.8;">' . $paymentCount . ' payments</small>';
        echo '</div>';
        
        echo '<div class="summary-card danger">';
        echo '<div class="summary-value">Ksh ' . number_format($totalExpenses, 2) . '</div>';
        echo '<div class="summary-label">Total Expenses</div>';
        echo '<small style="display: block; margin-top: 10px; opacity: 0.8;">' . $purchaseCount . ' purchases</small>';
        echo '</div>';
        
        $profitClass = $netProfit >= 0 ? 'success' : 'danger';
        echo '<div class="summary-card ' . $profitClass . '">';
        echo '<div class="summary-value">Ksh ' . number_format($netProfit, 2) . '</div>';
        echo '<div class="summary-label">Net Profit</div>';
        echo '<small style="display: block; margin-top: 10px; opacity: 0.8;">' . number_format($profitMargin, 1) . '% margin</small>';
        echo '</div>';
        echo '</div>';
    }

    if ($reportType == 'financial' || $reportType == 'revenue') {
        echo '<h3 style="margin: 30px 0 20px 0;">Revenue Breakdown by Service</h3>';
        echo '<div class="summary-grid">';
        
        echo '<div class="summary-card">';
        echo '<div class="summary-value">Ksh ' . number_format($consultationRevenue, 2) . '</div>';
        echo '<div class="summary-label">Consultation</div>';
        echo '</div>';
        
        echo '<div class="summary-card">';
        echo '<div class="summary-value">Ksh ' . number_format($labRevenue, 2) . '</div>';
        echo '<div class="summary-label">Laboratory</div>';
        echo '</div>';
        
        echo '<div class="summary-card">';
        echo '<div class="summary-value">Ksh ' . number_format($pharmacyRevenue, 2) . '</div>';
        echo '<div class="summary-label">Pharmacy</div>';
        echo '</div>';
        
        if ($admissionRevenue > 0) {
            echo '<div class="summary-card">';
            echo '<div class="summary-value">Ksh ' . number_format($admissionRevenue, 2) . '</div>';
            echo '<div class="summary-label">Admission</div>';
            echo '</div>';
        }
        
        if ($consolidatedRevenue > 0) {
            echo '<div class="summary-card">';
            echo '<div class="summary-value">Ksh ' . number_format($consolidatedRevenue, 2) . '</div>';
            echo '<div class="summary-label">Consolidated Bills</div>';
            echo '</div>';
        }
        
        echo '</div>';

        // Detailed Revenue Table from billing_payments
        if (!empty($revenueBreakdown)) {
            echo '<h3 style="margin: 30px 0 20px 0;">Detailed Revenue Analysis</h3>';
            echo '<div class="scroll">';
            echo '<table class="report-table">';
            echo '<thead><tr>';
            echo '<th>Service Type</th>';
            echo '<th class="text-center">Transactions</th>';
            echo '<th class="text-right">Revenue</th>';
            echo '<th class="text-right">% of Total</th>';
            echo '<th class="text-right">Avg/Transaction</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($revenueBreakdown as $service) {
                $serviceName = $serviceNames[$service['service_type']] ?? ucfirst($service['service_type']);
                $percentage = $totalRevenue > 0 ? ($service['revenue'] / $totalRevenue) * 100 : 0;
                $average = $service['transaction_count'] > 0 ? $service['revenue'] / $service['transaction_count'] : 0;
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($serviceName) . '</td>';
                echo '<td class="text-center">' . number_format($service['transaction_count']) . '</td>';
                echo '<td class="text-right"><strong>Ksh ' . number_format($service['revenue'], 2) . '</strong></td>';
                echo '<td class="text-right">' . number_format($percentage, 1) . '%</td>';
                echo '<td class="text-right">Ksh ' . number_format($average, 2) . '</td>';
                echo '</tr>';
            }
            
            // Add total row
            echo '<tr style="background-color: #f8fafc; font-weight: bold;">';
            echo '<td>Total</td>';
            echo '<td class="text-center">' . number_format($paymentCount) . '</td>';
            echo '<td class="text-right">Ksh ' . number_format($totalRevenue, 2) . '</td>';
            echo '<td class="text-right">100%</td>';
            echo '<td class="text-right">Ksh ' . number_format($paymentCount > 0 ? $totalRevenue / $paymentCount : 0, 2) . '</td>';
            echo '</tr>';
            
            echo '</tbody></table></div>';
        }

        // Payment Method Breakdown
        $paymentMethodQuery = "
            SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(amount), 0) as revenue
            FROM billing_payments 
            WHERE payment_date BETWEEN ? AND ?
            AND status = 'completed'
            GROUP BY payment_method
            ORDER BY revenue DESC
        ";
        $stmt = $database->prepare($paymentMethodQuery);
        $stmt->bind_param("ss", $startDateTime, $endDateTime);
        $stmt->execute();
        $paymentMethods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($paymentMethods)) {
            echo '<h3 style="margin: 30px 0 20px 0;">Payment Method Analysis</h3>';
            echo '<div class="scroll">';
            echo '<table class="report-table">';
            echo '<thead><tr>';
            echo '<th>Payment Method</th>';
            echo '<th class="text-center">Transactions</th>';
            echo '<th class="text-right">Revenue</th>';
            echo '<th class="text-right">% of Total</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($paymentMethods as $method) {
                $methodName = ucfirst(str_replace('_', ' ', $method['payment_method']));
                $percentage = $totalRevenue > 0 ? ($method['revenue'] / $totalRevenue) * 100 : 0;
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($methodName) . '</td>';
                echo '<td class="text-center">' . number_format($method['transaction_count']) . '</td>';
                echo '<td class="text-right"><strong>Ksh ' . number_format($method['revenue'], 2) . '</strong></td>';
                echo '<td class="text-right">' . number_format($percentage, 1) . '%</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        }
    }

    if ($reportType == 'financial' || $reportType == 'expenses') {
        // Detailed Expenses from PURCHASE_MASTER
        $expensesDetailQuery = "
            SELECT 
                pm.purchase_date,
                pm.product_name,
                pm.company_name,
                pm.received_quantity,
                pm.price,
                pm.total,
                s.businessname as supplier_name
            FROM purchase_master pm
            LEFT JOIN suppliers s ON pm.party_name = s.businessname
            WHERE pm.purchase_date BETWEEN ? AND ?
            AND pm.status = 'received'
            ORDER BY pm.purchase_date DESC, pm.total DESC
        ";
        $stmt = $database->prepare($expensesDetailQuery);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $expensesDetail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo '<h3 style="margin: 30px 0 20px 0;">Expense Details</h3>';
    
        
        if (!empty($expensesDetail)) {
            echo '<div class="scroll">';
            echo '<table class="report-table">';
            echo '<thead><tr>';
            echo '<th>Date</th>';
            echo '<th>Product</th>';
            echo '<th>Supplier</th>';
            echo '<th class="text-center">Quantity</th>';
            echo '<th class="text-right">Unit Price</th>';
            echo '<th class="text-right">Total</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($expensesDetail as $expense) {
                echo '<tr>';
                echo '<td>' . date('M j, Y', strtotime($expense['purchase_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($expense['product_name']) . '<br><small style="color: #64748b;">' . htmlspecialchars($expense['company_name']) . '</small></td>';
                echo '<td>' . htmlspecialchars($expense['supplier_name'] ?? $expense['company_name']) . '</td>';
                echo '<td class="text-center">' . number_format($expense['received_quantity']) . '</td>';
                echo '<td class="text-right">Ksh ' . number_format($expense['price'], 2) . '</td>';
                echo '<td class="text-right"><strong>Ksh ' . number_format($expense['total'], 2) . '</strong></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        } else {
            echo '<p style="color: #64748b; text-align: center; padding: 20px;">No expenses recorded for this period.</p>';
        }
    }

} else {
                                // Include other report types
                                $includedVars = [
                                    'startDate' => $startDate,
                                    'endDate' => $endDate,
                                    'supplierId' => $supplierId,
                                    'reportType' => $reportType,
                                    'database' => $database
                                ];
                                
                                extract($includedVars);
                                
                                switch($reportType) {
                                    case 'expiry':
                                        if (file_exists('expiry_report.php')) {
                                            include 'expiry_report.php';
                                        }
                                        break;
                                    case 'purchase':
                                        if (file_exists('purchase_report.php')) {
                                            include 'purchase_report.php';
                                        }
                                        break;
                                    case 'supplier':
                                        if (file_exists('supplier_report.php')) {
                                            include 'supplier_report.php';
                                        }
                                        break;
                                    case 'sales':
                                        if (file_exists('bills.php')) {
                                            include 'bills.php';
                                        }
                                        break;
                                    case 'inventory':
                                        if (file_exists('inventory_report.php')) {
                                            include 'inventory_report.php';
                                        }
                                        break;
                                }
                            }
                            ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>