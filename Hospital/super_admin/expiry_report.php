<?php

if(!isset($_SESSION["admin"]))
{
?>
<?php
}
?>
<DOCTYPE html>
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
            margin-left: 300px;
        }
        
        .main-container {
            padding-left: 250px;
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
        
        .status-critical {
            background-color: var(--dangercolor);
            color: white;
        }
        
        .status-warning {
            background-color: var(--warningcolor);
            color: #212529;
        }
        
        .status-normal {
            background-color: var(--successcolor);
            color: white;
        }
        
        .status-expired {
            background-color: #6c757d;
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
        
        .btn-danger {
            background-color: var(--dangercolor);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 30px;
        }
        
        .section-container {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 128, 128, 0.1);
            border: 1px solid var(--secondarycolor);
        }
        
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background-color: var(--successcolor);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warningcolor);
            color: #212529;
        }
        
        .badge-danger {
            background-color: var(--dangercolor);
            color: white;
        }
        
        .badge-info {
            background-color: var(--primarylight);
            color: white;
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
        
        .days-left {
            font-size: 11px;
            font-weight: 500;
            margin-top: 3px;
            color: #666;
        }
        
        .quantity-cell {
            font-weight: 600;
            color: var(--primarycolor);
        }
        
        .expired-row {
            background-color: #f8d7da !important;
        }
        
        .expiring-row {
            background-color: #fff3cd !important;
        }
        
        @media (max-width: 1200px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
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
        
        .product-name {
            font-weight: 600;
            color: var(--primarydark);
        }
        
        .company-name {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
     </style>
</head>
</html>
<?php
include "admin/connection.php";
include "includes/db_connect.php";


// Get current date
$current_date = date('Y-m-d');
$thirty_days_later = date('Y-m-d', strtotime('+30 days'));
$ninety_days_later = date('Y-m-d', strtotime('+90 days'));

// Get expiring soon items (within 90 days)
$expiryQuery = "
    SELECT 
        product_name,
        company_name,
        unit,
        packing_size,
        expiry_date,
        quantity,
        price,
        DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
    FROM purchase_master
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    AND quantity > 0
    ORDER BY expiry_date ASC
";
$expiryResult = mysqli_query($link, $expiryQuery);
$expiryCount = mysqli_num_rows($expiryResult);

// Get expired items
$expiredQuery = "
    SELECT 
        product_name,
        company_name,
        unit,
        expiry_date,
        quantity,
        price
    FROM purchase_master
    WHERE expiry_date < CURDATE()
    AND quantity > 0
    ORDER BY expiry_date DESC
";
$expiredResult = mysqli_query($link, $expiredQuery);
$expiredCount = mysqli_num_rows($expiredResult);

// Get total products count
$totalQuery = "SELECT COUNT(*) as total_count FROM purchase_master WHERE quantity > 0";
$totalResult = mysqli_query($link, $totalQuery);
$totalRow = mysqli_fetch_assoc($totalResult);
$totalCount = $totalRow['total_count'];

// Get value at risk (expired and expiring soon)
$valueQuery = "
    SELECT 
        SUM(price * quantity) as total_value
    FROM purchase_master
    WHERE (expiry_date < CURDATE() OR expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    AND quantity > 0
";
$valueResult = mysqli_query($link, $valueQuery);
$valueRow = mysqli_fetch_assoc($valueResult);
$valueAtRisk = $valueRow['total_value'] ?? 0;

include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiry Management Report</title>
</head>
<body>
    
            
            <!-- Page Title -->
            <h2>
                <span>Expiry Management Report</span>
                <span class="badge <?php echo $expiredCount > 0 ? 'badge-danger' : 'badge-success'; ?>">
                    <?php echo $expiredCount > 0 ? 'Action Required' : 'All Clear'; ?>
                </span>
            </h2>
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $expiryCount; ?></div>
                    <div class="summary-label">Expiring Soon (90 Days)</div>
                    <div class="summary-note">Items expiring within 90 days</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value" style="color: var(--dangercolor);"><?php echo $expiredCount; ?></div>
                    <div class="summary-label">Expired Items</div>
                    <div class="summary-note">Requires immediate attention</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value"><?php echo $totalCount; ?></div>
                    <div class="summary-label">Total Products</div>
                    <div class="summary-note">In inventory</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value" style="color: var(--dangercolor);">
                        Ksh <?php echo number_format($valueAtRisk, 2); ?>
                    </div>
                    <div class="summary-label">Value at Risk</div>
                    <div class="summary-note">Expired + Expiring Soon</div>
                </div>
            </div>
            
            <!-- Date Filters -->
            <div class="filters-container">
                <div class="filters-title">
                    <i class="fas fa-filter"></i> Filter by Expiry Date Range
                </div>
                <form action="" name="form1" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dt">Start Date</label>
                            <input type="date" name="dt" id="dt" class="form-control" required 
                                   value="<?php echo isset($_POST['dt']) ? $_POST['dt'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="dt2">End Date</label>
                            <input type="date" name="dt2" id="dt2" class="form-control"
                                   value="<?php echo isset($_POST['dt2']) ? $_POST['dt2'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="submit1" class="btn btn-primary">
                                <i class="fas fa-search icon"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Main Content Grid -->
            <?php if(isset($_POST["submit1"])): 
                $start_date = mysqli_real_escape_string($link, $_POST['dt']);
                $end_date = mysqli_real_escape_string($link, $_POST['dt2']);
                
                $filteredQuery = "
                    SELECT 
                        product_name,
                        company_name,
                        unit,
                        packing_size,
                        expiry_date,
                        quantity,
                        price,
                        DATEDIFF(expiry_date, CURDATE()) as days_until_expiry,
                        purchase_date,
                        party_name,
                        purchase_method
                    FROM purchase_master
                    WHERE expiry_date BETWEEN '$start_date' AND '$end_date'
                    AND quantity > 0
                    ORDER BY expiry_date ASC
                ";
                $filteredResult = mysqli_query($link, $filteredQuery);
                $filteredCount = mysqli_num_rows($filteredResult);
            ?>
            
                <div class="section-container">
                    <div class="section-title">
                        <h3>Filtered Results (<?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>)</h3>
                        <span class="badge badge-info"><?php echo $filteredCount; ?> items found</span>
                    </div>
                    
                    <div class="scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Qty</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                    <th>Purchase Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($filteredCount == 0): ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px; display: block; color: #6c757d;"></i>
                                        No products found expiring in the selected date range.
                                    </td>
                                </tr>
                                <?php else: 
                                    while($row = mysqli_fetch_assoc($filteredResult)):
                                        $days = $row['days_until_expiry'];
                                        
                                        if ($days < 0) {
                                            $status_class = 'status-expired';
                                            $status_text = 'EXPIRED';
                                            $row_class = 'expired-row';
                                        } elseif ($days <= 30) {
                                            $status_class = 'status-critical';
                                            $status_text = 'CRITICAL';
                                            $row_class = 'expiring-row';
                                        } elseif ($days <= 90) {
                                            $status_class = 'status-warning';
                                            $status_text = 'WARNING';
                                            $row_class = '';
                                        } else {
                                            $status_class = 'status-normal';
                                            $status_text = 'NORMAL';
                                            $row_class = '';
                                        }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <div class="product-name"><?php echo $row['product_name']; ?></div>
                                        <div class="company-name"><?php echo $row['company_name']; ?></div>
                                    </td>
                                    <td><?php echo $row['unit']; ?></td>
                                    <td class="quantity-cell"><?php echo $row['quantity']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($row['expiry_date'])); ?></td>
                                    <td>
                                        <?php 
                                        if ($days >= 0) {
                                            echo $days . ' days';
                                        } else {
                                            echo abs($days) . ' days ago';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                        <?php if($days <= 30 && $days >= 0): ?>
                                        <div class="days-left">Urgent action needed</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($row['purchase_date'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            
            <?php else: ?>
            
            <div class="grid-layout">
                <!-- Expiring Soon Section -->
                <div class="section-container">
                    <div class="section-title">
                        <h3>Items Expiring Soon (90 Days)</h3>
                        <span class="badge <?php echo $expiryCount > 0 ? 'badge-warning' : 'badge-success'; ?>">
                            <?php echo $expiryCount; ?> items
                        </span>
                    </div>
                    
                    <div class="scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Qty</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($expiryCount == 0): ?>
                                <tr>
                                    <td colspan="6" class="no-data">
                                        <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 10px; display: block; color: var(--successcolor);"></i>
                                        No items expiring within 90 days.
                                    </td>
                                </tr>
                                <?php else: 
                                    mysqli_data_seek($expiryResult, 0);
                                    while($row = mysqli_fetch_assoc($expiryResult)):
                                        $days = $row['days_until_expiry'];
                                        
                                        if ($days <= 30) {
                                            $status_class = 'status-critical';
                                            $status_text = 'CRITICAL';
                                            $row_class = 'expiring-row';
                                        } else {
                                            $status_class = 'status-warning';
                                            $status_text = 'WARNING';
                                            $row_class = '';
                                        }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <div class="product-name"><?php echo $row['product_name']; ?></div>
                                        <div class="company-name"><?php echo $row['company_name']; ?></div>
                                    </td>
                                    <td><?php echo $row['unit']; ?></td>
                                    <td class="quantity-cell"><?php echo $row['quantity']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($row['expiry_date'])); ?></td>
                                    <td><?php echo $days; ?> days</td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                        <?php if($days <= 30): ?>
                                        <div class="days-left">Take action</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Expired Items Section -->
                <div class="section-container">
                    <div class="section-title">
                        <h3>Expired Items</h3>
                        <span class="badge <?php echo $expiredCount > 0 ? 'badge-danger' : 'badge-success'; ?>">
                            <?php echo $expiredCount; ?> items
                        </span>
                    </div>
                    
                    <div class="scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Expired On</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($expiredCount == 0): ?>
                                <tr>
                                    <td colspan="4" class="no-data">
                                        <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 10px; display: block; color: var(--successcolor);"></i>
                                        No expired items found.
                                    </td>
                                </tr>
                                <?php else: 
                                    mysqli_data_seek($expiredResult, 0);
                                    while($row = mysqli_fetch_assoc($expiredResult)):
                                        $value = $row['price'] * $row['quantity'];
                                ?>
                                <tr class="expired-row">
                                    <td>
                                        <div class="product-name"><?php echo $row['product_name']; ?></div>
                                        <div class="company-name"><?php echo $row['company_name']; ?></div>
                                    </td>
                                    <td class="quantity-cell"><?php echo $row['quantity']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($row['expiry_date'])); ?></td>
                                    <td style="color: var(--dangercolor); font-weight: 600;">
                                        Ksh <?php echo number_format($value, 2); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if($expiredCount > 0): ?>
                    <div style="margin-top: 20px; padding: 15px; background-color: #f8d7da; border-radius: 4px; border-left: 4px solid var(--dangercolor);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--dangercolor);"></i>
                            <div>
                                <strong>Action Required:</strong> <?php echo $expiredCount; ?> expired item(s) found. 
                                Please remove from inventory immediately.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php endif; ?>
            
            <!-- Report Summary -->
            <div style="margin-top: 30px; padding: 20px; background-color: var(--primarycolor); color: white; border-radius: 8px;">
                <h4 style="margin-top: 0; color: white;">Report Summary</h4>
                <p>
                    <strong>Date:</strong> <?php echo date('F j, Y'); ?>
                    &nbsp;|&nbsp;
                    <strong>Status:</strong> 
                    <span style="font-weight: bold; color: <?php echo $expiredCount > 0 ? '#ffa9a9' : '#a9ffa9'; ?>">
                        <?php echo $expiredCount > 0 ? '⚠️ Requires Attention' : '✓ All Clear'; ?>
                    </span>
                    &nbsp;|&nbsp;
                    <strong>Total Products:</strong> <?php echo $totalCount; ?>
                    &nbsp;|&nbsp;
                    <strong>Value at Risk:</strong> Ksh <?php echo number_format($valueAtRisk, 2); ?>
                </p>
                <p style="font-size: 14px; opacity: 0.9;">
                    <?php if($expiredCount > 0): ?>
                        <i class="fas fa-exclamation-circle"></i> Immediate action required for <?php echo $expiredCount; ?> expired item(s).
                    <?php elseif($expiryCount > 0): ?>
                        <i class="fas fa-clock"></i> Monitor <?php echo $expiryCount; ?> item(s) expiring within 90 days.
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i> All inventory items are within safe expiry limits.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Set default dates for date inputs
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const ninetyDaysLater = new Date();
            ninetyDaysLater.setDate(ninetyDaysLater.getDate() + 90);
            const ninetyDaysLaterStr = ninetyDaysLater.toISOString().split('T')[0];
            
            // Set default values for filter dates
            const startDateInput = document.getElementById('dt');
            const endDateInput = document.getElementById('dt2');
            
            if (startDateInput && !startDateInput.value) {
                startDateInput.value = today;
            }
            if (endDateInput && !endDateInput.value) {
                endDateInput.value = ninetyDaysLaterStr;
            }
        });
        
        // Highlight expired and critical items
        document.addEventListener('DOMContentLoaded', function() {
            const criticalItems = document.querySelectorAll('.status-critical, .status-expired');
            criticalItems.forEach(item => {
                item.closest('tr').style.animation = 'pulse 2s infinite';
            });
        });
    </script>
    
    <style>
        @keyframes pulse {
            0% { background-color: inherit; }
            50% { background-color: rgba(220, 53, 69, 0.1); }
            100% { background-color: inherit; }
        }
        
        .expired-row {
            animation: pulse 2s infinite !important;
        }
    </style>

    <?php
    include "includes/footer.php";
    ?>
</body>
</html>