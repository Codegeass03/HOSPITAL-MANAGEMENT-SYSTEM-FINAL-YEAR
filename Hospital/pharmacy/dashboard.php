<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Check if user has pharmacy access
$user_role = $_SESSION['role'] ?? '';
$pharmacy_roles = ['Pharmacist', 'Super Admin'];
if (!in_array($user_role, $pharmacy_roles)) {
    die("Access denied. Pharmacy staff only.");
}

// Get today's date
$today = date('Y-m-d');
$current_datetime = date('Y-m-d H:i:s');
$thirty_days_later = date('Y-m-d', strtotime('+30 days'));

// STATISTICS - Fetch from pharmacy_billing table (same as bills.php)
$stats = [];

// 1. Today's Bills Count - from pharmacy_billing table
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM pharmacy_billing 
        WHERE DATE(date) = :today
    ");
    $stmt->execute([':today' => $today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_bills'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    $stats['today_bills'] = 0;
}

// 2. Today's Revenue - from pharmacy_billing table with status = 'paid'
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM pharmacy_billing 
        WHERE DATE(date) = :today 
        AND payment_status = 'paid'
    ");
    $stmt->execute([':today' => $today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_revenue'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    $stats['today_revenue'] = 0;
}

// 3. Total Revenue (All time) - from pharmacy_billing table with status = 'paid'
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM pharmacy_billing 
        WHERE payment_status = 'paid'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_revenue'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    $stats['total_revenue'] = 0;
}

// 4. Pending Bills Count - from pharmacy_billing table
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM pharmacy_billing 
        WHERE payment_status = 'pending'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_bills'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    $stats['pending_bills'] = 0;
}

// 5. Pending Prescriptions Count - from prescriptions table (like pharmacy.php)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE status = 'active' AND dispensed = 0
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_prescriptions'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    $stats['pending_prescriptions'] = 0;
}

// 6. Low Stock Count - from stock_master table
try {
    $result = mysqli_query($link, "SELECT COUNT(*) as count FROM stock_master WHERE product_quantity <= 10 AND product_quantity > 0");
    $row = mysqli_fetch_assoc($result);
    $stats['low_stock'] = $row['count'] ?? 0;
} catch (Exception $e) {
    $stats['low_stock'] = 0;
}

// 7. Out of Stock Count - from stock_master table
try {
    $result = mysqli_query($link, "SELECT COUNT(*) as count FROM stock_master WHERE product_quantity = 0");
    $row = mysqli_fetch_assoc($result);
    $stats['out_of_stock'] = $row['count'] ?? 0;
} catch (Exception $e) {
    $stats['out_of_stock'] = 0;
}

// 8. Expiring Soon Count - from purchase_master table (like expiry_report.php)
try {
    $result = mysqli_query($link, "SELECT COUNT(*) as count FROM purchase_master WHERE expiry_date >= '$today' AND expiry_date <= '$thirty_days_later'");
    $row = mysqli_fetch_assoc($result);
    $stats['expiring_soon'] = $row['count'] ?? 0;
} catch (Exception $e) {
    $stats['expiring_soon'] = 0;
}

// PENDING PRESCRIPTIONS - Fetch from prescriptions table (like pharmacy.php)
$pending_prescriptions = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, 
               pt.first_name, pt.last_name, pt.patient_id,
               u.first_name as doctor_first_name, u.last_name as doctor_last_name
        FROM prescriptions p
        JOIN patients pt ON p.patient_id = pt.id
        JOIN users u ON p.doctor_id = u.id
        WHERE p.status = 'active' AND p.dispensed = 0
        ORDER BY p.prescription_date DESC
        LIMIT 10
    ");
    $pending_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_prescriptions = [];
}

