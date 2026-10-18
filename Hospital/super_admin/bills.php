<!DOCTYPE html>
<head>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <style>
        :root {
            --primarycolor: #008080;
            --primarydark: #006666;
            --primarylight: #00b3b3;
            --secondarycolor: #87CEEB;
            --successcolor: #27ae60;
            --warningcolor: #f39c12;
            --dangercolor: #e74c3c;
            --lightgray: #f5f6fa;
            --bordercolor: #dcdde1;
            --teallight: #e6f7f7;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .main-container {
        
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e6f7f7 100%);
        }
        
        .content-wrapper {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h2 {
            color: var(--primarycolor);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primarylight);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h3 {
            color: var(--primarydark);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--secondarycolor);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0, 128, 128, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid var(--primarycolor);
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 128, 128, 0.15);
        }
        
        .summary-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--primarycolor);
            margin-bottom: 8px;
        }
        
        .summary-label {
            color: var(--primarydark);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .summary-note {
            color: #666;
            font-size: 12px;
            margin-top: 8px;
            font-style: italic;
        }
        
        .scroll {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--bordercolor);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        
        .report-table th {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 14px;
            border: none;
        }
        
        .report-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--bordercolor);
            font-size: 14px;
        }
        
        .report-table tr:hover {
            background-color: var(--teallight);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-paid {
            background-color: var(--successcolor);
            color: white;
        }
        
        .status-pending {
            background-color: var(--warningcolor);
            color: #212529;
        }
        
        .status-cancelled {
            background-color: #6c757d;
            color: white;
        }
        
        .status-partial {
            background-color: #3498db;
            color: white;
        }
        
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid var(--secondarycolor);
        }
        
        .filters-title {
            color: var(--primarycolor);
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primarydark);
            font-size: 14px;
        }
        
        .form-control {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px 12px;
            height: 40px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primarylight);
            box-shadow: 0 0 0 2px rgba(0, 179, 179, 0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            height: 40px;
        }
        
        .btn-primary {
            background-color: var(--primarycolor);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primarydark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--successcolor);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #219653;
            transform: translateY(-2px);
        }
        
        .date-range {
            background-color: var(--teallight);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--secondarycolor);
        }
        
        .date-range strong {
            color: var(--primarycolor);
        }
        
        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .amount-cell {
            font-weight: 600;
            color: var(--successcolor);
        }
        
        .customer-cell {
            font-weight: 600;
            color: var(--primarydark);
        }
        
        .bill-type {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #495057;
        }
        
        .view-link {
            color: var(--primarycolor);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }
        
        .view-link:hover {
            color: var(--primarydark);
            text-decoration: underline;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .page-item {
            list-style: none;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: var(--primarycolor);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background-color: var(--teallight);
        }
        
        .page-link.active {
            background-color: var(--primarycolor);
            color: white;
            border-color: var(--primarycolor);
        }
        
        @media (max-width: 1200px) {
            .main-container {
                padding-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 15px;
            }
            .form-row {
                flex-direction: column;
            }
            .form-group {
                width: 100%;
            }
            .controls {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .report-table th,
            .report-table td {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
        }
        
        .icon {
            margin-right: 8px;
        }
        
        .total-row {
            background-color: var(--teallight) !important;
            font-weight: bold;
        }
        
        .total-row td {
            border-top: 2px solid var(--primarycolor);
        }
        
        .bill-number {
            font-weight: 600;
            color: var(--primarycolor);
            font-family: 'Courier New', monospace;
        }
        
        .paid-row {
            background-color: #f0fff4 !important;
        }
        
        .pending-row {
            background-color: #fffaf0 !important;
        }
     </style>
</head>
</html>
<?php
include "admin/connection.php";
include "includes/db_connect.php";


// Switch to hospital database for all queries
mysqli_select_db($link, "hospital");

// Get date range (default to last 30 days)
$startDate = isset($_POST['dt']) ? $_POST['dt'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_POST['dt2']) ? $_POST['dt2'] : date('Y-m-d');
$isFiltered = isset($_POST["submit1"]);

// Calculate summary statistics
function getPharmacyStats($link, $startDate = null, $endDate = null) {
    $whereClause = "";
    if ($startDate && $endDate) {
        $whereClause = "WHERE date BETWEEN '$startDate' AND '$endDate 23:59:59'";
    }
    
    // Get total transactions and amount
    $query = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(COALESCE(total_amount, 0)) as gross_sales,
            AVG(COALESCE(total_amount, 0)) as avg_sale,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_transactions,
            SUM(CASE WHEN payment_status = 'paid' THEN COALESCE(total_amount, 0) ELSE 0 END) as collected_amount
        FROM pharmacy_billing
        $whereClause
    ";
    
    $result = mysqli_query($link, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return [
        'total_transactions' => 0,
        'gross_sales' => 0,
        'avg_sale' => 0,
        'paid_transactions' => 0,
        'collected_amount' => 0
    ];
}

// Get pharmacy sales data
function getPharmacySales($link, $startDate = null, $endDate = null) {
    $whereClause = "";
    if ($startDate && $endDate) {
        $whereClause = "WHERE date BETWEEN '$startDate' AND '$endDate 23:59:59'";
    }
    
    $query = "
        SELECT 
            pb.id,
            pb.bill_no,
            pb.CustomerName,
            pb.patient_id,
            pb.date,
            pb.bill_type,
            pb.payment_status,
            pb.username as generated_by,
            COALESCE(pb.total_amount, 
                (SELECT SUM(price * quantity) 
                 FROM pharmacy_billing_details pbd 
                 WHERE pbd.billing_id = pb.id)) as total_amount
        FROM pharmacy_billing pb
        $whereClause
        ORDER BY pb.date DESC
    ";
    
    return mysqli_query($link, $query);
}

// Get statistics
$stats = getPharmacyStats($link, $isFiltered ? $startDate : null, $isFiltered ? $endDate : null);

// Get sales data
$salesResult = getPharmacySales($link, $isFiltered ? $startDate : null, $isFiltered ? $endDate : null);
$totalRows = mysqli_num_rows($salesResult);

// Calculate collection rate
$collectionRate = $stats['gross_sales'] > 0 ? ($stats['collected_amount'] / $stats['gross_sales'] * 100) : 0;

include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Sales Report</title>
</head>
<body>
    <div class="main-container" style="margin-left: 320px;">
        
            <!-- Controls -->
            <div class="controls">
               
                <?php if($isFiltered): ?>
                <a href="view_pharmacy_bills.php" class="btn btn-secondary">
                    <i class="fas fa-times icon"></i> Clear Filter
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Page Title -->
            <h2>
                <span>Pharmacy Sales Report</span>
                <span class="status-badge <?php echo $collectionRate >= 90 ? 'status-paid' : 'status-pending'; ?>">
                    <?php echo number_format($collectionRate, 1); ?>% Collected
                </span>
            </h2>
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $stats['total_transactions']; ?></div>
                    <div class="summary-label">Total Bills</div>
                    <div class="summary-note">Pharmacy transactions</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value">Ksh <?php echo number_format($stats['gross_sales'], 2); ?></div>
                    <div class="summary-label">Gross Sales</div>
                    <div class="summary-note">Total bill amount</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value">Ksh <?php echo number_format($stats['avg_sale'], 2); ?></div>
                    <div class="summary-label">Average Bill</div>
                    <div class="summary-note">Per transaction</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value"><?php echo $stats['paid_transactions']; ?></div>
                    <div class="summary-label">Paid Bills</div>
                    <div class="summary-note">Completed payments</div>
                </div>
            </div>
            
            <!-- Date Filters -->
            <div class="filters-container">
                <div class="filters-title">
                    <i class="fas fa-filter"></i> Filter by Bill Date
                </div>
                <form action="" name="form1" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dt">Start Date</label>
                            <input type="date" name="dt" id="dt" class="form-control" 
                                   value="<?php echo $startDate; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="dt2">End Date</label>
                            <input type="date" name="dt2" id="dt2" class="form-control"
                                   value="<?php echo $endDate; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="submit1" class="btn btn-primary">
                                <i class="fas fa-search icon"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Pharmacy Bills Table -->
            <div class="section-container" style="margin-top: 30px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 3px 15px rgba(0, 128, 128, 0.1);">
                <div class="section-title">
                    <h3>Pharmacy Bills</h3>
                    <span class="status-badge <?php echo $totalRows > 0 ? 'status-paid' : 'status-pending'; ?>">
                        <?php echo $totalRows; ?> bills found
                    </span>
                </div>
                
                <div class="scroll">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Customer</th>
                                <th>Patient ID</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Generated By</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($totalRows == 0): ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px; display: block; color: #6c757d;"></i>
                                    No pharmacy bills found for the selected period.
                                </td>
                            </tr>
                            <?php else: 
                                $totalAmount = 0;
                                $paidAmount = 0;
                                mysqli_data_seek($salesResult, 0);
                                
                                while($row = mysqli_fetch_assoc($salesResult)):
                                    $totalAmount += $row['total_amount'];
                                    if ($row['payment_status'] == 'paid') {
                                        $paidAmount += $row['total_amount'];
                                    }
                                    
                                    $statusClass = 'status-' . ($row['payment_status'] ?: 'pending');
                                    $rowClass = $row['payment_status'] == 'paid' ? 'paid-row' : 'pending-row';
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <span class="bill-number"><?php echo htmlspecialchars($row['bill_no']); ?></span>
                                </td>
                                <td>
                                    <div class="customer-cell"><?php echo htmlspecialchars($row['CustomerName']); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    if ($row['patient_id']) {
                                        echo '<span style="color: var(--primarycolor); font-weight: 600;">' . htmlspecialchars($row['patient_id']) . '</span>';
                                    } else {
                                        echo '<span style="color: #6c757d; font-style: italic;">Walk-in</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($row['date'])); ?>
                                    <div style="font-size: 11px; color: #666;">
                                        <?php echo date('H:i', strtotime($row['date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="bill-type"><?php echo htmlspecialchars($row['bill_type'] ?: 'General'); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['generated_by']); ?></td>
                                <td class="amount-cell">Ksh <?php echo number_format($row['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($row['payment_status'] ?: 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_bill_details.php?id=<?php echo $row['id']; ?>" class="view-link">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            
                            <!-- Summary Row -->
                            <tr class="total-row">
                                <td colspan="6" style="text-align: right; font-weight: bold;">Total Summary:</td>
                                <td class="amount-cell">Ksh <?php echo number_format($totalAmount, 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $collectionRate >= 90 ? 'status-paid' : 'status-pending'; ?>">
                                        <?php echo number_format($collectionRate, 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <span style="color: #666; font-size: 12px;">
                                        <?php echo $totalRows; ?> bills
                                    </span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Payment Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                <!-- Payment Status Breakdown -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.08);">
                    <h3>Payment Status</h3>
                    <div style="margin-top: 15px;">
                        <?php
                        // Get payment status breakdown
                        $statusQuery = "
                            SELECT 
                                payment_status,
                                COUNT(*) as count,
                                SUM(COALESCE(total_amount, 0)) as amount
                            FROM pharmacy_billing
                            " . ($isFiltered ? "WHERE date BETWEEN '$startDate' AND '$endDate 23:59:59'" : "") . "
                            GROUP BY payment_status
                        ";
                        $statusResult = mysqli_query($link, $statusQuery);
                        
                        if ($statusResult && mysqli_num_rows($statusResult) > 0):
                            while ($status = mysqli_fetch_assoc($statusResult)):
                                $statusType = $status['payment_status'] ?: 'pending';
                                $percentage = $stats['total_transactions'] > 0 ? ($status['count'] / $stats['total_transactions'] * 100) : 0;
                        ?>
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <div style="font-weight: 600; font-size: 13px;">
                                    <span class="status-badge status-<?php echo $statusType; ?>" style="margin-right: 8px;">
                                        <?php echo ucfirst($statusType); ?>
                                    </span>
                                </div>
                                <div style="font-size: 13px;"><?php echo $status['count']; ?> bills</div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #666; margin-bottom: 5px;">
                                <div>Ksh <?php echo number_format($status['amount'], 2); ?></div>
                                <div><?php echo number_format($percentage, 1); ?>%</div>
                            </div>
                            <div style="height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; background: var(--primarylight); width: <?php echo $percentage; ?>%; border-radius: 3px;"></div>
                            </div>
                        </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <div class="no-data" style="padding: 20px; font-size: 14px;">
                            No payment status data available
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <!-- <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.08);">
                    <h3>Recent Activity</h3>
                    <div style="margin-top: 15px;">
                        <?php
                        // Get recent bills (last 5)
                        $recentQuery = "
                            SELECT 
                                bill_no,
                                CustomerName,
                                date,
                                total_amount,
                                payment_status
                            FROM pharmacy_billing
                            ORDER BY date DESC
                            LIMIT 5
                        ";
                        $recentResult = mysqli_query($link, $recentQuery);
                        
                        if ($recentResult && mysqli_num_rows($recentResult) > 0):
                            while ($recent = mysqli_fetch_assoc($recent)):
                        ?>
                        <div style="padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                            <div style="background: var(--primarylight); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-receipt" style="font-size: 12px;"></i>
                            </div>
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($recent['CustomerName']); ?></div>
                                <div style="font-size: 11px; color: #666;">
                                    <?php echo date('M j, H:i', strtotime($recent['date'])); ?> • 
                                    <?php echo htmlspecialchars($recent['bill_no']); ?>
                                </div>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo $recent['payment_status'] ?: 'pending'; ?>" style="margin-right: 8px;">
                                    <?php echo ucfirst($recent['payment_status'] ?: 'pending'); ?>
                                </span>
                            </div>
                        </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <div class="no-data" style="padding: 20px; font-size: 14px;">
                            No recent activity
                        </div>
                        <?php endif; ?>
                    </div>
                </div> -->
            </div>
            
            <!-- Report Summary -->
            <div style="margin-top: 30px; padding: 20px; background-color: var(--primarycolor); color: white; border-radius: 8px;">
                <h4 style="margin-top: 0; color: white;">Report Summary</h4>
                <p>
                    <strong>Period:</strong> 
                    <?php 
                    if($isFiltered) {
                        echo date('F j, Y', strtotime($startDate)) . ' to ' . date('F j, Y', strtotime($endDate));
                    } else {
                        echo 'All Time';
                    }
                    ?>
                    &nbsp;|&nbsp;
                    <strong>Total Bills:</strong> <?php echo $stats['total_transactions']; ?>
                    &nbsp;|&nbsp;
                    <strong>Total Sales:</strong> Ksh <?php echo number_format($stats['gross_sales'], 2); ?>
                    &nbsp;|&nbsp;
                    <strong>Collection Rate:</strong> <?php echo number_format($collectionRate, 1); ?>%
                </p>
                <p style="font-size: 14px; opacity: 0.9;">
                    <?php if($isFiltered): ?>
                        <i class="fas fa-filter"></i> Showing filtered results. 
                        <?php echo $stats['paid_transactions']; ?> of <?php echo $stats['total_transactions']; ?> bills are paid.
                    <?php else: ?>
                        <i class="fas fa-chart-line"></i> All pharmacy bills displayed. 
                        Total collected amount: Ksh <?php echo number_format($stats['collected_amount'], 2); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Set default dates for date inputs
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];
            
            // Set default values for filter dates if not already set by PHP
            const startDateInput = document.getElementById('dt');
            const endDateInput = document.getElementById('dt2');
            
            if (startDateInput && !startDateInput.hasAttribute('value')) {
                startDateInput.value = thirtyDaysAgoStr;
            }
            if (endDateInput && !endDateInput.hasAttribute('value')) {
                endDateInput.value = today;
            }
        });
        
        // Add click animation to view links
        document.addEventListener('DOMContentLoaded', function() {
            const viewLinks = document.querySelectorAll('.view-link');
            viewLinks.forEach(link => {
                link.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 200);
                });
            });
        });
    </script>

    <?php
    include "includes/footer.php";
    ?>
</body>
</html>