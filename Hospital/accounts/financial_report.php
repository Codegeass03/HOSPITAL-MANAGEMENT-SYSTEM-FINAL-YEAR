<?php
// financial_report.php - Enhanced version for billing dashboard compatibility

// Get database connections
include "includes/db_connect.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get date range parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';
$exportToExcel = isset($_GET['export']) && $_GET['export'] === 'excel';

// Helper function to execute queries
function fetchData($conn, $query, $params = []) {
    try {
        $stmt = $conn->prepare($query);
        if ($params) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return [];
    }
}

function fetchSingleValue($conn, $query, $params = []) {
    try {
        $stmt = $conn->prepare($query);
        if ($params) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return 0;
    }
}

// Get total revenue from all sources
$totalRevenue = 0;
$revenueBreakdown = [];

// 1. Hospital bills revenue (paid_amount)
$hospitalRevenue = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(paid_amount), 0) 
    FROM billing 
    WHERE bill_date BETWEEN ? AND ? 
    AND paid_amount > 0
", [$startDate, $endDate]);

$revenueBreakdown['hospital'] = [
    'name' => 'Hospital Services',
    'revenue' => $hospitalRevenue,
    'color' => '#3b82f6'
];

// 2. Lab orders revenue
$labRevenue = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(paid_amount), 0)
    FROM lab_orders 
    WHERE order_date BETWEEN ? AND ?
    AND paid_amount > 0
", [$startDate, $endDate]);

$revenueBreakdown['lab'] = [
    'name' => 'Laboratory',
    'revenue' => $labRevenue,
    'color' => '#f59e0b'
];

// 3. Pharmacy revenue
mysqli_select_db($link, "hospital");
$pharmacyQuery = "
    SELECT COALESCE(SUM(paid_amount), 0) as pharmacy_revenue
    FROM pharmacy_billing 
    WHERE date BETWEEN '$startDate' AND '$endDate'
    AND paid_amount > 0
";
$pharmacyResult = mysqli_query($link, $pharmacyQuery);
$pharmacyRevenue = 0;
if ($pharmacyResult) {
    $row = mysqli_fetch_assoc($pharmacyResult);
    $pharmacyRevenue = $row['pharmacy_revenue'] ?? 0;
}

$revenueBreakdown['pharmacy'] = [
    'name' => 'Pharmacy',
    'revenue' => $pharmacyRevenue,
    'color' => '#8b5cf6'
];

// 4. Consultation revenue
$consultationRevenue = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(bi.total_amount), 0)
    FROM billing b
    INNER JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.bill_date BETWEEN ? AND ?
    AND b.paid_amount > 0
    AND bi.item_type = 'consultation'
", [$startDate, $endDate]);

$revenueBreakdown['consultation'] = [
    'name' => 'Consultation',
    'revenue' => $consultationRevenue,
    'color' => '#10b981'
];

// 5. Admission revenue
$admissionRevenue = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(bi.total_amount), 0)
    FROM billing b
    INNER JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.bill_date BETWEEN ? AND ?
    AND b.paid_amount > 0
    AND bi.item_type = 'bed_charge'
", [$startDate, $endDate]);

$revenueBreakdown['admission'] = [
    'name' => 'Admission',
    'revenue' => $admissionRevenue,
    'color' => '#db2777'
];

// Calculate total revenue
foreach ($revenueBreakdown as $breakdown) {
    $totalRevenue += $breakdown['revenue'];
}

// Get total expenses (from purchase_master and other costs)
$totalExpenses = 0;
$expenseBreakdown = [];

// 1. Purchase expenses
$purchaseExpenses = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(price * quantity), 0)
    FROM purchase_master 
    WHERE status = 'received'
    AND purchase_date BETWEEN ? AND ?
", [$startDate, $endDate]);

$expenseBreakdown['purchases'] = [
    'name' => 'Inventory Purchases',
    'amount' => $purchaseExpenses,
    'color' => '#ef4444'
];

// 2. Staff salaries (you'll need to adjust this based on your payroll table)
$salaryExpenses = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(net_salary), 0)
    FROM payroll 
    WHERE month BETWEEN ? AND ?
    AND status = 'paid'
", [$startDate, $endDate]);

$expenseBreakdown['salaries'] = [
    'name' => 'Staff Salaries',
    'amount' => $salaryExpenses,
    'color' => '#f97316'
];