// RECENT BILLS - Fetch from pharmacy_billing table (like bills.php)
$recent_bills = [];
try {
    $stmt = $pdo->query("
        SELECT 
            id,
            CustomerName,
            bill_type,
            date,
            bill_no,
            total_amount,
            payment_status
        FROM pharmacy_billing 
        ORDER BY date DESC
        LIMIT 10
    ");
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_bills = [];
}

// EXPIRING PRODUCTS - Fetch from purchase_master table (like expiry_report.php)
$expiring_products = [];
try {
    $result = mysqli_query($link, "
        SELECT 
            product_name,
            company_name,
            quantity,
            expiry_date,
            price,
            unit,
            packing_size,
            party_name
        FROM purchase_master 
        WHERE expiry_date >= '$today' AND expiry_date <= '$thirty_days_later'
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $expiry_date = $row['expiry_date'];
        $days_remaining = floor((strtotime($expiry_date) - strtotime($today)) / (60 * 60 * 24));
        
        $expiring_products[] = [
            'product_name' => $row['product_name'],
            'company_name' => $row['company_name'],
            'quantity' => $row['quantity'],
            'expiry_date' => $row['expiry_date'],
            'price' => $row['price'],
            'unit' => $row['unit'],
            'packing_size' => $row['packing_size'],
            'party_name' => $row['party_name'],
            'days_remaining' => $days_remaining
        ];
    }
} catch (Exception $e) {
    $expiring_products = [];
}

// LOW STOCK PRODUCTS - Fetch from stock_master table
$low_stock_products = [];
try {
    $result = mysqli_query($link, "
        SELECT 
            product_name,
            product_company,
            product_quantity,
            product_selling_price,
            product_unit,
            packing_size
        FROM stock_master 
        WHERE product_quantity <= 10
        ORDER BY product_quantity ASC
        LIMIT 10
    ");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $low_stock_products[] = [
            'product_name' => $row['product_name'],
            'product_company' => $row['product_company'],
            'product_quantity' => $row['product_quantity'],
            'product_selling_price' => $row['product_selling_price'],
            'product_unit' => $row['product_unit'],
            'packing_size' => $row['packing_size']
        ];
    }
} catch (Exception $e) {
    $low_stock_products = [];
}

