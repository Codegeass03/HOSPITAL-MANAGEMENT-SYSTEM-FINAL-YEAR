<?php

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Purchase Records - AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
            margin-left: 280px;
            width: calc(100% - 280px);
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

        .btn-add-supplier {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 128, 128, 0.3);
            font-size: 14px;
        }

        .btn-add-supplier:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 128, 128, 0.4);
            background: linear-gradient(135deg, var(--primarydark) 0%, var(--primarycolor) 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            border: 1px solid var(--secondarycolor);
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.2s ease;
            padding: 5px;
        }

        .modal-close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 8px;
            display: block;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primarylight);
            box-shadow: 0 0 0 3px rgba(0, 179, 179, 0.1);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            padding: 20px 25px;
            background: var(--teallight);
            border-top: 1px solid var(--bordercolor);
            border-radius: 0 0 12px 12px;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 128, 128, 0.3);
        }

        /* Active Filter Display */
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

        /* Price Styling */
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            tbody td, thead th {
                padding: 12px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
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

            .btn-add-supplier,
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
                <h1><i class="fas fa-truck"></i> Supplier Purchase Records</h1>
                <p>Manage and track all supplier purchases</p>
            </div>
            <!-- <button class="btn-add-supplier" onclick="window.location.href='supplier2.php'">
                <i class="fas fa-plus"></i> Add New Supplier
            </button> -->
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form class="search-controls" name="form1" method="post" action="">
                <div class="form-group">
                    <label for="company_name"><i class="fas fa-building"></i> Select Company</label>
                    <select class="form-control" name="company_name" id="company_name">
                        <option value="">-- All Companies --</option>
                        <?php 
                        $res = mysqli_query($link, "SELECT DISTINCT businessname FROM suppliers ORDER BY businessname");
                        while($row = mysqli_fetch_array($res)) {
                            $selected = (isset($_POST["company_name"]) && $_POST["company_name"] == $row["businessname"]) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($row["businessname"]) . "' $selected>" . htmlspecialchars($row["businessname"]) . "</option>";
                        }
                        ?>
                    </select>
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

            <?php if(isset($_POST["submit1"]) && !empty($_POST["company_name"])): ?>
            <div class="active-filters">
                <div class="active-filters-title">
                    <i class="fas fa-filter"></i> Active Filters
                </div>
                <div class="filter-tags">
                    <span class="filter-tag">
                        <i class="fas fa-building"></i>
                        Company: <?php echo htmlspecialchars($_POST["company_name"]); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php
        if(isset($_POST["submit1"]) && !empty($_POST["company_name"])) {
            $company_name = mysqli_real_escape_string($link, $_POST["company_name"]);
            
            // Get purchase summary
            $summary_query = mysqli_query($link, 
                "SELECT 
                    COUNT(*) as total_count,
                    SUM(quantity) as total_quantity,
                    SUM(price * quantity) as total_value,
                    AVG(price * quantity) as avg_purchase
                FROM purchase_master 
                WHERE party_name='$company_name'"
            );
            $summary = mysqli_fetch_assoc($summary_query);
            $totalCount = $summary['total_count'] ?? 0;
            $totalQuantity = $summary['total_quantity'] ?? 0;
            $totalValue = $summary['total_value'] ?? 0;
            $avgPurchase = $summary['avg_purchase'] ?? 0;

            // Get actual purchase records
            $res = mysqli_query($link, 
                "SELECT * FROM purchase_master 
                WHERE party_name='$company_name' 
                ORDER BY purchase_date DESC"
            );
            $row_count = mysqli_num_rows($res);
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
                <i class="fas fa-list"></i> Purchase Records (<?php echo $row_count; ?> records found)
            </div>
            <?php if($row_count > 0) { ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product Name</th>
                            <th>Unit</th>
                            <th>Packing Size</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Purchase Method</th>
                            <th>Expiry Date</th>
                            <th>Purchase Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while ($row = mysqli_fetch_array($res)) {
                            $count++;
                            $total = $row["price"] * $row["quantity"];
                            $expiry_date = $row["expiry_date"];
                            $current_date = date('Y-m-d');
                            $expiry_days = floor((strtotime($expiry_date) - strtotime($current_date)) / (60 * 60 * 24));
                            
                            // Determine expiry status
                            if ($expiry_days < 0) {
                                $expiry_status = 'status-critical';
                                $status_text = 'EXPIRED';
                            } elseif ($expiry_days <= 30) {
                                $expiry_status = 'status-critical';
                                $status_text = 'SOON';
                            } elseif ($expiry_days <= 90) {
                                $expiry_status = 'status-warning';
                                $status_text = 'WATCH';
                            } else {
                                $expiry_status = 'status-normal';
                                $status_text = 'OK';
                            }
                        ?>
                        <tr>
                            <td><?php echo $count; ?></td>
                            <td><strong><?php echo htmlspecialchars($row["product_name"] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($row["unit"] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row["packing_size"] ?? ''); ?></td>
                            <td><span class="quantity-cell"><?php echo htmlspecialchars($row["quantity"] ?? ''); ?></span></td>
                            <td class="price-cell">Ksh <?php echo number_format($row["price"] ?? 0, 2); ?></td>
                            <td><strong>Ksh <?php echo number_format($total, 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($row["purchase_method"] ?? ''); ?></td>
                            <td>
                                <span class="status-badge <?php echo $expiry_status; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                                <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                    <?php echo date('M d, Y', strtotime($expiry_date)); ?>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row["purchase_date"] ?? '')); ?></td>
                            <td>
                                <button style="background: var(--primarylight); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } else { ?>
            <div class="no-results">
                <i class="fas fa-inbox"></i>
                <p>No purchase records found for "<?php echo htmlspecialchars($_POST["company_name"]); ?>".</p>
            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
        <!-- Default View - Show recent purchases -->
        <div class="results-section">
            <div class="results-header">
                <i class="fas fa-history"></i> Recent Purchases (Last 30 Days)
            </div>
            <?php
            $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
            $recent_query = mysqli_query($link, 
                "SELECT * FROM purchase_master 
                WHERE purchase_date >= '$thirty_days_ago' 
                ORDER BY purchase_date DESC 
                LIMIT 10"
            );
            $recent_count = mysqli_num_rows($recent_query);
            ?>
            
            <?php if($recent_count > 0) { ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Company</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Purchase Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while ($row = mysqli_fetch_array($recent_query)) {
                            $count++;
                            $expiry_date = $row["expiry_date"];
                            $current_date = date('Y-m-d');
                            $expiry_days = floor((strtotime($expiry_date) - strtotime($current_date)) / (60 * 60 * 24));
                            
                            // Determine expiry status
                            if ($expiry_days < 0) {
                                $expiry_status = 'status-critical';
                                $status_text = 'EXPIRED';
                            } elseif ($expiry_days <= 30) {
                                $expiry_status = 'status-critical';
                                $status_text = 'SOON';
                            } elseif ($expiry_days <= 90) {
                                $expiry_status = 'status-warning';
                                $status_text = 'WATCH';
                            } else {
                                $expiry_status = 'status-normal';
                                $status_text = 'OK';
                            }
                        ?>
                        <tr>
                            <td><?php echo $count; ?></td>
                            <td><strong><?php echo htmlspecialchars($row["party_name"] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($row["product_name"] ?? ''); ?></td>
                            <td><span class="quantity-cell"><?php echo htmlspecialchars($row["quantity"] ?? ''); ?></span></td>
                            <td class="price-cell">Ksh <?php echo number_format($row["price"] ?? 0, 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row["purchase_date"] ?? '')); ?></td>
                            <td>
                                <span class="status-badge <?php echo $expiry_status; ?>">
                                    <?php echo $status_text; ?>
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
                <p>No recent purchases found. Select a company from above to view purchase records.</p>
            </div>
            <?php } ?>
        </div>
        <?php } ?>
    </div>

    <!-- Add Supplier Modal (Keep existing modal code) -->
    <div id="addSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-truck"></i> Add New Supplier</h2>
                <button class="modal-close" onclick="window.location.href='supplier2.php'">&times;</button>
            </div>
            <form id="supplierPurchaseForm">
                <div class="modal-body">
                    <div class="form-grid">
                        <!-- Supplier Information Section -->
                        <div class="form-group full-width" style="margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: var(--primarydark); font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid var(--bordercolor); padding-bottom: 0.5rem;">
                                <i class="fas fa-building"></i> Supplier Information
                            </h4>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Name *</label>
                            <input type="text" name="company_name" class="form-input" placeholder="Enter company name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person *</label>
                            <input type="text" name="contact_person" class="form-input" placeholder="Contact person name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" placeholder="supplier@company.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone No. *</label>
                            <input type="tel" name="phone" class="form-input" placeholder="Enter phone number" required>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-input" placeholder="Enter supplier address">
                        </div>

                        <!-- Product Information Section -->
                        <div class="form-group full-width" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: var(--primarydark); font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid var(--bordercolor); padding-bottom: 0.5rem;">
                                <i class="fas fa-box"></i> Product Details
                            </h4>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="product_name" class="form-input" placeholder="Enter product name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-input">
                                <option value="">Select category</option>
                                <option value="Medications">Medications</option>
                                <option value="Medical Supplies">Medical Supplies</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Consumables">Consumables</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit *</label>
                            <input type="text" name="unit" class="form-input" placeholder="e.g., Box, Carton, Pcs" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Packing Size *</label>
                            <input type="text" name="packing_size" class="form-input" placeholder="e.g., 10x100ml, 50 tabs" required>
                        </div>

                        <!-- Purchase Information Section -->
                        <div class="form-group full-width" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: var(--primarydark); font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid var(--bordercolor); padding-bottom: 0.5rem;">
                                <i class="fas fa-shopping-cart"></i> Purchase Details
                            </h4>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="quantity" class="form-input" placeholder="Enter quantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price per Unit *</label>
                            <input type="number" name="price" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Purchase Method *</label>
                            <select name="purchase_method" class="form-input" required>
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit">Credit</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Purchase Date *</label>
                            <input type="date" name="purchase_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-input" placeholder="Enter invoice number">
                        </div>

                        <!-- Additional Information Section -->
                        <div class="form-group full-width" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: var(--primarydark); font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid var(--bordercolor); padding-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Additional Information
                            </h4>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-input" placeholder="Add any additional notes about this purchase" style="min-height: 80px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddSupplierModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Supplier Purchase</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddSupplierModal() {
            document.getElementById('addSupplierModal').style.display = 'block';
        }

        function closeAddSupplierModal() {
            document.getElementById('addSupplierModal').style.display = 'none';
            document.getElementById('supplierPurchaseForm').reset();
        }

        window.onclick = function(event) {
            const modal = document.getElementById('addSupplierModal');
            if (event.target === modal) {
                closeAddSupplierModal();
            }
        }

        document.getElementById('supplierPurchaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Supplier purchase form would be submitted here');
            closeAddSupplierModal();
        });
    </script>
</body>
</html>

<?php 
include "includes/footer.php";
?>