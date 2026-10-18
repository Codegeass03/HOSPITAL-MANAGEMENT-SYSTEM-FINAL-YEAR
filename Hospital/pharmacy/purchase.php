<?php
session_start();



// Get the admin username from session
$admin_username = isset($_SESSION["username"]) ? $_SESSION["username"] : (isset($_SESSION["user"]) ? $_SESSION["user"] : "");

include("config/database.php");
include("includes/sidebar.php");
include("admin/connection.php");

function sanitize_input($data) {
    global $link;
    return mysqli_real_escape_string($link, trim($data));
}

$message = "";
$message_type = "";

// Handle purchase submission (ONLY records purchase order, doesn't update stock)
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'purchase') {
    $company_name = sanitize_input($_POST['company_name']);
    $productcategory = sanitize_input($_POST['product_category']);
    $product_name = sanitize_input($_POST['product_name']);
    $unit = sanitize_input($_POST['unit']);
    $packing_size = sanitize_input($_POST['packing_size']);
    $quantity = sanitize_input($_POST['quantity']);
    $price = sanitize_input($_POST['price']);
   $selling_price = sanitize_input($_POST['selling_price']);

   
    $party_name = sanitize_input($_POST['party_name']);
    $purchase_method = sanitize_input($_POST['purchase_method']);
    $expiry_date = sanitize_input($_POST['expiry_date']);
    $purchase_date = date('Y-m-d');
    $admin_user = sanitize_input($admin_username);
    
    // Insert into purchase_master with 'pending' status
   $sql1 = "INSERT INTO purchase_master 
(company_name, productcategory, product_name, unit, packing_size, 
 quantity, price, product_selling_price, party_name, purchase_method, expiry_date, 
 purchase_date, username, status) 
VALUES 
('$company_name', '$productcategory', '$product_name', '$unit', '$packing_size', 
 '$quantity', '$price', '$selling_price', '$party_name', '$purchase_method', '$expiry_date', 
 '$purchase_date', '{$_SESSION['username']}', 'pending')";

    
    if (mysqli_query($link, $sql1)) {
        $purchase_id = mysqli_insert_id($link);
        $message = "✅ Purchase Order #$purchase_id recorded successfully! Status: <strong>Pending</strong>.";
        $message_type = "success";
    } else {
        $message = "Error recording purchase: " . mysqli_error($link);
        $message_type = "error";
    }
}

// Handle receiving purchase (confirms batch and updates stock)
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'receive_purchase') {
    $purchase_id = sanitize_input($_POST['purchase_id']);
    $received_quantity = sanitize_input($_POST['received_quantity']);
    $notes = sanitize_input($_POST['notes']);
    
    // Get purchase details
    $purchase_query = mysqli_query($link, "SELECT * FROM purchase_master WHERE id = '$purchase_id' AND status = 'pending'");
    
    if (mysqli_num_rows($purchase_query) > 0) {
        $purchase = mysqli_fetch_assoc($purchase_query);
        $selling_price = $purchase['product_selling_price'];

        $company_name = $purchase['company_name'];
        $product_name = $purchase['product_name'];
        $unit = $purchase['unit'];
        $packing_size = $purchase['packing_size'];
        $ordered_quantity = $purchase['quantity'];
        $price = $purchase['price'];
        $admin_user = sanitize_input($_SESSION['username']);
        
        // Validate received quantity
       $received_quantity = min($received_quantity, $purchase['quantity']);


        // Update purchase status to 'received'
        $update_sql = "UPDATE purchase_master SET 
                      status = 'received', 
                      received_date = NOW(),
                      received_by = '{$_SESSION['username']}',
                      notes = '$notes',
                     received_quantity = '$received_quantity'
                      WHERE id = '$purchase_id'";
        
        if (mysqli_query($link, $update_sql)) {
            // Now update stock
            $check = mysqli_query($link, "SELECT * FROM stock_master WHERE product_name='$product_name' AND product_company='$company_name' AND product_unit='$unit' AND packing_size='$packing_size'");
            
            if (mysqli_num_rows($check) > 0) {
                // Update existing stock
                
              $sql2 = "UPDATE stock_master SET 
    product_quantity = product_quantity + $received_quantity,
    product_selling_price = '$selling_price'
WHERE product_name='$product_name'
AND product_company='$company_name'
AND product_unit='$unit'
AND packing_size='$packing_size'";

            } else {
                // Add new stock item
                $sql2 = "INSERT INTO stock_master 
                        (product_company, product_name, product_unit, packing_size, product_quantity, product_selling_price) 
                        VALUES 
                        ('$company_name', '$product_name', '$unit', '$packing_size', '$received_quantity', '$selling_price')";
            }
            
            if (mysqli_query($link, $sql2)) {
                $message = "✅ Purchase #$purchase_id confirmed as received! Stock updated with $received_quantity units.";
                $message_type = "success";
            } else {
                $message = "⚠️ Purchase marked as received but error updating stock: " . mysqli_error($link);
                $message_type = "warning";
            }
        } else {
            $message = "Error updating purchase status: " . mysqli_error($link);
            $message_type = "error";
        }
    } else {
        $message = "Purchase not found or already processed!";
        $message_type = "error";
    }
}

