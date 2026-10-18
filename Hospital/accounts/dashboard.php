<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Check if user has accounts department access
$user_role = $_SESSION['role'] ?? '';
$accounts_roles = ['Accountant', 'Accounts Officer', 'Super Admin', 'Admin'];
if (!in_array($user_role, $accounts_roles)) {
    die("Access denied. Accounts department only.");
}

// Get dates
$today = date('Y-m-d');
$current_month = date('Y-m');
$first_day_of_month = date('Y-m-01');
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$last_6_months = date('Y-m-d', strtotime('-6 months'));

// STATISTICS - Fetch from billing_payments table
$stats = [];

// 1. Today's Revenue
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as today_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE DATE(payment_date) = :today
        AND status = 'completed'
    ");
    $stmt->execute([':today' => $today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_revenue'] = $result['today_revenue'] ?? 0;
    $stats['today_payments'] = $result['payment_count'] ?? 0;
} catch (PDOException $e) {
    $stats['today_revenue'] = 0;
    $stats['today_payments'] = 0;
}

// 2. This Month's Revenue
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as month_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE payment_date >= :first_day
        AND status = 'completed'
    ");
    $stmt->execute([':first_day' => $first_day_of_month]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['month_revenue'] = $result['month_revenue'] ?? 0;
    $stats['month_payments'] = $result['payment_count'] ?? 0;
} catch (PDOException $e) {
    $stats['month_revenue'] = 0;
    $stats['month_payments'] = 0;
}

// 3. Total Revenue (All time)
try {
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(amount), 0) as total_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE status = 'completed'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_revenue'] = $result['total_revenue'] ?? 0;
    $stats['total_payments'] = $result['payment_count'] ?? 0;
} catch (PDOException $e) {
    $stats['total_revenue'] = 0;
    $stats['total_payments'] = 0;
}

// 4. Pending Payments (failed status)
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total
        FROM billing_payments 
        WHERE status = 'failed'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['failed_payments'] = $result['count'] ?? 0;
    $stats['failed_amount'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    $stats['failed_payments'] = 0;
    $stats['failed_amount'] = 0;
}

// 5. Refunded Payments
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total
        FROM billing_payments 
        WHERE status = 'refunded'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['refunded_payments'] = $result['count'] ?? 0;
    $stats['refunded_amount'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    $stats['refunded_payments'] = 0;
    $stats['refunded_amount'] = 0;
}

// 6. Revenue by Payment Method (Last 30 days)
$payment_methods_data = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM billing_payments 
        WHERE payment_date >= :thirty_days_ago
        AND status = 'completed'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute([':thirty_days_ago' => $thirty_days_ago]);
    $payment_methods_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payment_methods_data = [];
}

// 7. Revenue by Service Type (Today)
$today_service_revenue = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            service_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as revenue
        FROM billing_payments 
        WHERE DATE(payment_date) = :today
        AND status = 'completed'
        GROUP BY service_type
        ORDER BY revenue DESC
    ");
    $stmt->execute([':today' => $today]);
    $today_service_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $today_service_revenue = [];
}

// Calculate individual service revenues for today
$service_types = ['consultation' => 0, 'pharmacy' => 0, 'lab' => 0, 'admission' => 0, 'consolidated' => 0];
foreach ($today_service_revenue as $service) {
    if (isset($service_types[$service['service_type']])) {
        $service_types[$service['service_type']] = $service['revenue'];
    }
}

// 8. Monthly Revenue Trend (Last 6 months)
$monthly_revenue = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            COUNT(*) as payment_count,
            COALESCE(SUM(amount), 0) as total_revenue
        FROM billing_payments
        WHERE payment_date >= :six_months_ago
        AND status = 'completed'
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([':six_months_ago' => $last_6_months]);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in missing months
    $current = date('Y-m', strtotime('-5 months'));
    for ($i = 0; $i < 6; $i++) {
        $found = false;
        foreach ($monthly_data as $data) {
            if ($data['month'] == $current) {
                $monthly_revenue[] = $data;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $monthly_revenue[] = [
                'month' => $current,
                'payment_count' => 0,
                'total_revenue' => 0
            ];
        }
        $current = date('Y-m', strtotime($current . ' +1 month'));
    }
} catch (PDOException $e) {
    $monthly_revenue = [];
}

