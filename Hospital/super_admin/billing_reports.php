<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

// if (!isset($_SESSION['username'])) {
//     header("Location: login.php");
//     exit();
// }

// Initialize variables
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'overview';
$comparison_period = $_GET['comparison'] ?? 'none';

// Initialize data arrays
$revenue_data = [
    'consultation' => ['total' => 0, 'paid' => 0, 'pending' => 0, 'count' => 0],
    'pharmacy' => ['total' => 0, 'paid' => 0, 'pending' => 0, 'count' => 0],
    'lab' => ['total' => 0, 'paid' => 0, 'pending' => 0, 'count' => 0],
    'admission' => ['total' => 0, 'paid' => 0, 'pending' => 0, 'count' => 0]
];

$daily_revenue = [];
$payment_methods = [];
$top_services = [];
$department_revenue = [];

try {
    // Fetch revenue by service type
    $stmt = $pdo->prepare("
        SELECT 
            service_type,
            COUNT(*) as bill_count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(paid_amount), 0) as paid_amount,
            COALESCE(SUM(balance_amount), 0) as pending_amount
        FROM consolidated_patient_bills
        WHERE bill_date BETWEEN ? AND ?
        GROUP BY service_type
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['service_type'];
        if (isset($revenue_data[$type])) {
            $revenue_data[$type] = [
                'total' => $row['total_revenue'],
                'paid' => $row['paid_amount'],
                'pending' => $row['pending_amount'],
                'count' => $row['bill_count']
            ];
        }
    }

    // Fetch daily revenue trend (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(bill_date) as date,
            service_type,
            COALESCE(SUM(total_amount), 0) as amount
        FROM consolidated_patient_bills
        WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(bill_date), service_type
        ORDER BY date ASC
    ");
    $stmt->execute();
    
    $daily_temp = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $date = $row['date'];
        if (!isset($daily_temp[$date])) {
            $daily_temp[$date] = ['consultation' => 0, 'pharmacy' => 0, 'lab' => 0, 'admission' => 0];
        }
        $daily_temp[$date][$row['service_type']] = floatval($row['amount']);
    }
    
    foreach ($daily_temp as $date => $amounts) {
        $daily_revenue[] = [
            'date' => $date,
            'consultation' => $amounts['consultation'],
            'pharmacy' => $amounts['pharmacy'],
            'lab' => $amounts['lab'],
            'admission' => $amounts['admission']
        ];
    }

    // Fetch payment methods distribution
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(total_amount), 0) as total_amount
        FROM consolidated_patient_bills
        WHERE bill_date BETWEEN ? AND ?
            AND payment_method IS NOT NULL
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch top revenue generating services
    $stmt = $pdo->prepare("
        SELECT 
            service_item,
            item_type,
            COUNT(*) as usage_count,
            COALESCE(SUM(item_amount), 0) as total_revenue
        FROM detailed_patient_bills
        WHERE bill_date BETWEEN ? AND ?
        GROUP BY service_item, item_type
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $top_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $grand_total = array_sum(array_column($revenue_data, 'total'));
    $grand_paid = array_sum(array_column($revenue_data, 'paid'));
    $grand_pending = array_sum(array_column($revenue_data, 'pending'));
    $total_transactions = array_sum(array_column($revenue_data, 'count'));

} catch (Exception $e) {
    error_log("Error in financial reports: " . $e->getMessage());
}