// Get data for dropdowns and tables
$suppliers = mysqli_query($link, "SELECT DISTINCT businessname, productcategory FROM suppliers ORDER BY businessname");
$companies = mysqli_query($link, "SELECT DISTINCT company_name FROM company_name ORDER BY company_name");

// Get pending purchases
$pending_purchases = mysqli_query($link, "
    SELECT pm.*, s.businessname as supplier_name
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    WHERE pm.status = 'pending'
    ORDER BY pm.purchase_date DESC, pm.id DESC
");

// Get all purchases (recent 20)
$all_purchases = mysqli_query($link, "
    SELECT pm.*, s.businessname as supplier_name
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    ORDER BY pm.purchase_date DESC, pm.id DESC
    LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management - Improved Workflow</title>
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

            <div class="tabs">
                <button class="tab active" onclick="switchTab('purchase')">Record Purchase Order</button>
                <button class="tab" onclick="switchTab('pending')">Pending Purchases</button>
                <button class="tab" onclick="switchTab('all')">All Purchases</button>
            </div>

            <!-- Record Purchase Tab -->
            <div id="purchase" class="tab-content active">
                <div class="form-container">
                    <!-- <div class="note-box">
                        <strong>📝 Note:</strong> This records a purchase order. Stock will NOT be updated until you confirm receipt in the "Pending Purchases" tab.
                    </div> -->
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Supplier: <span class="required">*</span></label>
                                <select name="company_name" id="company_name" onchange="select_company(this.value)" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    mysqli_data_seek($suppliers, 0);
                                    while($sup = mysqli_fetch_assoc($suppliers)) {
                                        echo "<option value='{$sup['businessname']}'>{$sup['businessname']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Product Category: <span class="required">*</span></label>
                                <input type="text" name="product_category" id="product_category" readonly placeholder="Auto-filled">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Product Name: <span class="required">*</span></label>
                                <div id="product_name_div">
                                    <select name="product_name" class="span11" required>
                                        <option value="">Select supplier first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Unit: <span class="required">*</span></label>
                                <div id="unit_div">
                                    <select name="unit" class="span11" required>
                                        <option value="">Select product first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Packing Size:</label>
                                <div id="packing_size_div">
                                    <select name="packing_size" class="span11">
                                        <option value="">Select unit first</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Quantity: <span class="required">*</span></label>
                                <input type="number" name="quantity" min="1" value="0" required>
                            </div>

                            <div class="form-group">
                                <label>Purchase Price (per unit): <span class="required">*</span></label>
                                <input type="number" name="price" step="0.01" min="0" value="0" required>
                            </div>

                            <div class="form-group">
                                <label>Selling Price (per unit): <span class="required">*</span></label>
                                <input type="number" name="selling_price" step="0.01" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Supplier Name: <span class="required">*</span></label>
                                <select name="party_name" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    mysqli_data_seek($suppliers, 0);
                                    while($sup = mysqli_fetch_assoc($suppliers)) {
                                        echo "<option value='{$sup['businessname']}'>{$sup['businessname']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Payment Method: <span class="required">*</span></label>
                                <select name="purchase_method" required>
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="Mpesa">M-Pesa</option>
                                    <option value="Debit card">Card</option>
                                    <!-- <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit">Credit</option> -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Expiry Date: <span class="required">*</span></label>
                                <input type="date" name="expiry_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            📝 Record Purchase Order
                        </button>
                    </form>
                </div>
            </div>

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
                            if (mysqli_num_rows($pending_purchases) > 0) {
                                while($row = mysqli_fetch_assoc($pending_purchases)) {
                                    $total = $row['quantity'] * $row['price'];
                                    echo "<tr>";
                                    echo "<td><strong>#" . $row['id'] . "</strong></td>";
                                    echo "<td>" . date('M d, Y', strtotime($row['purchase_date'])) . "</td>";
                                    echo "<td><strong>" . htmlspecialchars($row['product_name']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['supplier_name'] ?? $row['party_name']) . "</td>";
                                    echo "<td>" . $row['quantity'] . " " . htmlspecialchars($row['unit']) . "</td>";
                                    echo "<td>KES " . number_format($row['price'], 2) . "</td>";
                                    echo "<td>KES " . number_format($total, 2) . "</td>";
                                    echo "<td><span class='status-badge status-pending'>Pending</span></td>";
                                    echo "<td>";
                                    echo "<button onclick='showReceiveForm(" . $row['id'] . ", \"" . htmlspecialchars($row['product_name']) . "\", " . $row['quantity'] . ")' class='action-btn btn-confirm'>";
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

                <!-- Receive Form (Hidden by default) -->
                <div id="receiveFormContainer" style="display: none;">
                    <div class="receive-form">
                        <h3>🔔 Confirm Purchase Receipt</h3>
                        <form method="POST" id="receivePurchaseForm">
                            <input type="hidden" name="action" value="receive_purchase">
                            <input type="hidden" name="purchase_id" id="receive_purchase_id">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Product:</label>
                                    <input type="text" id="receive_product_name" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Ordered Quantity:</label>
                                    <input type="text" id="receive_ordered_qty" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Received Quantity: <span class="required">*</span></label>
                                    <input type="number" name="received_quantity" id="receive_qty" min="1" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes (optional):</label>
                                <textarea name="notes" rows="3" placeholder="Any remarks about the received batch..."></textarea>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn btn-success">
                                    ✓ Confirm Receipt & Update Stock
                                </button>
                                <button type="button" onclick="hideReceiveForm()" class="btn btn-secondary">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- All Purchases Tab -->
            <div id="all" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Purchase Date</th>
                                <th>Product</th>
                                <th>Company</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Received Date</th>
                                <th>Received By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($all_purchases) > 0) {
                                while($row = mysqli_fetch_assoc($all_purchases)) {
                                    $total = $row['quantity'] * $row['price'];
                                    $status = $row['status'] ?? 'pending';
                                    $status_class = "status-" . $status;
                                    
                                    echo "<tr>";
                                    echo "<td><strong>#" . $row['id'] . "</strong></td>";
                                    echo "<td>" . date('M d, Y', strtotime($row['purchase_date'])) . "</td>";
                                    echo "<td><strong>" . htmlspecialchars($row['product_name']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['supplier_name'] ?? $row['party_name']) . "</td>";
                                    echo "<td>" . $row['quantity'] . " " . htmlspecialchars($row['unit']) . "</td>";
                                    echo "<td>KES " . number_format($row['price'], 2) . "</td>";
                                    echo "<td>KES " . number_format($total, 2) . "</td>";
                                    echo "<td><span class='status-badge $status_class'>" . ucfirst($status) . "</span></td>";
                                    echo "<td>" . ($row['received_date'] ? date('M d, Y', strtotime($row['received_date'])) : '-') . "</td>";
                                    echo "<td>" . ($row['received_by'] ? htmlspecialchars($row['received_by']) : '-') . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='11' style='text-align: center; padding: 30px;'>No purchases found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script type="text/javascript">
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            hideReceiveForm();
        }

        function showReceiveForm(purchaseId, productName, orderedQty) {
            document.getElementById('receive_purchase_id').value = purchaseId;
            document.getElementById('receive_product_name').value = productName;
            document.getElementById('receive_ordered_qty').value = orderedQty + ' units';
            document.getElementById('receive_qty').value = orderedQty;
            document.getElementById('receive_qty').max = orderedQty;
            
            document.getElementById('receiveFormContainer').style.display = 'block';
            document.getElementById('receiveFormContainer').scrollIntoView({ behavior: 'smooth' });
        }

        function hideReceiveForm() {
            document.getElementById('receiveFormContainer').style.display = 'none';
        }

        function select_company(company_name) {
            if(company_name === "" || company_name === "Select") {
                document.getElementById("product_category").value = "";
                return;
            }
            
            fetchProductCategory(company_name);
            
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("product_name_div").innerHTML = xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/load_product_using_company.php?company_name=" + encodeURIComponent(company_name), true);
            xmlhttp.send();
        }

        function fetchProductCategory(company_name) {
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "get_product_category.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            
            xhr.onreadystatechange = function() {
                if(xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("product_category").value = xhr.responseText;
                }
            };
            
            xhr.send("businessname=" + encodeURIComponent(company_name));
        }

        function select_product(product_name, company_name) {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("unit_div").innerHTML = xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/load_unit_using_products.php?product_name=" + encodeURIComponent(product_name) + "&company_name=" + encodeURIComponent(company_name), true);
            xmlhttp.send();
        }

        function select_unit(unit, product_name, company_name) {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("packing_size_div").innerHTML = xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/load_packingsize_using_unit.php?unit=" + encodeURIComponent(unit) + "&product_name=" + encodeURIComponent(product_name) + "&company_name=" + encodeURIComponent(company_name), true);
            xmlhttp.send();
        }
    </script>
</body>
</html>

<?php
include "includes/footer.php";
?>