// 3. Utility expenses (adjust based on your utilities table)
$utilityExpenses = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(amount), 0)
    FROM utility_bills 
    WHERE bill_date BETWEEN ? AND ?
    AND status = 'paid'
", [$startDate, $endDate]);

$expenseBreakdown['utilities'] = [
    'name' => 'Utilities',
    'amount' => $utilityExpenses,
    'color' => '#06b6d4'
];

// Calculate total expenses
foreach ($expenseBreakdown as $expense) {
    $totalExpenses += $expense['amount'];
}

// Get outstanding amounts
$totalOutstanding = 0;
$outstandingBreakdown = [];

// 1. Hospital outstanding
$hospitalOutstanding = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(balance_amount), 0)
    FROM billing 
    WHERE bill_date BETWEEN ? AND ?
    AND balance_amount > 0
", [$startDate, $endDate]);

$outstandingBreakdown['hospital'] = [
    'name' => 'Hospital Services',
    'amount' => $hospitalOutstanding,
    'color' => '#3b82f6'
];

// 2. Lab outstanding
$labOutstanding = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(total_amount - paid_amount), 0)
    FROM lab_orders 
    WHERE order_date BETWEEN ? AND ?
    AND payment_status IN ('unpaid', 'partial')
    AND (total_amount - paid_amount) > 0
", [$startDate, $endDate]);

$outstandingBreakdown['lab'] = [
    'name' => 'Laboratory',
    'amount' => $labOutstanding,
    'color' => '#f59e0b'
];

// 3. Pharmacy outstanding
$pharmacyQuery = "
    SELECT COALESCE(SUM(balance_amount), 0) as pharmacy_outstanding
    FROM pharmacy_billing 
    WHERE date BETWEEN '$startDate' AND '$endDate'
    AND COALESCE(balance_amount, 0) > 0
";
$pharmacyResult = mysqli_query($link, $pharmacyQuery);
$pharmacyOutstanding = 0;
if ($pharmacyResult) {
    $row = mysqli_fetch_assoc($pharmacyResult);
    $pharmacyOutstanding = $row['pharmacy_outstanding'] ?? 0;
}

$outstandingBreakdown['pharmacy'] = [
    'name' => 'Pharmacy',
    'amount' => $pharmacyOutstanding,
    'color' => '#8b5cf6'
];

// Calculate total outstanding
foreach ($outstandingBreakdown as $outstanding) {
    $totalOutstanding += $outstanding['amount'];
}

