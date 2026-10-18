<?php
session_start();
if(!isset($_SESSION["admin"])) {
    header("location: index.php");
    exit();
}

// Database connection for new tables
$conn = new mysqli("localhost", "root", "", "hospital");

include "includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Sales Report</title>
    <style>
        /* Add your styles here */
        .sales-report {
            padding: 20px;
        }
        .report-header {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 24px;
            color: #667eea;
        }
        .stat-card p {
            margin: 5px 0 0 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .status-pending { color: #ff9800; }
        .status-paid { color: #4caf50; }
        .status-partial { color: #2196f3; }
    </style>
</head>
<body>
    <div class="sales-report" style="margin-left: 250px; padding: 20px;">
        <div class="report-header">
            <h1><i class="fa fa-chart-line"></i> Pharmacy Sales Report</h1>
            <p>New Database System - Unified Billing</p>
        </div>
        
        <?php
        // Get statistics
        $today = date('Y-m-d');
        
        // Today's sales
        $sql_today = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                     FROM pharmacy_sales 
                     WHERE DATE(sale_date) = '$today'";
        $result_today = $conn->query($sql_today);
        $today_stats = $result_today->fetch_assoc();
        
        // Pending payments
        $sql_pending = "SELECT COUNT(*) as count, SUM(balance_amount) as total 
                       FROM pharmacy_sales 
                       WHERE payment_status = 'pending'";
        $result_pending = $conn->query($sql_pending);
        $pending_stats = $result_pending->fetch_assoc();
        
        // All time sales
        $sql_all = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                   FROM pharmacy_sales";
        $result_all = $conn->query($sql_all);
        $all_stats = $result_all->fetch_assoc();
        ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $today_stats['count'] ?? 0; ?></h3>
                <p>Today's Sales</p>
                <small>Ksh <?php echo number_format($today_stats['total'] ?? 0, 2); ?></small>
            </div>
            <div class="stat-card">
                <h3><?php echo $pending_stats['count'] ?? 0; ?></h3>
                <p>Pending Bills</p>
                <small>Ksh <?php echo number_format($pending_stats['total'] ?? 0, 2); ?></small>
            </div>
            <div class="stat-card">
                <h3><?php echo $all_stats['count'] ?? 0; ?></h3>
                <p>Total Sales</p>
                <small>Ksh <?php echo number_format($all_stats['total'] ?? 0, 2); ?></small>
            </div>
        </div>
        
        <h2>Recent Sales</h2>
        <?php
        $sql_sales = "SELECT * FROM pharmacy_sales 
                     ORDER BY sale_date DESC 
                     LIMIT 50";
        $result_sales = $conn->query($sql_sales);
        
        if ($result_sales->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result_sales->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['receipt_no']; ?></td>
                    <td><?php echo $row['patient_name']; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($row['sale_date'])); ?></td>
                    <td>Ksh <?php echo number_format($row['total_amount'], 2); ?></td>
                    <td>Ksh <?php echo number_format($row['paid_amount'], 2); ?></td>
                    <td>Ksh <?php echo number_format($row['balance_amount'], 2); ?></td>
                    <td>
                        <span class="status-<?php echo $row['payment_status']; ?>">
                            <?php echo strtoupper($row['payment_status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="view_sale_details.php?id=<?php echo $row['id']; ?>" 
                           style="color: #667eea; text-decoration: none;">
                            <i class="fa fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No sales records found.</p>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="pharmacy_new_sales.php" class="btn btn-primary">
                <i class="fa fa-plus"></i> New Sale
            </a>
            <a href="pharmacy_pending_payments.php" class="btn btn-warning">
                <i class="fa fa-money-bill-wave"></i> Pending Payments
            </a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>