// Calculate growth rates if comparison is enabled
$growth_rates = [];
if ($comparison_period !== 'none') {
    try {
        $interval = $comparison_period === 'previous_period' ? 
            "INTERVAL " . (strtotime($end_date) - strtotime($start_date)) . " SECOND" :
            "INTERVAL 1 YEAR";
            
        $stmt = $pdo->prepare("
            SELECT 
                service_type,
                COALESCE(SUM(total_amount), 0) as amount
            FROM consolidated_patient_bills
            WHERE bill_date BETWEEN DATE_SUB(?, $interval) AND DATE_SUB(?, $interval)
            GROUP BY service_type
        ");
        $stmt->execute([$start_date, $end_date]);
        
        $previous_data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $previous_data[$row['service_type']] = floatval($row['amount']);
        }
        
        foreach ($revenue_data as $type => $data) {
            $previous = $previous_data[$type] ?? 0;
            if ($previous > 0) {
                $growth_rates[$type] = (($data['total'] - $previous) / $previous) * 100;
            }
        }
    } catch (Exception $e) {
        error_log("Error calculating growth rates: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports & Analytics - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #0d7377;
            --secondary-color: #14FFEC;
            --dark-navy: #003ea8;
            --light-teal: #32e0c4;
            --background: #f0f4f8;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--background);
            color: var(--text-primary);
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-navy));
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .filters-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 115, 119, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #0a5d61;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 115, 119, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
        }

        .stat-card.consultation::before { background: var(--primary-color); }
        .stat-card.pharmacy::before { background: #8b5cf6; }
        .stat-card.lab::before { background: #f59e0b; }
        .stat-card.admission::before { background: #ef4444; }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-card.consultation .stat-card-icon { background: var(--primary-color); }
        .stat-card.pharmacy .stat-card-icon { background: #8b5cf6; }
        .stat-card.lab .stat-card-icon { background: #f59e0b; }
        .stat-card.admission .stat-card-icon { background: #ef4444; }

        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0.5rem 0;
        }

        .stat-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .stat-growth {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-growth.positive { color: var(--success); }
        .stat-growth.negative { color: var(--danger); }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            background: linear-gradient(to right, rgba(13, 115, 119, 0.05), transparent);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title i {
            color: var(--primary-color);
        }

        .card-body {
            padding: 1.5rem;
        }

        .chart-container {
            height: 350px;
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
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: linear-gradient(to right, rgba(13, 115, 119, 0.05), transparent);
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 0.95rem;
        }

        tr:hover {
            background: rgba(13, 115, 119, 0.02);
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-consultation { background: rgba(13, 115, 119, 0.1); color: var(--primary-color); }
        .badge-pharmacy { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .badge-lab { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge-admission { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 1rem;
        }

        .export-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media print {
            .filters-card,
            .export-actions,
            .btn {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-line"></i> Financial Reports & Analytics</h1>
            <p>Comprehensive financial insights and revenue analysis</p>
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
                
                <!-- <div class="form-group">
                    <label for="comparison">Comparison</label>
                    <select class="form-control" id="comparison" name="comparison">
                        <option value="none" <?php echo $comparison_period == 'none' ? 'selected' : ''; ?>>None</option>
                        <option value="previous_period" <?php echo $comparison_period == 'previous_period' ? 'selected' : ''; ?>>Previous Period</option>
                        <option value="last_year" <?php echo $comparison_period == 'last_year' ? 'selected' : ''; ?>>Same Period Last Year</option>
                    </select>
                </div> -->
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card consultation">
                <div class="stat-card-header">
                    <h3>Consultation Revenue</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <div class="stat-value">Ksh <?php echo number_format($revenue_data['consultation']['total'], 2); ?></div>
                <div class="stat-details">
                    <span>Paid: Ksh <?php echo number_format($revenue_data['consultation']['paid'], 2); ?></span>
                    <span><?php echo number_format($revenue_data['consultation']['count']); ?> bills</span>
                </div>
                <?php if (isset($growth_rates['consultation'])): ?>
                <div class="stat-growth <?php echo $growth_rates['consultation'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_rates['consultation'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs(round($growth_rates['consultation'], 1)); ?>% vs comparison period
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card pharmacy">
                <div class="stat-card-header">
                    <h3>Pharmacy Revenue</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                </div>
                <div class="stat-value">Ksh <?php echo number_format($revenue_data['pharmacy']['total'], 2); ?></div>
                <div class="stat-details">
                    <span>Paid: Ksh <?php echo number_format($revenue_data['pharmacy']['paid'], 2); ?></span>
                    <span><?php echo number_format($revenue_data['pharmacy']['count']); ?> bills</span>
                </div>
                <?php if (isset($growth_rates['pharmacy'])): ?>
                <div class="stat-growth <?php echo $growth_rates['pharmacy'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_rates['pharmacy'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs(round($growth_rates['pharmacy'], 1)); ?>% vs comparison period
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card lab">
                <div class="stat-card-header">
                    <h3>Laboratory Revenue</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                </div>
                <div class="stat-value">Ksh <?php echo number_format($revenue_data['lab']['total'], 2); ?></div>
                <div class="stat-details">
                    <span>Paid: Ksh <?php echo number_format($revenue_data['lab']['paid'], 2); ?></span>
                    <span><?php echo number_format($revenue_data['lab']['count']); ?> bills</span>
                </div>
                <?php if (isset($growth_rates['lab'])): ?>
                <div class="stat-growth <?php echo $growth_rates['lab'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_rates['lab'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs(round($growth_rates['lab'], 1)); ?>% vs comparison period
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card admission">
                <div class="stat-card-header">
                    <h3>Admission Revenue</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-bed"></i>
                    </div>
                </div>
                <div class="stat-value">Ksh <?php echo number_format($revenue_data['admission']['total'], 2); ?></div>
                <div class="stat-details">
                    <span>Paid: Ksh <?php echo number_format($revenue_data['admission']['paid'], 2); ?></span>
                    <span><?php echo number_format($revenue_data['admission']['count']); ?> bills</span>
                </div>
                <?php if (isset($growth_rates['admission'])): ?>
                <div class="stat-growth <?php echo $growth_rates['admission'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_rates['admission'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs(round($growth_rates['admission'], 1)); ?>% vs comparison period
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Charts Section -->
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
                        Payment Methods
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="paymentMethodsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-chart-area"></i>
                    30-Day Revenue Trend
                </h2>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dailyRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Services Table -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-trophy"></i>
                    Top Revenue Generating Services
                </h2>
            </div>
            <div class="card-body">
                <?php if (count($top_services) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Service/Item</th>
                                <th>Category</th>
                                <th class="text-center">Usage Count</th>
                                <th style="text-align: right;">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_services as $index => $service): ?>
                            <tr>
                                <td><strong>#<?php echo $index + 1; ?></strong></td>
                                <td><?php echo htmlspecialchars($service['service_item']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $service['item_type']; ?>">
                                        <?php echo ucfirst($service['item_type']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;"><?php echo number_format($service['usage_count']); ?></td>
                                <td style="text-align: right;">
                                    <strong>Ksh <?php echo number_format($service['total_revenue'], 2); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Data Available</h3>
                    <p>No service data found for the selected period.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Revenue Distribution Chart
        const revenueCtx = document.getElementById('revenueDistributionChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Consultation', 'Pharmacy', 'Laboratory', 'Admission'],
                datasets: [{
                    data: [
                        <?php echo $revenue_data['consultation']['total']; ?>,
                        <?php echo $revenue_data['pharmacy']['total']; ?>,
                        <?php echo $revenue_data['lab']['total']; ?>,
                        <?php echo $revenue_data['admission']['total']; ?>
                    ],
                    backgroundColor: ['#0d7377', '#8b5cf6', '#f59e0b', '#ef4444'],
                    borderWidth: 0
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
                            usePointStyle: true,
                            font: { size: 12, weight: '600' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${context.label}: Ksh ${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($pm) { return "'" . ucfirst($pm['payment_method']) . "'"; }, $payment_methods)); ?>],
                datasets: [{
                    label: 'Revenue',
                    data: [<?php echo implode(',', array_column($payment_methods, 'total_amount')); ?>],
                    backgroundColor: '#0d7377',
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Ksh ' + context.parsed.y.toLocaleString();
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
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // Daily Revenue Trend Chart
        const dailyCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($d) { return "'" . date('M j', strtotime($d['date'])) . "'"; }, $daily_revenue)); ?>],
                datasets: [
                    {
                        label: 'Consultation',
                        data: [<?php echo implode(',', array_column($daily_revenue, 'consultation')); ?>],
                        borderColor: '#0d7377',
                        backgroundColor: 'rgba(13, 115, 119, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Pharmacy',
                        data: [<?php echo implode(',', array_column($daily_revenue, 'pharmacy')); ?>],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Laboratory',
                        data: [<?php echo implode(',', array_column($daily_revenue, 'lab')); ?>],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Admission',
                        data: [<?php echo implode(',', array_column($daily_revenue, 'admission')); ?>],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 11, weight: '600' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Ksh ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: false,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // Export Report Function
        function exportReport() {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Header
            csvContent += "Equity Afia Hospital - Financial Report\n";
            csvContent += `Period: ${startDate} to ${endDate}\n`;
            csvContent += `Generated: ${new Date().toLocaleString()}\n\n`;
            
            // Summary Statistics
            csvContent += "REVENUE SUMMARY\n";
            csvContent += "Service Type,Total Revenue,Paid Amount,Pending Amount,Bill Count\n";
            csvContent += `Consultation,<?php echo $revenue_data['consultation']['total']; ?>,<?php echo $revenue_data['consultation']['paid']; ?>,<?php echo $revenue_data['consultation']['pending']; ?>,<?php echo $revenue_data['consultation']['count']; ?>\n`;
            csvContent += `Pharmacy,<?php echo $revenue_data['pharmacy']['total']; ?>,<?php echo $revenue_data['pharmacy']['paid']; ?>,<?php echo $revenue_data['pharmacy']['pending']; ?>,<?php echo $revenue_data['pharmacy']['count']; ?>\n`;
            csvContent += `Laboratory,<?php echo $revenue_data['lab']['total']; ?>,<?php echo $revenue_data['lab']['paid']; ?>,<?php echo $revenue_data['lab']['pending']; ?>,<?php echo $revenue_data['lab']['count']; ?>\n`;
            csvContent += `Admission,<?php echo $revenue_data['admission']['total']; ?>,<?php echo $revenue_data['admission']['paid']; ?>,<?php echo $revenue_data['admission']['pending']; ?>,<?php echo $revenue_data['admission']['count']; ?>\n`;
            csvContent += `\nTOTAL,<?php echo $grand_total; ?>,<?php echo $grand_paid; ?>,<?php echo $grand_pending; ?>,<?php echo $total_transactions; ?>\n\n`;
            
            // Payment Methods
            csvContent += "PAYMENT METHODS\n";
            csvContent += "Method,Transaction Count,Total Amount\n";
            <?php foreach ($payment_methods as $pm): ?>
            csvContent += "<?php echo ucfirst($pm['payment_method']); ?>,<?php echo $pm['transaction_count']; ?>,<?php echo $pm['total_amount']; ?>\n";
            <?php endforeach; ?>
            
            csvContent += "\n\nTOP REVENUE SERVICES\n";
            csvContent += "Rank,Service/Item,Category,Usage Count,Total Revenue\n";
            <?php foreach ($top_services as $index => $service): ?>
            csvContent += "<?php echo $index + 1; ?>,<?php echo str_replace(',', ';', $service['service_item']); ?>,<?php echo $service['item_type']; ?>,<?php echo $service['usage_count']; ?>,<?php echo $service['total_revenue']; ?>\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `financial_report_${startDate}_to_${endDate}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto-refresh charts on window resize
        window.addEventListener('resize', function() {
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.resize();
            });
        });
    </script>
</body>
</html>