// Get payment methods breakdown
$paymentMethods = fetchData($pdo, "
    SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(paid_amount) as total_amount
    FROM billing 
    WHERE bill_date BETWEEN ? AND ?
    AND paid_amount > 0
    GROUP BY payment_method
    UNION ALL
    SELECT 
        'cash' as payment_method,
        COUNT(*) as transaction_count,
        SUM(paid_amount) as total_amount
    FROM lab_orders 
    WHERE order_date BETWEEN ? AND ?
    AND paid_amount > 0
    GROUP BY 'cash'
    UNION ALL
    SELECT 
        COALESCE(bill_type, 'cash') as payment_method,
        COUNT(*) as transaction_count,
        SUM(paid_amount) as total_amount
    FROM pharmacy_billing 
    WHERE date BETWEEN ? AND ?
    AND paid_amount > 0
    GROUP BY COALESCE(bill_type, 'cash')
", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

// Consolidate payment methods
$consolidatedPayments = [];
foreach ($paymentMethods as $payment) {
    $method = strtolower($payment['payment_method']);
    if (!isset($consolidatedPayments[$method])) {
        $consolidatedPayments[$method] = [
            'method' => ucfirst($payment['payment_method']),
            'transactions' => 0,
            'amount' => 0
        ];
    }
    $consolidatedPayments[$method]['transactions'] += $payment['transaction_count'];
    $consolidatedPayments[$method]['amount'] += $payment['total_amount'];
}

// Get top services by revenue
$topServices = fetchData($pdo, "
    SELECT 
        'consultation' as service_type,
        'Doctor Consultation' as service_name,
        SUM(bi.total_amount) as revenue,
        COUNT(DISTINCT b.id) as bill_count
    FROM billing b
    INNER JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.bill_date BETWEEN ? AND ?
    AND b.paid_amount > 0
    AND bi.item_type = 'consultation'
    
    UNION ALL
    
    SELECT 
        'lab' as service_type,
        'Laboratory Tests' as service_name,
        SUM(lo.paid_amount) as revenue,
        COUNT(DISTINCT lo.id) as bill_count
    FROM lab_orders lo
    WHERE lo.order_date BETWEEN ? AND ?
    AND lo.paid_amount > 0
    
    UNION ALL
    
    SELECT 
        'pharmacy' as service_type,
        'Pharmacy Sales' as service_name,
        SUM(pb.paid_amount) as revenue,
        COUNT(DISTINCT pb.id) as bill_count
    FROM pharmacy_billing pb
    WHERE pb.date BETWEEN ? AND ?
    AND pb.paid_amount > 0
    
    UNION ALL
    
    SELECT 
        'admission' as service_type,
        'Admission Charges' as service_name,
        SUM(bi.total_amount) as revenue,
        COUNT(DISTINCT b.id) as bill_count
    FROM billing b
    INNER JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.bill_date BETWEEN ? AND ?
    AND b.paid_amount > 0
    AND bi.item_type = 'bed_charge'
    
    UNION ALL
    
    SELECT 
        'procedure' as service_type,
        'Medical Procedures' as service_name,
        SUM(bi.total_amount) as revenue,
        COUNT(DISTINCT b.id) as bill_count
    FROM billing b
    INNER JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.bill_date BETWEEN ? AND ?
    AND b.paid_amount > 0
    AND bi.item_type = 'procedure'
    
    ORDER BY revenue DESC
    LIMIT 10
", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

// Get daily revenue trend
$dailyRevenue = fetchData($pdo, "
    SELECT 
        DATE(bill_date) as date,
        SUM(paid_amount) as daily_revenue
    FROM billing 
    WHERE bill_date BETWEEN ? AND ?
    AND paid_amount > 0
    GROUP BY DATE(bill_date)
    ORDER BY date
", [$startDate, $endDate]);

// Get top patients by spending
$topPatients = fetchData($pdo, "
    SELECT 
        p.id,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.phone,
        COALESCE(SUM(b.paid_amount), 0) as total_spent,
        COUNT(DISTINCT b.id) as bill_count
    FROM patients p
    LEFT JOIN billing b ON p.id = b.patient_id
    WHERE b.bill_date BETWEEN ? AND ?
    AND b.paid_amount > 0
    GROUP BY p.id
    ORDER BY total_spent DESC
    LIMIT 10
", [$startDate, $endDate]);

// Calculate profit metrics
$netProfit = $totalRevenue - $totalExpenses;
$profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue * 100) : 0;

// Get growth metrics (compare with previous period)
$previousStartDate = date('Y-m-d', strtotime($startDate . ' -1 month'));
$previousEndDate = date('Y-m-d', strtotime($endDate . ' -1 month'));

$previousRevenue = fetchSingleValue($pdo, "
    SELECT COALESCE(SUM(paid_amount), 0)
    FROM billing 
    WHERE bill_date BETWEEN ? AND ?
    AND paid_amount > 0
", [$previousStartDate, $previousEndDate]);

$revenueGrowth = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue * 100) : 100;


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Summary Report - Equity Afia</title>
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
            padding: 1rem;
        }
        
       .container {
    max-width: 1440px;
    padding: 30px 24px;
    display: flex;
    flex-direction: column;
    gap: 50px;   /* 🔥 THIS SOLVES IT */
}

        .report-header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .report-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-header p {
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .date-range {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        
        .date-input {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .date-input label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .date-input input {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(6, 182, 212, 0.2);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
            transition: transform 0.2s;
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
        }
        
        .summary-card.revenue { border-left-color: var(--success); }
        .summary-card.expenses { border-left-color: var(--danger); }
        .summary-card.profit { border-left-color: var(--primary); }
        .summary-card.outstanding { border-left-color: var(--warning); }
        .summary-card.margin { border-left-color: var(--secondary); }
        .summary-card.growth { border-left-color: #8b5cf6; }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .summary-change {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            display: inline-block;
        }
        
        .positive { background: #dcfce7; color: var(--success); }
        .negative { background: #fee2e2; color: var(--danger); }
        .neutral { background: #e5e7eb; color: var(--gray); }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (min-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .chart-card h3 {
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .tables-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .table-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1rem 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table-header h3 {
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-body {
            max-height: 400px;
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8fafc;
            position: sticky;
            top: 0;
        }
        
        th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e5e7eb;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .amount {
            font-weight: 600;
            text-align: right;
        }
        
        .revenue-amount { color: var(--success); }
        .expense-amount { color: var(--danger); }
        .outstanding-amount { color: var(--warning); }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success { background: #dcfce7; color: var(--success); }
        .badge-warning { background: #fef3c7; color: var(--warning); }
        .badge-danger { background: #fee2e2; color: var(--danger); }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .kpi-card {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            text-align: center;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .kpi-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0.5rem 0;
        }
        
        .kpi-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .date-range {
                flex-direction: column;
                align-items: stretch;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .table-body {
                max-height: 300px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="report-header">
            <h1><i class="fas fa-chart-line"></i> Financial Summary Report</h1>
            <p>Comprehensive financial analysis across all departments and services</p>
            
            <form method="GET" action="" class="date-range">
                <div class="date-input">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" required>
                </div>
                <div class="date-input">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" class="btn btn-secondary">
                    <i class="fas fa-calendar"></i> This Month
                </a>
                <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
            </form>
        </div>
        
        <!-- Summary Stats -->
        <div class="summary-grid">
            <div class="summary-card revenue">
                <div class="summary-value">Ksh <?php echo number_format($totalRevenue, 2); ?></div>
                <div class="summary-label">Total Revenue</div>
                <span class="summary-change <?php echo $revenueGrowth >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $revenueGrowth >= 0 ? '+' : ''; ?><?php echo number_format($revenueGrowth, 1); ?>% vs last period
                </span>
            </div>
            
            <div class="summary-card expenses">
                <div class="summary-value">Ksh <?php echo number_format($totalExpenses, 2); ?></div>
                <div class="summary-label">Total Expenses</div>
                <div style="font-size: 0.75rem; color: var(--gray);">
                    Purchases: Ksh <?php echo number_format($purchaseExpenses, 2); ?>
                </div>
            </div>
            
            <div class="summary-card profit">
                <div class="summary-value">Ksh <?php echo number_format($netProfit, 2); ?></div>
                <div class="summary-label">Net Profit</div>
                <span class="summary-change <?php echo $netProfit >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $netProfit >= 0 ? '✓ Profit' : '✗ Loss'; ?>
                </span>
            </div>
            
            <div class="summary-card outstanding">
                <div class="summary-value">Ksh <?php echo number_format($totalOutstanding, 2); ?></div>
                <div class="summary-label">Total Outstanding</div>
                <div style="font-size: 0.75rem; color: var(--warning);">
                    Uncollected payments
                </div>
            </div>
            
            <div class="summary-card margin">
                <div class="summary-value"><?php echo number_format($profitMargin, 1); ?>%</div>
                <div class="summary-label">Profit Margin</div>
                <div style="font-size: 0.75rem; color: var(--gray);">
                    <?php echo $profitMargin > 20 ? 'Excellent' : ($profitMargin > 10 ? 'Good' : 'Needs attention'); ?>
                </div>
            </div>
            
            <div class="summary-card growth">
                <div class="summary-value"><?php echo number_format($revenueGrowth, 1); ?>%</div>
                <div class="summary-label">Revenue Growth</div>
                <span class="summary-change <?php echo $revenueGrowth >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $revenueGrowth >= 0 ? '↑ Growth' : '↓ Decline'; ?>
                </span>
            </div>
        </div>
        
        <!-- Key Performance Indicators -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <i class="fas fa-money-bill-wave" style="color: var(--success);"></i>
                <div class="kpi-value"><?php echo count($consolidatedPayments); ?></div>
                <div class="kpi-label">Payment Methods</div>
            </div>
            <div class="kpi-card">
                <i class="fas fa-hospital" style="color: var(--primary);"></i>
                <div class="kpi-value"><?php echo count($topServices); ?></div>
                <div class="kpi-label">Top Services</div>
            </div>
            <div class="kpi-card">
                <i class="fas fa-users" style="color: var(--secondary);"></i>
                <div class="kpi-value"><?php echo count($topPatients); ?></div>
                <div class="kpi-label">Top Patients</div>
            </div>
            <div class="kpi-card">
                <i class="fas fa-calendar-alt" style="color: var(--warning);"></i>
                <div class="kpi-value"><?php echo count($dailyRevenue); ?></div>
                <div class="kpi-label">Days Analyzed</div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Revenue Breakdown Chart -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Revenue Breakdown</h3>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="legend">
                    <?php foreach ($revenueBreakdown as $item): ?>
                        <?php if ($item['revenue'] > 0): ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: <?php echo $item['color']; ?>"></div>
                            <span><?php echo $item['name']; ?>: Ksh <?php echo number_format($item['revenue'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Daily Revenue Trend -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Daily Revenue Trend</h3>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="tables-grid">
            <!-- Top Services Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-star"></i> Top Services by Revenue</h3>
                </div>
                <div class="table-body">
                    <?php if (count($topServices) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Revenue</th>
                                <th>Bills</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topServices as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td class="amount revenue-amount">Ksh <?php echo number_format($service['revenue'], 2); ?></td>
                                <td><?php echo $service['bill_count']; ?></td>
                                <td>
                                    <?php 
                                    $share = $totalRevenue > 0 ? ($service['revenue'] / $totalRevenue * 100) : 0;
                                    echo number_format($share, 1); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h3>No Service Data</h3>
                        <p>No service revenue in selected period.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Methods Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                </div>
                <div class="table-body">
                    <?php if (count($consolidatedPayments) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Transactions</th>
                                <th>Amount</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consolidatedPayments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['method']); ?></td>
                                <td><?php echo $payment['transactions']; ?></td>
                                <td class="amount revenue-amount">Ksh <?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $share = $totalRevenue > 0 ? ($payment['amount'] / $totalRevenue * 100) : 0;
                                    echo number_format($share, 1); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-credit-card"></i>
                        <h3>No Payment Data</h3>
                        <p>No payment transactions in selected period.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Patients Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-users"></i> Top Patients by Spending</h3>
                </div>
                <div class="table-body">
                    <?php if (count($topPatients) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>ID</th>
                                <th>Phone</th>
                                <th>Spent</th>
                                <th>Bills</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPatients as $patient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                <td class="amount revenue-amount">Ksh <?php echo number_format($patient['total_spent'], 2); ?></td>
                                <td><?php echo $patient['bill_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Patient Data</h3>
                        <p>No patient spending in selected period.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Outstanding Breakdown -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Outstanding by Department</h3>
                </div>
                <div class="table-body">
                    <?php if (count($outstandingBreakdown) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Amount</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outstandingBreakdown as $outstanding): ?>
                            <?php if ($outstanding['amount'] > 0): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($outstanding['name']); ?></td>
                                <td class="amount outstanding-amount">Ksh <?php echo number_format($outstanding['amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $share = $totalOutstanding > 0 ? ($outstanding['amount'] / $totalOutstanding * 100) : 0;
                                    echo number_format($share, 1); ?>%
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Outstanding</h3>
                        <p>All payments collected.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=excel" 
               class="btn btn-primary">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
            <a href="billing_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-dashboard"></i> Back to Dashboard
            </a>
            <a href="print_report.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               target="_blank"
               class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Report
            </a>
            <button onclick="window.location.reload()" class="btn btn-secondary">
                <i class="fas fa-sync"></i> Refresh Data
            </button>
        </div>
    </div>

    <script>
        // Revenue Breakdown Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        
        const revenueLabels = [];
        const revenueData = [];
        const revenueColors = [];
        
        <?php foreach ($revenueBreakdown as $item): ?>
            <?php if ($item['revenue'] > 0): ?>
            revenueLabels.push('<?php echo $item['name']; ?>');
            revenueData.push(<?php echo $item['revenue']; ?>);
            revenueColors.push('<?php echo $item['color']; ?>');
            <?php endif; ?>
        <?php endforeach; ?>
        
        const revenueChart = new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: revenueLabels,
                datasets: [{
                    data: revenueData,
                    backgroundColor: revenueColors,
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: Ksh ${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Daily Revenue Trend Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        
        const dailyLabels = [];
        const dailyData = [];
        
        <?php foreach ($dailyRevenue as $day): ?>
        dailyLabels.push('<?php echo date('M d', strtotime($day['date'])); ?>');
        dailyData.push(<?php echo $day['daily_revenue']; ?>);
        <?php endforeach; ?>
        
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Daily Revenue',
                    data: dailyData,
                    borderColor: '#0891b2',
                    backgroundColor: 'rgba(8, 145, 178, 0.1)',
                    fill: true,
                    tension: 0.4
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
                                return `Revenue: Ksh ${context.parsed.y.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(6, 182, 212, 0.1)'
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

        // Auto-refresh every 5 minutes
        setInterval(() => {
            // Check if user is active
            if (!document.hidden) {
                window.location.reload();
            }
        }, 300000); // 5 minutes

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>