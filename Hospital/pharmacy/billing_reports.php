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
                pbd.item_name,
                COALESCE(SUM(pbd.quantity), 0) as total_quantity,
                COALESCE(SUM(pbd.total_amount), 0) as total_revenue,
                COUNT(DISTINCT pbd.billing_id) as bill_count
            FROM pharmacy_billing_details pbd
            INNER JOIN pharmacy_billing pb ON pbd.billing_id = pb.id
            WHERE pb.date BETWEEN '$start_date' AND '$end_date 23:59:59'
            GROUP BY pbd.item_name
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
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            margin: 0;
            color: #1f2937;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .page-header p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
        }
        
        .filters-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
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
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3b82f6;
        }
        
        .stat-card.revenue { border-left-color: #10b981; }
        .stat-card.hospital { border-left-color: #3b82f6; }
        .stat-card.pharmacy { border-left-color: #8b5cf6; }
        .stat-card.bills { border-left-color: #f59e0b; }
        
        .stat-card h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-card p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 1rem;
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
            color: #1f2937;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            font-size: 0.875rem;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .revenue-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .revenue-hospital { background: #dbeafe; color: #1e40af; }
        .revenue-pharmacy { background: #f3e8ff; color: #7c3aed; }
        
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
        
        .export-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Billing Reports</h1>
            <p>Comprehensive financial reports for hospital services and pharmacy</p>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="report_type">Report Type</label>
                    <select class="form-control" id="report_type" name="report_type">
                        <option value="daily_summary" <?php echo $report_type == 'daily_summary' ? 'selected' : ''; ?>>Daily Summary</option>
                        <option value="revenue_breakdown" <?php echo $report_type == 'revenue_breakdown' ? 'selected' : ''; ?>>Revenue Breakdown</option>
                        <option value="all" <?php echo $report_type == 'all' ? 'selected' : ''; ?>>All Reports</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Generate Report
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
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
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-center">Hospital Transactions</th>
                                <th class="text-right">Hospital Amount</th>
                                <th class="text-center">Pharmacy Transactions</th>
                                <th class="text-right">Pharmacy Amount</th>
                                <th class="text-right">Total Amount</th>
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
                                    <th class="text-right">Total Revenue</th>
                                    <th class="text-right">Avg. Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospital_services as $service): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['item_description']); ?></td>
                                    <td class="text-center"><?php echo number_format($service['total_quantity']); ?></td>
                                    <td class="text-right">Ksh <?php echo number_format($service['total_revenue'], 2); ?></td>
                                    <td class="text-right">Ksh <?php echo number_format($service['avg_revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Hospital Services Data</h3>
                        <p>No hospital services were billed in the selected period.</p>
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
                                    <th class="text-center">Quantity Sold</th>
                                    <th class="text-right">Total Revenue</th>
                                    <th class="text-center">Bills</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="text-center"><?php echo number_format($item['total_quantity']); ?></td>
                                    <td class="text-right">Ksh <?php echo number_format($item['total_revenue'], 2); ?></td>
                                    <td class="text-center"><?php echo number_format($item['bill_count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-pills"></i>
                        <h3>No Pharmacy Sales Data</h3>
                        <p>No pharmacy products were sold in the selected period.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Distribution Chart
        const revenueCtx = document.getElementById('revenueDistributionChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hospital Services', 'Pharmacy Sales'],
                datasets: [{
                    data: [
                        <?php echo $combined_totals['hospital_revenue']; ?>,
                        <?php echo $combined_totals['pharmacy_revenue']; ?>
                    ],
                    backgroundColor: ['#3b82f6', '#8b5cf6'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: Ksh ${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Daily Revenue Chart
        const dailyCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        
        // Prepare daily data - combine last 7 days from both systems
        const dates = [];
        const hospitalRevenue = [];
        const pharmacyRevenue = [];
        
        // Get last 7 days of data for the chart
        const recentHospitalData = <?php echo json_encode(array_slice($hospital_daily, 0, 7)); ?>;
        const recentPharmacyData = <?php echo json_encode(array_slice($pharmacy_daily, 0, 7)); ?>;
        
        // Create a set of all dates from both systems
        const allDates = new Set();
        
        recentHospitalData.forEach(day => allDates.add(day.date));
        recentPharmacyData.forEach(day => allDates.add(day.date));
        
        // Convert to array and sort
        const sortedDates = Array.from(allDates).sort().reverse().slice(0, 7).reverse();
        
        sortedDates.forEach(date => {
            const hospitalDay = recentHospitalData.find(d => d.date === date);
            const pharmacyDay = recentPharmacyData.find(d => d.date === date);
            
            dates.push(new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            hospitalRevenue.push(hospitalDay ? parseFloat(hospitalDay.total_amount) : 0);
            pharmacyRevenue.push(pharmacyDay ? parseFloat(pharmacyDay.total_amount) : 0);
        });
        
        const dailyChart = new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Hospital Services',
                        data: hospitalRevenue,
                        backgroundColor: '#3b82f6',
                        borderColor: '#3b82f6',
                        borderWidth: 1
                    },
                    {
                        label: 'Pharmacy Sales',
                        data: pharmacyRevenue,
                        backgroundColor: '#8b5cf6',
                        borderColor: '#8b5cf6',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y || 0;
                                return `${label}: Ksh ${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });

        // Export to Excel function
        function exportToExcel() {
            // Simple table export - you might want to use a library like SheetJS for more complex exports
            const tables = document.querySelectorAll('table');
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add report header
            csvContent += "Equity Afia Billing Report\n";
            csvContent += "Period: <?php echo $start_date . ' to ' . $end_date; ?>\n";
            csvContent += "Generated on: <?php echo date('Y-m-d H:i:s'); ?>\n\n";
            
            tables.forEach((table, tableIndex) => {
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const cols = row.querySelectorAll('th, td');
                    const rowArray = [];
                    cols.forEach(col => {
                        // Remove any commas from numbers for proper CSV formatting
                        let text = col.textContent.trim().replace(/,/g, '');
                        // Wrap text in quotes if it contains commas
                        if (text.includes(',')) {
                            text = '"' + text + '"';
                        }
                        rowArray.push(text);
                    });
                    csvContent += rowArray.join(",") + "\n";
                });
                csvContent += "\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "billing_report_<?php echo $start_date . '_to_' . $end_date; ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto-refresh charts on window resize
        window.addEventListener('resize', function() {
            revenueChart.resize();
            dailyChart.resize();
        });
    </script>
</body>
</html>