// Today's date for display
$display_date = date('F j, Y');
$display_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #17a2b8;
            --purple: #9b59b6;
            --teal: #1abc9c;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard-container {
            padding: 20px;
            margin-left: 300px;
            transition: all 0.3s;
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
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--primary);
        }

        .header-content h1 {
            font-size: clamp(24px, 4vw, 32px);
            color: var(--secondary);
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
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
        .stat-card-info { border-top-color: var(--info); }
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
            color: var(--secondary);
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
            background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%);
            color: white;
            flex-shrink: 0;
        }

        .stat-card-primary .stat-icon { background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%); }
        .stat-card-success .stat-icon { background: linear-gradient(135deg, var(--success) 0%, #229954 100%); }
        .stat-card-warning .stat-icon { background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%); }
        .stat-card-danger .stat-icon { background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%); }
        .stat-card-info .stat-icon { background: linear-gradient(135deg, var(--info) 0%, #138496 100%); }
        .stat-card-purple .stat-icon { background: linear-gradient(135deg, var(--purple) 0%, #8e44ad 100%); }
        .stat-card-teal .stat-icon { background: linear-gradient(135deg, var(--teal) 0%, #16a085 100%); }

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

        /* Main Content Sections */
        .content-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 500px), 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%);
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
            table-layout: fixed;
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
            color: var(--secondary);
            border-bottom: 2px solid var(--light-gray);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
            white-space: wrap;
        }

        tbody tr {
            transition: background-color 0.2s;
            cursor: pointer;
        }

        tbody tr:hover {
            background: var(--light);
        }

        tbody tr:last-child td {
            border-bottom: none;
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

        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-completed { background: #d1ecf1; color: #0c5460; }

        .stock-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .stock-low { background: #fff3cd; color: #856404; }
        .stock-critical { background: #f8d7da; color: #721c24; }
        .stock-out { background: #f8d7da; color: #721c24; }

        .expiry-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }

        .expiry-normal { background: #d4edda; color: #155724; }
        .expiry-warning { background: #fff3cd; color: #856404; }
        .expiry-danger { background: #f8d7da; color: #721c24; }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 50px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            padding: 30px;
            margin: 0 auto;
            border-radius: 25%;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
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
            background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%);
        }

        .action-content h3 {
            font-size: 16px;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 5px;
        }

        .action-content p {
            font-size: 15px;
            color: var(--gray);
            margin: 0;
        }

        /* Bottom Tables Section */
        .tables-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 500px), 1fr));
            gap: 25px;
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

        /* Refresh Button */
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%);
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
            
            .stat-card {
                padding: 20px;
                min-height: 140px;
            }
            
            .section-header {
                padding: 15px;
            }
            
            th, td {
                padding: 10px 12px;
                font-size: 12px;
            }
            
            .refresh-btn {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }
        }

        /* Loading animation */
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-clinic-medical"></i> Pharmacy Management Dashboard</h1>
                <p>
                    <i style ="font-size: 25px; font-weight: bold; color: #55abd3;" class="far fa-calendar-alt"></i>
                    <?php echo $display_date;?>
                </p>
                <br>
                <p>
                    <i style ="font-size: 24px; font-weight: bold;color: #55abd3;" class="fas fa-clock"></i>
                    <?php echo $display_time; ?>
                </p>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <!-- Today's Bills -->
            <div class="stat-card stat-card-primary" onclick="window.location.href='bills.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Today's Bills</h3>
                        <div class="stat-value"><?php echo number_format($stats['today_bills']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Bills generated today</span>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>From pharmacy</span>
                    </div>
                </div>
            </div>

            <!-- Today's Revenue -->
            <div class="stat-card stat-card-success" onclick="window.location.href='bills.php'">
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
                    <span>Revenue from paid bills</span>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>Paid status</span>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="stat-card stat-card-info" onclick="window.location.href='bills.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Total Revenue</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['total_revenue'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>All time pharmacy revenue</span>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>Total</span>
                    </div>
                </div>
            </div>

            <!-- Pending Bills -->
            <div class="stat-card stat-card-warning" onclick="window.location.href='bills.php?filter=pending'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Pending Bills</h3>
                        <div class="stat-value"><?php echo number_format($stats['pending_bills']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Awaiting payment</span>
                    <?php if ($stats['pending_bills'] > 0): ?>
                        <div class="stat-trend down">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Requires action</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>All clear</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Prescriptions -->
            <div class="stat-card stat-card-purple" onclick="window.location.href='pharmacy.php?tab=prescriptions'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Pending Prescriptions</h3>
                        <div class="stat-value"><?php echo number_format($stats['pending_prescriptions']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-prescription-bottle-alt"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>To be dispensed</span>
                    <?php if ($stats['pending_prescriptions'] > 0): ?>
                        <div class="stat-trend down">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Needs dispensing</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>All dispensed</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock -->
            <div class="stat-card stat-card-danger" onclick="window.location.href='inventorylist.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Low Stock Items</h3>
                        <div class="stat-value"><?php echo number_format($stats['low_stock']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>≤ 10 units remaining</span>
                    <?php if ($stats['low_stock'] > 0): ?>
                        <div class="stat-trend down">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Reorder needed</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>Well stocked</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Out of Stock -->
            <div class="stat-card stat-card-danger" onclick="window.location.href='inventorylist.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Out of Stock</h3>
                        <div class="stat-value"><?php echo number_format($stats['out_of_stock']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>0 units in stock</span>
                    <?php if ($stats['out_of_stock'] > 0): ?>
                        <div class="stat-trend down">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Urgent reorder</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>All available</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Expiring Soon -->
            <div class="stat-card stat-card-teal" onclick="window.location.href='expiry_report.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Expiring Soon</h3>
                        <div class="stat-value"><?php echo number_format($stats['expiring_soon']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Within 30 days</span>
                    <?php if ($stats['expiring_soon'] > 0): ?>
                        <div class="stat-trend down">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Check expiry</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>No expiring items</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <br>
        <!-- Quick Actions -->
         <div>
            <h1 style="font-size: 24px; color: #0863e4; text-align:center; text-decoration: none;">QUICK LINKS TO PHARMACY MODULES</h1>
            <br>
         </div>

        <div class="quick-actions">
            <a href="pharmacy.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="action-content">
                    <h3>Manage Pharmacy</h3>
                    <p>View inventory & prescriptions</p>
                </div>
            </a>
            
            <a href="inventorylist.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--success) 0%, #229954 100%);">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="action-content">
                    <h3>Inventory</h3>
                    <p>Manage stock & products</p>
                </div>
            </a>
            
            <a href="sales.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="action-content">
                    <h3>Process Sale</h3>
                    <p>Sell medications</p>
                </div>
            </a>
            
            <a href="bills.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="action-content">
                    <h3>View Bills</h3>
                    <p>See all pharmacy bills</p>
                </div>
            </a>
        </div>


        <!-- Main Content Section - Pending Prescriptions & Recent Bills -->
        <div class="content-sections">
            <!-- Pending Prescriptions -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-prescription"></i> Pending Prescriptions</h2>
                    <span class="badge"><?php echo count($pending_prescriptions); ?> pending</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Medication</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($pending_prescriptions) > 0): ?>
                                    <?php foreach ($pending_prescriptions as $prescription): ?>
                                        <tr onclick="window.location.href='pharmacy.php?tab=prescriptions'">
                                            <td>
                                                <strong><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></strong>
                                                <div style="font-size: 11px; color: var(--gray);">
                                                    ID: <?php echo htmlspecialchars($prescription['patient_id']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($prescription['medication_name']); ?></td>
                                            <td>
                                                Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-active">
                                                    Pending
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <h4>No Pending Prescriptions</h4>
                                            <p>All prescriptions have been dispensed</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="pharmacy.php?tab=prescriptions" class="view-all-link">
                        View All Prescriptions <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Recent Bills -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Recent Bills</h2>
                    <span class="badge"><?php echo count($recent_bills); ?> bills</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Bill No</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_bills) > 0): ?>
                                    <?php foreach ($recent_bills as $bill): ?>
                                        <tr onclick="window.location.href='view_bill_details.php?id=<?php echo $bill['id']; ?>'">
                                            <td><strong><?php echo htmlspecialchars($bill['bill_no']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bill['CustomerName']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($bill['date'])); ?></td>
                                            <td><strong>Ksh <?php echo number_format($bill['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <?php 
                                                $status_class = 'status-pending';
                                                if ($bill['payment_status'] == 'paid') {
                                                    $status_class = 'status-paid';
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($bill['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-file-invoice"></i>
                                            <h4>No Bills Found</h4>
                                            <p>No pharmacy bills have been created yet</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="bills.php" class="view-all-link">
                        View All Bills <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Bottom Tables Section - Expiring Products & Low Stock -->
        <div class="tables-section">
            <!-- Expiring Products -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-times"></i> Expiring Products (30 Days)</h2>
                    <span class="badge"><?php echo count($expiring_products); ?> expiring</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Manufacturer</th>
                                    <th>Expiry Date</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($expiring_products) > 0): ?>
                                    <?php foreach ($expiring_products as $product): ?>
                                        <?php
                                        $expiry_class = 'expiry-normal';
                                        $status_text = 'VALID';
                                        if ($product['days_remaining'] < 0) {
                                            $expiry_class = 'expiry-danger';
                                            $status_text = 'EXPIRED';
                                        } elseif ($product['days_remaining'] <= 7) {
                                            $expiry_class = 'expiry-danger';
                                            $status_text = 'URGENT';
                                        } elseif ($product['days_remaining'] <= 30) {
                                            $expiry_class = 'expiry-warning';
                                            $status_text = 'SOON';
                                        }
                                        ?>
                                        <tr onclick="window.location.href='expiry_report.php'">
                                            <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($product['company_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($product['expiry_date'])); ?></td>
                                            <td><?php echo $product['quantity']; ?> <?php echo $product['unit']; ?></td>
                                            <td>
                                                <span class="expiry-badge <?php echo $expiry_class; ?>">
                                                    <?php echo $status_text; ?>
                                                    <?php if ($product['days_remaining'] >= 0): ?>
                                                        <br><small><?php echo $product['days_remaining']; ?> days</small>
                                                    <?php else: ?>
                                                        <br><small><?php echo abs($product['days_remaining']); ?> days ago</small>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <h4>No Expiring Products</h4>
                                            <p>No products expiring within 30 days</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="expiry_report.php" class="view-all-link">
                        View All Expiring Products <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Products</h2>
                    <span class="badge"><?php echo count($low_stock_products); ?> low</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Manufacturer</th>
                                    <th>Stock</th>
                                    <th>Unit</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($low_stock_products) > 0): ?>
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <?php
                                        $stock_class = 'stock-low';
                                        $status_text = 'LOW';
                                        if ($product['product_quantity'] == 0) {
                                            $stock_class = 'stock-out';
                                            $status_text = 'OUT';
                                        } elseif ($product['product_quantity'] <= 5) {
                                            $stock_class = 'stock-critical';
                                            $status_text = 'CRITICAL';
                                        }
                                        ?>
                                        <tr onclick="window.location.href='inventorylist.php'">
                                            <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($product['product_company']); ?></td>
                                            <td><strong><?php echo $product['product_quantity']; ?></strong></td>
                                            <td><?php echo $product['product_unit']; ?></td>
                                            <td>
                                                <span class="stock-badge <?php echo $stock_class; ?>">
                                                    <?php echo $status_text; ?>
                                                    <br><small>Stock: <?php echo $product['product_quantity']; ?></small>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <h4>No Low Stock Items</h4>
                                            <p>All products are sufficiently stocked</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="inventorylist.php" class="view-all-link">
                        View Full Inventory <i class="fas fa-arrow-right"></i>
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

        // Make table rows clickable
        document.querySelectorAll('tbody tr').forEach(row => {
            row.style.cursor = 'pointer';
        });
    </script>
</body>
</html>