// 9. Top Patients by Revenue (Last 30 days)
$top_patients = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            patient_id,
            patient_name,
            COUNT(*) as payment_count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM billing_payments 
        WHERE payment_date >= :thirty_days_ago
        AND status = 'completed'
        GROUP BY patient_id, patient_name
        ORDER BY total_amount DESC
        LIMIT 8
    ");
    $stmt->execute([':thirty_days_ago' => $thirty_days_ago]);
    $top_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $top_patients = [];
}

// 10. Recent Transactions
$recent_transactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            receipt_number,
            patient_name,
            service_type,
            amount,
            payment_method,
            status,
            payment_date
        FROM billing_payments 
        WHERE status IN ('completed', 'failed', 'refunded')
        ORDER BY payment_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_transactions = [];
}

// 11. Average Transaction Value
$stats['avg_transaction_value'] = $stats['total_payments'] > 0 
    ? $stats['total_revenue'] / $stats['total_payments'] 
    : 0;

// 12. Success Rate
$stats['success_rate'] = $stats['total_payments'] > 0 
    ? (($stats['total_payments'] - ($stats['failed_payments'] + $stats['refunded_payments'])) / $stats['total_payments']) * 100
    : 0;

// Display date
$display_date = date('F j, Y');

$display_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Department Dashboard</title>
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
            --purple: #8b5cf6;
            --teal: #0d9488;
            --light: #f0fdfa;
            --dark: #134e4a;
            --gray: #64748b;
            --light-gray: #e2e8f0;
            --card-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
            font-weight: bold;
            font-size: 18px;
        }

        .dashboard-container {
            padding: 20px;
            margin-left: 300px;
            transition: all 0.3s;
            min-height: 100vh;
           
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .dashboard-container {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Header Styles */
        .dashboard-header {
            margin-bottom: 30px;
            background: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--primary);
            align-items: center;
        }

        .header-content h1 {
            font-size: clamp(24px, 4vw, 32px);
            color: var(--dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-content p {
            color: var(--gray);
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .header-stat {
            background: var(--light);
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 200px;
        }

        .header-stat i {
            font-size: 24px;
            color: var(--primary);
        }

        .header-stat-content h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .header-stat-content p {
            font-size: 13px;
            color: var(--gray);
            margin: 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            transition: all 0.3s;
            cursor: pointer;
            border-top: 4px solid var(--primary);
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 160px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-card-primary { border-top-color: var(--primary); }
        .stat-card-success { border-top-color: var(--success); }
        .stat-card-warning { border-top-color: var(--warning); }
        .stat-card-danger { border-top-color: var(--danger); }
        .stat-card-info { border-top-color: var(--accent); }
        .stat-card-purple { border-top-color: var(--purple); }
        .stat-card-teal { border-top-color: var(--teal); }

        .stat-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-text h3 {
            font-size: 14px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-value {
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        .stat-value small {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .stat-card-primary .stat-icon { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .stat-card-success .stat-icon { background: linear-gradient(135deg, var(--success) 0%, #059669 100%); }
        .stat-card-warning .stat-icon { background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%); }
        .stat-card-danger .stat-icon { background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); }
        .stat-card-info .stat-icon { background: linear-gradient(135deg, var(--accent) 0%, var(--primary-light) 100%); }
        .stat-card-purple .stat-icon { background: linear-gradient(135deg, var(--purple) 0%, #7c3aed 100%); }
        .stat-card-teal .stat-icon { background: linear-gradient(135deg, var(--teal) 0%, #0f766e 100%); }

        .stat-footer {
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--gray);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }

        .stat-trend.up { color: var(--success); }
        .stat-trend.down { color: var(--danger); }

        /* Service Type Breakdown */
        .service-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .service-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .service-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            background: var(--primary);
        }

        .service-card.consultation .service-icon { background: var(--primary); }
        .service-card.pharmacy .service-icon { background: var(--success); }
        .service-card.lab .service-icon { background: var(--warning); }
        .service-card.admission .service-icon { background: var(--purple); }
        .service-card.consolidated .service-icon { background: var(--teal); }

        .service-card h3 {
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .service-card .amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            border: 1px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .action-content h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .action-content p {
            font-size: 14px;
            color: var(--gray);
            margin: 0;
        }

        /* Content Sections */
        .content-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 500px), 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .section-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--light-gray);
        }

        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header .badge {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .section-content {
            flex: 1;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .table-responsive {
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--light-gray);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background-color 0.2s;
            cursor: pointer;
        }

        tbody tr:hover {
            background: var(--light);
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-completed { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .status-refunded { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .status-pending { background: rgba(148, 163, 184, 0.1); color: var(--gray); }

        .payment-method-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(8, 145, 178, 0.1);
            color: var(--primary);
        }

        .service-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .service-consultation { background: rgba(8, 145, 178, 0.1); color: var(--primary); }
        .service-pharmacy { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .service-lab { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .service-admission { background: rgba(139, 92, 246, 0.1); color: var(--purple); }
        .service-consolidated { background: rgba(13, 148, 136, 0.1); color: var(--teal); }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 450px), 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            padding: 20px;
            border: 1px solid var(--light-gray);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            height: 250px;
            position: relative;
        }

        /* View All Link */
        .view-all-link {
            display: block;
            padding: 15px;
            text-align: center;
            background: var(--light);
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border-top: 1px solid var(--light-gray);
        }

        .view-all-link:hover {
            background: var(--primary);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--light-gray);
            margin-bottom: 15px;
        }

        .empty-state h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--secondary);
        }

        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* Refresh Button */
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            font-size: 20px;
            transition: all 0.3s;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .refresh-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .header-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-stat {
                min-width: 100%;
            }
            
            .service-breakdown {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .content-sections {
                grid-template-columns: 1fr;
            }
            
            .refresh-btn {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Print Styles */
        @media print {
            .refresh-btn,
            .action-card,
            .view-all-link {
                display: none !important;
            }
            
            .stat-card:hover,
            .service-card:hover {
                transform: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 style="font-size: 24px; text-align: center; font-weight: bold; color: #55abd3;"><i class="fas fa-chart-line"></i> Accounts Department Dashboard</h1>
                <br>
                <p><i style ="font-size: 25px; font-weight: bold; color: #55abd3;" class="far fa-calendar-alt"></i> <?php echo $display_date; ?></p>
               <br>
                <p><i style ="font-size: 25px; font-weight: bold; color: #55abd3;" class="fas fa-clock"></i> <?php echo $display_time; ?></p>
            </div>
            <!-- <div class="header-stats">
                <div class="header-stat">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="header-stat-content">
                        <h3>Ksh <?php echo number_format($stats['today_revenue'], 0); ?></h3>
                        <p>Today's Revenue</p>
                    </div>
                </div>
                <div class="header-stat">
                    <i class="fas fa-calendar-month"></i>
                    <div class="header-stat-content">
                        <h3>Ksh <?php echo number_format($stats['month_revenue'], 0); ?></h3>
                        <p>Month's Revenue</p>
                    </div>
                </div>
                <div class="header-stat">
                    <i class="fas fa-chart-bar"></i>
                    <div class="header-stat-content">
                        <h3><?php echo number_format($stats['total_payments'], 0); ?></h3>
                        <p>Total Transactions</p>
                    </div>
                </div>
                <!-- <div class="header-stat">
                    <i class="fas fa-percentage"></i>
                    <div class="header-stat-content">
                        <h3><?php echo number_format($stats['success_rate'], 1); ?>%</h3>
                        <p>Success Rate</p>
                    </div>
                </div> -->
            <!-- </div> -->
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <!-- Today's Revenue -->
            <div class="stat-card stat-card-primary" onclick="window.location.href='reports.php?report=financial'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Today's Revenue</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['today_revenue'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['today_payments']); ?> payments</span>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>Today</span>
                    </div>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="stat-card stat-card-success" onclick="window.location.href='reports.php?report=revenue'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>This Month</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['month_revenue'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['month_payments']); ?> payments</span>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>Current month</span>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="stat-card stat-card-info" onclick="window.location.href='reports.php?report=financial'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Total Revenue</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['total_revenue'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['total_payments']); ?> payments</span>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>All time</span>
                    </div>
                </div>
            </div>

            <!-- Failed Payments -->
            <!-- <div class="stat-card stat-card-danger" onclick="window.location.href='billing_payments.php?status=failed'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Failed Payments</h3>
                        <div class="stat-value"><?php echo number_format($stats['failed_payments']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Ksh <?php echo number_format($stats['failed_amount'], 0); ?></span>
                    <div class="stat-trend down">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Requires action</span>
                    </div>
                </div>
            </div> -->

            <!-- Refunded Payments -->
            <!-- <div class="stat-card stat-card-warning" onclick="window.location.href='billing_payments.php?status=refunded'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Refunded Payments</h3>
                        <div class="stat-value"><?php echo number_format($stats['refunded_payments']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Ksh <?php echo number_format($stats['refunded_amount'], 0); ?></span>
                    <div class="stat-trend down">
                        <i class="fas fa-undo"></i>
                        <span>Refunded</span>
                    </div>
                </div>
            </div> -->

            <!-- Average Transaction -->
            <div class="stat-card stat-card-teal" onclick="window.location.href='reports.php?report=revenue'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Avg Transaction</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['avg_transaction_value'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Per payment</span>
                    <div class="stat-trend up">
                        <i class="fas fa-chart-pie"></i>
                        <span>Average value</span>
                    </div>
                </div>
            </div>

            <!-- Success Rate -->
            <!-- <div class="stat-card stat-card-purple">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Success Rate</h3>
                        <div class="stat-value"><?php echo number_format($stats['success_rate'], 1); ?>%</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Payment success</span>
                    <?php if ($stats['success_rate'] >= 95): ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>Excellent</span>
                        </div>
                    <?php elseif ($stats['success_rate'] >= 90): ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>Good</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-trend down">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Needs attention</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div> -->
        </div>

        <!-- Service Type Breakdown -->
        <div class="service-breakdown">
            <div class="service-card consultation">
                <div class="service-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Consultation</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['consultation'], 0); ?></div>
            </div>
            
            <div class="service-card pharmacy">
                <div class="service-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <h3>Pharmacy</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['pharmacy'], 0); ?></div>
            </div>
            
            <div class="service-card lab">
                <div class="service-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3>Laboratory</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['lab'], 0); ?></div>
            </div>
            
            <div class="service-card admission">
                <div class="service-icon">
                    <i class="fas fa-procedures"></i>
                </div>
                <h3>Admission</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['admission'], 0); ?></div>
            </div>
            
            <!-- <div class="service-card consolidated">
                <div class="service-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3>Consolidated</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['consolidated'], 0); ?></div>
            </div> -->
        </div>

        <!-- Quick Actions
        <div class="quick-actions">
            <a href="unified_payment.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="action-content">
                    <h3>Process Payment</h3>
                    <p>Record new payment</p>
                </div>
            </a>
            
            <a href="reports.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="action-content">
                    <h3>View Reports</h3>
                    <p>Financial analytics</p>
                </div>
            </a>
            
            <a href="billing_payments.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="action-content">
                    <h3>View Payments</h3>
                    <p>All billing payments</p>
                </div>
            </a>
            
            <a href="create_bill.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--purple) 0%, #7c3aed 100%);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="action-content">
                    <h3>Create Bill</h3>
                    <p>Generate new invoice</p>
                </div>
            </a>
        </div> -->

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Monthly Revenue Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Revenue Trend</h3>
                    <span class="badge">6 months</span>
                </div>
                <div class="chart-container">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>

            <!-- Payment Methods Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                    <span class="badge">Last 30 days</span>
                </div>
                <div class="chart-container">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Main Content Section - Recent Transactions & Top Patients -->
        <div class="content-sections">
            <!-- Recent Transactions -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-exchange-alt"></i> Recent Transactions</h2>
                    <span class="badge"><?php echo count($recent_transactions); ?> transactions</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_transactions) > 0): ?>
                                    <?php foreach ($recent_transactions as $tx): ?>
                                        <?php 
                                        $service_class = 'service-' . $tx['service_type'];
                                        $status_class = 'status-' . $tx['status'];
                                        ?>
                                        <tr onclick="window.location.href='view_payment.php?id=<?php echo $tx['id']; ?>'">
                                            <td>
                                                <strong><?php echo htmlspecialchars(substr($tx['receipt_number'], 0, 10)); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($tx['patient_name']); ?></td>
                                            <td>
                                                <span class="service-badge <?php echo $service_class; ?>">
                                                    <?php echo ucfirst($tx['service_type']); ?>
                                                </span>
                                            </td>
                                            <td><strong>Ksh <?php echo number_format($tx['amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="payment-method-badge">
                                                    <?php echo ucfirst($tx['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($tx['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-exchange-alt"></i>
                                            <h4>No Recent Transactions</h4>
                                            <p>No billing payments recorded yet</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="billing_module.php" class="view-all-link">
                        View All Transactions <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Top Patients -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Top Patients (30 Days)</h2>
                    <span class="badge"><?php echo count($top_patients); ?> patients</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>ID</th>
                                    <th>Payments</th>
                                    <th>Total</th>
                                    <th>Avg/Visit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($top_patients) > 0): ?>
                                    <?php foreach ($top_patients as $patient): 
                                        $avg = $patient['payment_count'] > 0 ? $patient['total_amount'] / $patient['payment_count'] : 0;
                                    ?>
                                    <tr onclick="window.location.href='patient_details.php?id=<?php echo $patient['patient_id']; ?>'">
                                        <td>
                                            <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                        </td>
                                        <td>#<?php echo $patient['patient_id']; ?></td>
                                        <td><?php echo number_format($patient['payment_count']); ?></td>
                                        <td><strong>Ksh <?php echo number_format($patient['total_amount'], 0); ?></strong></td>
                                        <td>Ksh <?php echo number_format($avg, 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-users"></i>
                                            <h4>No Patient Data</h4>
                                            <p>No billing data for last 30 days</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="reports.php?report=financial" class="view-all-link">
                        View Revenue Reports <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="refreshDashboard()" title="Refresh Dashboard">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Revenue Chart
            const monthlyCtx = document.getElementById('monthlyRevenueChart');
            if (monthlyCtx) {
                const monthlyData = <?php echo json_encode($monthly_revenue); ?>;
                
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(m => {
                            const date = new Date(m.month + '-01');
                            return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
                        }),
                        datasets: [{
                            label: 'Revenue (Ksh)',
                            data: monthlyData.map(m => m.total_revenue),
                            backgroundColor: 'rgba(8, 145, 178, 0.1)',
                            borderColor: 'rgba(8, 145, 178, 1)',
                            borderWidth: 2,
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
                                        return `Revenue: Ksh ${context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
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
                                    },
                                    color: '#64748b'
                                },
                                grid: {
                                    color: 'rgba(100, 116, 139, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#64748b'
                                }
                            }
                        }
                    }
                });
            }
            
            // Payment Methods Chart
            const paymentCtx = document.getElementById('paymentMethodsChart');
            if (paymentCtx) {
                const paymentData = <?php echo json_encode($payment_methods_data); ?>;
                
                new Chart(paymentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: paymentData.map(p => {
                            const method = p.payment_method || 'cash';
                            return method.charAt(0).toUpperCase() + method.slice(1).replace('_', ' ');
                        }),
                        datasets: [{
                            data: paymentData.map(p => p.total_amount),
                            backgroundColor: [
                                'rgba(8, 145, 178, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(13, 148, 136, 0.8)'
                            ],
                            borderColor: 'white',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    },
                                    padding: 15,
                                    color: '#64748b'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        const index = context.dataIndex;
                                        const transactionCount = paymentData[index]?.transaction_count || 0;
                                        return [
                                            `${label}: Ksh ${value.toLocaleString('en-US', {minimumFractionDigits: 2})}`,
                                            `${percentage}% of total revenue`,
                                            `${transactionCount} transactions`
                                        ];
                                    }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            }
        });
        
        // Refresh dashboard function
        function refreshDashboard() {
            const btn = document.querySelector('.refresh-btn');
            const container = document.querySelector('.dashboard-container');
            
            // Add loading state
            btn.classList.add('pulse');
            container.classList.add('loading');
            
            // Refresh after 1 second
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Auto-refresh every 2 minutes
        setTimeout(function() {
            refreshDashboard();
        }, 120000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P for Process Payment
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'unified_payment.php';
            }
            // Ctrl + R for Reports
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'reports.php';
            }
            // Ctrl + B for Billing
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.location.href = 'billing_payments.php';
            }
            // F5 to refresh
            if (e.key === 'F5') {
                e.preventDefault();
                refreshDashboard();
            }
        });

        // Make table rows clickable
        document.querySelectorAll('tbody tr').forEach(row => {
            if (!row.querySelector('.empty-state')) {
                row.style.cursor = 'pointer';
            }
        });
    </script>
</body>
</html>