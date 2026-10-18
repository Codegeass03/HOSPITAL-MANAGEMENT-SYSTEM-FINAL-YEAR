<?php
include "admin/connection.php";
include "includes/db_connect.php";

// Get current date
$current_date = date('Y-m-d');
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));

// Get filter values
$company_filter = isset($_POST['company_name']) ? $_POST['company_name'] : '';
$start_date_filter = isset($_POST['start_date']) ? $_POST['start_date'] : $thirty_days_ago;
$end_date_filter = isset($_POST['end_date']) ? $_POST['end_date'] : $current_date;

// Initialize $link if not set
if(!isset($link)) {
    $link = new mysqli("127.0.0.1", "root", "", "php_ims");
    if ($link->connect_error) {
        die("Connection failed: " . $link->connect_error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History Report - AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e6f7f7 100%);
            min-height: 100vh;
            margin: 0;
            color: #333;
        }

        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
            margin-left: 10px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 128, 128, 0.1);
            border: 1px solid var(--secondarycolor);
        }

        .header-content h1 {
            font-size: 28px;
            color: var(--primarydark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-content p {
            color: #666;
            font-size: 15px;
        }

        /* Search and Filter Section */
        .search-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0, 128, 128, 0.1);
            border: 1px solid var(--secondarycolor);
        }

        .search-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group select,
        .form-group input {
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--primarylight);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(0, 179, 179, 0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, var(--successcolor) 0%, #219653 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .btn-clear {
            background: linear-gradient(135deg, var(--warningcolor) 0%, #e67e22 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-clear:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }

        /* Summary Cards */
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

        /* Results Section */
        .results-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 128, 128, 0.1);
            margin-top: 25px;
            border: 1px solid var(--secondarycolor);
        }

        .results-header {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            padding: 18px 25px;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: var(--teallight);
            border-bottom: 2px solid var(--bordercolor);
        }

        thead th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--primarydark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--bordercolor);
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: var(--teallight);
        }

        tbody td {
            padding: 15px;
            color: #475569;
            font-size: 14px;
            border: none;
        }

        /* Status Badges */
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

        /* Active Filters */
        .active-filters {
            background: var(--teallight);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid var(--secondarycolor);
        }

        .active-filters-title {
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tag {
            background: var(--primarylight);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Price and Quantity Styling */
        .price-cell {
            font-weight: 600;
            color: var(--successcolor);
        }

        .quantity-cell {
            font-weight: 600;
            color: var(--primarycolor);
            background: #e6f7f7;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* No Results */
        .no-results {
            padding: 60px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
            color: var(--primarylight);
        }

        .no-results p {
            font-size: 16px;
            margin-top: 10px;
        }

        /* Row Status */
        .expired-row {
            background-color: #f8d7da !important;
        }

        .expiring-row {
            background-color: #fff3cd !important;
        }

        .recent-row {
            background-color: #e3f2fd !important;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .search-controls {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            tbody td, thead th {
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .page-header {
                padding: 20px;
            }

            .header-content h1 {
                font-size: 22px;
            }

            .btn-search,
            .btn-clear {
                padding: 10px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-shopping-cart"></i> Purchase History Report</h1>
                <p>View and analyze all purchase records</p>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form class="search-controls" name="form1" method="post" action="">
                <div class="form-group">
                    <label for="company_name"><i class="fas fa-building"></i> Select Company</label>
                    <select class="form-control" name="company_name" id="company_name">
                        <option value="">-- All Companies --</option>
                        <?php 
                        $res = mysqli_query($link, "SELECT DISTINCT party_name FROM purchase_master ORDER BY party_name");
                        while($row = mysqli_fetch_array($res)) {
                            $selected = ($company_filter == $row["party_name"]) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($row["party_name"]) . "' $selected>" . htmlspecialchars($row["party_name"]) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_date"><i class="fas fa-calendar"></i> Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" 
                           value="<?php echo $start_date_filter; ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date"><i class="fas fa-calendar"></i> End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                           value="<?php echo $end_date_filter; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" name="submit1" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                
                <div class="form-group">
                    <button type="button" name="submit2" class="btn-clear" onclick="window.location.href=window.location.href">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </form>

            <?php if(isset($_POST["submit1"]) || $company_filter != ''): ?>
            <div class="active-filters">
                <div class="active-filters-title">
                    <i class="fas fa-filter"></i> Active Filters
                </div>
                <div class="filter-tags">
                    <?php if($company_filter != ''): ?>
                    <span class="filter-tag">
                        <i class="fas fa-building"></i>
                        Company: <?php echo htmlspecialchars($company_filter); ?>
                    </span>
                    <?php endif; ?>
                    <span class="filter-tag">
                        <i class="fas fa-calendar"></i>
                        Date: <?php echo date('M d, Y', strtotime($start_date_filter)); ?> - <?php echo date('M d, Y', strtotime($end_date_filter)); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php
        // Build WHERE conditions
        $where_conditions = [];
        
        if ($company_filter != '') {
            $where_conditions[] = "party_name = '" . mysqli_real_escape_string($link, $company_filter) . "'";
        }
        
        if (isset($_POST["submit1"])) {
            $where_conditions[] = "(purchase_date >= '" . mysqli_real_escape_string($link, $start_date_filter) . "' AND purchase_date <= '" . mysqli_real_escape_string($link, $end_date_filter) . "')";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Get purchase summary
        $summary_query = "
            SELECT 
                COUNT(*) as total_count,
                SUM(quantity) as total_quantity,
                SUM(price * quantity) as total_value,
                AVG(price * quantity) as avg_purchase
            FROM purchase_master
            $where_clause
        ";
        
        $summary_result = mysqli_query($link, $summary_query);
        $summary = mysqli_fetch_assoc($summary_result);
        $totalCount = $summary['total_count'] ?? 0;
        $totalQuantity = $summary['total_quantity'] ?? 0;
        $totalValue = $summary['total_value'] ?? 0;
        $avgPurchase = $summary['avg_purchase'] ?? 0;

        // Get recent purchases (last 7 days)
        $recent_where = $where_clause;
        if ($recent_where) {
            $recent_where .= " AND purchase_date >= '$seven_days_ago'";
        } else {
            $recent_where = "WHERE purchase_date >= '$seven_days_ago'";
        }
        
        $recent_query = "
            SELECT 
                COUNT(*) as recent_count,
                SUM(quantity) as recent_quantity,
                SUM(price * quantity) as recent_value
            FROM purchase_master
            $recent_where
        ";
        $recent_result = mysqli_query($link, $recent_query);
        $recent = mysqli_fetch_assoc($recent_result);
        $recentCount = $recent['recent_count'] ?? 0;
        $recentQuantity = $recent['recent_quantity'] ?? 0;
        $recentValue = $recent['recent_value'] ?? 0;
        ?>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-value"><?php echo $totalCount; ?></div>
                <div class="summary-label">Total Purchases</div>
                <div class="summary-note">All purchase records</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-value"><?php echo $totalQuantity; ?></div>
                <div class="summary-label">Total Quantity</div>
                <div class="summary-note">Units purchased</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-value">
                    Ksh <?php echo number_format($totalValue, 2); ?>
                </div>
                <div class="summary-label">Total Value</div>
                <div class="summary-note">Purchase amount</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-value">
                    Ksh <?php echo number_format($avgPurchase, 2); ?>
                </div>
                <div class="summary-label">Average Purchase</div>
                <div class="summary-note">Per transaction</div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            <div class="results-header">
                <i class="fas fa-list"></i> Purchase Records (<?php echo $totalCount; ?> records found)
                <?php if($recentCount > 0): ?>
                <span style="margin-left: auto; font-size: 13px; background: var(--successcolor); color: white; padding: 4px 10px; border-radius: 15px;">
                    <i class="fas fa-clock"></i> <?php echo $recentCount; ?> recent purchases
                </span>
                <?php endif; ?>
            </div>
            
            <?php 
            // Get purchase records
            $query = "SELECT * FROM purchase_master $where_clause ORDER BY purchase_date DESC";
            $res = mysqli_query($link, $query);
            $row_count = mysqli_num_rows($res);
            ?>
            
            <?php if($row_count > 0) { ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Company</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Purchase Date</th>
                            <th>Expiry</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while ($row = mysqli_fetch_array($res)) {
                            $count++;
                            $total = $row["price"] * $row["quantity"];
                            $expiry_date = $row["expiry_date"];
                            $purchase_date = $row["purchase_date"];
                            
                            // Calculate expiry status
                            $expiry_days = floor((strtotime($expiry_date) - strtotime($current_date)) / (60 * 60 * 24));
                            if ($expiry_days < 0) {
                                $expiry_status = 'status-expired';
                                $status_text = 'EXPIRED';
                                $row_class = 'expired-row';
                            } elseif ($expiry_days <= 30) {
                                $expiry_status = 'status-critical';
                                $status_text = 'SOON';
                                $row_class = 'expiring-row';
                            } elseif ($expiry_days <= 90) {
                                $expiry_status = 'status-warning';
                                $status_text = 'WATCH';
                                $row_class = '';
                            } else {
                                $expiry_status = 'status-normal';
                                $status_text = 'OK';
                                $row_class = '';
                            }
                            
                            // Check if recent purchase
                            $purchase_days = floor((strtotime($current_date) - strtotime($purchase_date)) / (60 * 60 * 24));
                            if ($purchase_days <= 7) {
                                $row_class = $row_class ? $row_class . ' recent-row' : 'recent-row';
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo $count; ?></td>
                            <td><strong><?php echo htmlspecialchars($row["party_name"] ?? ''); ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($row["product_name"] ?? ''); ?></div>
                                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($row["company_name"] ?? ''); ?></div>
                            </td>
                            <td><span class="quantity-cell"><?php echo htmlspecialchars($row["quantity"] ?? ''); ?></span></td>
                            <td class="price-cell">Ksh <?php echo number_format($row["price"] ?? 0, 2); ?></td>
                            <td><strong>Ksh <?php echo number_format($total, 2); ?></strong></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($purchase_date)); ?>
                                <?php if($purchase_days <= 7): ?>
                                <div style="font-size: 11px; color: var(--successcolor); margin-top: 3px;">
                                    <i class="fas fa-clock"></i> Recent
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $expiry_status; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                                <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                    <?php echo date('M d, Y', strtotime($expiry_date)); ?>
                                </div>
                            </td>
                            <td>
                                <span style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo htmlspecialchars($row["purchase_method"] ?? ''); ?>
                                </span>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } else { ?>
            <div class="no-results">
                <i class="fas fa-inbox"></i>
                <p>No purchase records found for the selected filters.</p>
            </div>
            <?php } ?>
        </div>
        
        <!-- Summary Footer -->
        <?php if($row_count > 0): ?>
        <div style="margin-top: 25px; padding: 20px; background: var(--primarycolor); color: white; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h4 style="margin: 0 0 10px 0; color: white; font-size: 16px;">
                        <i class="fas fa-chart-bar"></i> Purchase Summary
                    </h4>
                    <p style="font-size: 14px; opacity: 0.9;">
                        Total Value: <strong>Ksh <?php echo number_format($totalValue, 2); ?></strong> • 
                        Average Purchase: <strong>Ksh <?php echo number_format($avgPurchase, 2); ?></strong> • 
                        Recent (7 Days): <strong><?php echo $recentCount; ?> purchases</strong>
                    </p>
                </div>
                <div style="font-size: 12px; opacity: 0.8;">
                    <i class="fas fa-info-circle"></i> Report generated on <?php echo date('F j, Y, g:i A'); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Set default dates for date inputs if not already set
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];
            
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && !startDateInput.value) {
                startDateInput.value = thirtyDaysAgoStr;
            }
            if (endDateInput && !endDateInput.value) {
                endDateInput.value = today;
            }
        });
    </script>
</body>
</html>