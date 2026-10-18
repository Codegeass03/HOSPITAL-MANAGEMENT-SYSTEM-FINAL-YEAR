<?php
session_start();

// Check for admin session - adjust the session key based on your login system
if(!isset($_SESSION["admin"]) && !isset($_SESSION["user"]))
{
    header("location: index.php");
    exit();
}

// Get the admin username from session
$admin_username = isset($_SESSION["admin"]) ? $_SESSION["admin"] : (isset($_SESSION["user"]) ? $_SESSION["user"] : "");

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

function sanitize_input($data) {
    global $link;
    return mysqli_real_escape_string($link, trim($data));
}

$message = "";
$message_type = "";

// ... [rest of your existing code for handling form submissions] ...

// Get data for dropdowns and tables
$suppliers = mysqli_query($link, "SELECT DISTINCT businessname, productcategory FROM suppliers ORDER BY businessname");
$companies = mysqli_query($link, "SELECT DISTINCT company_name FROM company_name ORDER BY company_name");

// DEBUG: Check if suppliers query works
if (!$suppliers) {
    error_log("Suppliers query error: " . mysqli_error($link));
}

// FIXED: Get pending purchases - Added backticks around column names
$pending_purchases_query = "
    SELECT pm.*, s.businessname as supplier_name
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    WHERE pm.`status` = 'pending'
    ORDER BY pm.purchase_date DESC, pm.id DESC
";

$pending_purchases = mysqli_query($link, $pending_purchases_query);

// DEBUG: Check for query errors
if (!$pending_purchases) {
    $error = mysqli_error($link);
    error_log("Pending purchases query error: " . $error);
    // Fallback to simpler query
    $pending_purchases = mysqli_query($link, "
        SELECT *, party_name as supplier_name
        FROM purchase_master
        WHERE `status` = 'pending'
        ORDER BY purchase_date DESC, id DESC
    ");
}

// FIXED: Get all purchases (recent 20) - Added backticks
$all_purchases_query = "
    SELECT pm.*, s.businessname as supplier_name
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    ORDER BY pm.purchase_date DESC, pm.id DESC
    LIMIT 20
";

$all_purchases = mysqli_query($link, $all_purchases_query);

// DEBUG: Check for query errors
if (!$all_purchases) {
    $error = mysqli_error($link);
    error_log("All purchases query error: " . $error);
    // Fallback to simpler query
    $all_purchases = mysqli_query($link, "
        SELECT *, party_name as supplier_name
        FROM purchase_master
        ORDER BY purchase_date DESC, id DESC
        LIMIT 20
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management - Improved Workflow</title>
    <style>
        /* ... [your existing CSS styles] ... */
    </style>
</head>
<body>
    <div id="content">
        <div class="container-fluid" style="padding: 20px 150px;">
            
            <div class="page-header">
                <h1>Purchase Management System</h1>
                <p>Record purchase orders and confirm receipts to update inventory</p>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Add debug info for testing -->
            <?php if (mysqli_error($link)): ?>
                <div class="alert alert-error">
                    Database Error: <?php echo htmlspecialchars(mysqli_error($link)); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('purchase')">Record Purchase Order</button>
                <button class="tab" onclick="switchTab('pending')">Pending Purchases</button>
                <button class="tab" onclick="switchTab('all')">All Purchases</button>
            </div>

            <!-- Record Purchase Tab -->
            <div id="purchase" class="tab-content active">
                <div class="form-container">
                    <div class="note-box">
                        <strong>📝 Note:</strong> This records a purchase order. Stock will NOT be updated until you confirm receipt in the "Pending Purchases" tab.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Supplier: <span class="required">*</span></label>
                                <select name="company_name" id="company_name" onchange="select_company(this.value)" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    // Reset pointer and fetch suppliers
                                    if ($suppliers) {
                                        mysqli_data_seek($suppliers, 0);
                                        while($sup = mysqli_fetch_assoc($suppliers)) {
                                            echo "<option value='" . htmlspecialchars($sup['businessname']) . "'>" . htmlspecialchars($sup['businessname']) . "</option>";
                                        }
                                    } else {
                                        echo "<option value=''>Error loading suppliers</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Product Category: <span class="required">*</span></label>
                                <input type="text" name="product_category" id="product_category" readonly placeholder="Auto-filled">
                            </div>
                        </div>

                        <!-- ... [rest of your form remains the same] ... -->

            <!-- Pending Purchases Tab -->
            <div id="pending" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Order Date</th>
                                <th>Product</th>
                                <th>Company</th>
                                <th>Supplier</th>
                                <th>Ordered Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($pending_purchases && mysqli_num_rows($pending_purchases) > 0) {
                                while($row = mysqli_fetch_assoc($pending_purchases)) {
                                    $total = $row['quantity'] * $row['price'];
                                    // Use supplier_name if available from JOIN, otherwise use party_name
                                    $supplier_display = $row['supplier_name'] ?? $row['party_name'];
                                    echo "<tr>";
                                    echo "<td><strong>#" . $row['id'] . "</strong></td>";
                                    echo "<td>" . date('M d, Y', strtotime($row['purchase_date'])) . "</td>";
                                    echo "<td><strong>" . htmlspecialchars($row['product_name']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($supplier_display) . "</td>";
                                    echo "<td>" . $row['quantity'] . " " . htmlspecialchars($row['unit']) . "</td>";
                                    echo "<td>KES " . number_format($row['price'], 2) . "</td>";
                                    echo "<td>KES " . number_format($total, 2) . "</td>";
                                    echo "<td><span class='status-badge status-pending'>Pending</span></td>";
                                    echo "<td>";
                                    echo "<button onclick='showReceiveForm(" . $row['id'] . ", \"" . htmlspecialchars(addslashes($row['product_name'])) . "\", " . $row['quantity'] . ")' class='action-btn btn-confirm'>";
                                    echo "✓ Confirm Receipt";
                                    echo "</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' style='text-align: center; padding: 30px;'>No pending purchases</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- ... [rest of your code remains the same] ... -->

                 <style>
        :root {
            --primary: #1976d2;
            --primary-light: #42a5f5;
            --primary-dark: #1565c0;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light-bg: #e3f2fd;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        #content {
            padding-left: 240px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
            background: white;
            padding: 15px 15px 0;
            border-radius: 10px 10px 0 0;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: #666;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab:hover {
            color: var(--primary);
            background: var(--light-bg);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: var(--light-bg);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-container {
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .required {
            color: var(--danger);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 1px solid #90caf9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .form-group input[readonly] {
            background: #f0f8ff;
            cursor: not-allowed;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(25, 118, 210, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        td {
            font-size: 14px;
            color: #333;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-received {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .btn-confirm {
            background: var(--success);
            color: white;
        }

        .receive-form {
            background: #f8f9fa;
            border: 2px solid var(--primary-light);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .receive-form h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }

        .note-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .note-box strong {
            color: #856404;
        }
    </style>