<?php
session_start();


// Include database connections
include "admin/connection.php"; // php_ims connection
include "includes/db_connect.php"; // hospital connection for prescriptions
include "includes/sidebar.php";

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Get next receipt number
function generate_receipt_no($conn) {
    $prefix = "PHARM";
    $year = date('Y');
    $month = date('m');
    
    // Get last receipt number
    $query = "SELECT receipt_no FROM pharmacy_sales 
              WHERE receipt_no LIKE '$prefix-$year-$month-%' 
              ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_no = $row['receipt_no'];
        $last_seq = intval(substr($last_no, -4));
        $new_seq = str_pad($last_seq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_seq = '0001';
    }
    
    return "$prefix-$year-$month-$new_seq";
}

// Handle prescription data
$prescription_data = [];
$from_prescription = false;
$patient_id = null;
$patient_name = "";

if (isset($_GET['prescription_id'])) {
    $prescription_id = $_GET['prescription_id'];
    $from_prescription = true;
    
    try {
        $prescription_stmt = $pdo->prepare("
            SELECT p.*, 
                   pt.id as patient_db_id, pt.first_name, pt.last_name, pt.patient_id,
                   u.first_name as doctor_first_name, u.last_name as doctor_last_name
            FROM prescriptions p
            JOIN patients pt ON p.patient_id = pt.id
            JOIN users u ON p.doctor_id = u.id
            WHERE p.id = ? AND p.status = 'active' AND p.dispensed = 0
        ");
        $prescription_stmt->execute([$prescription_id]);
        $prescription = $prescription_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prescription) {
            $prescription_data = [
                'prescription_id' => $prescription_id,
                'patient_name' => $prescription['first_name'] . ' ' . $prescription['last_name'],
                'patient_id' => $prescription['patient_id'],
                'patient_db_id' => $prescription['patient_db_id'],
                'medication_name' => $prescription['medication_name'],
                'dosage' => $prescription['dosage'],
                'frequency' => $prescription['frequency'],
                'duration' => $prescription['duration'],
                'doctor_name' => 'Dr. ' . $prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']
            ];
            
            $patient_id = $prescription['patient_db_id'];
            $patient_name = $prescription['first_name'] . ' ' . $prescription['last_name'];
            $_SESSION['current_prescription'] = $prescription_data;
        }
    } catch (PDOException $e) {
        error_log("Error fetching prescription: " . $e->getMessage());
    }
}

// Handle form submission
if (isset($_POST["submit_sale"])) {
    // Create MySQL connection for new tables
    $conn = new mysqli("localhost", "root", "", "hospital");
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $patient_id = $_POST['patient_id'] ?? null;
    $patient_name = $_POST['full_name'];
    $bill_type = $_POST['bill_type'];
    $sale_date = $_POST['date'];
    $receipt_no = generate_receipt_no($conn);
    $username = $_SESSION['admin'];
    
    // Calculate total amount from cart
    $total_amount = 0;
    $cart_items = [];
    
    if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
        foreach ($_SESSION['cart'] as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $total_amount += $item_total;
            
            $cart_items[] = [
                'company' => $item['company_name'],
                'product' => $item['product_name'],
                'unit' => $item['unit'],
                'packing' => $item['packing_size'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total' => $item_total
            ];
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert into pharmacy_sales
        $sql_sales = "INSERT INTO pharmacy_sales 
                     (receipt_no, patient_id, patient_name, bill_type, sale_date, 
                      total_amount, paid_amount, balance_amount, payment_status, 
                      prescription_id, dispensed_by, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'pending', ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_sales);
        $stmt->bind_param("sisssddiss", 
            $receipt_no,
            $patient_id,
            $patient_name,
            $bill_type,
            $sale_date,
            $total_amount,
            $total_amount, // balance_amount = total_amount initially
            $_POST['prescription_id'] ?? null,
            $username,
            $username
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save sale: " . $stmt->error);
        }
        
        $sale_id = $conn->insert_id;
        
        // 2. Insert sale items
        foreach ($cart_items as $item) {
            $sql_items = "INSERT INTO pharmacy_sale_items 
                         (sale_id, receipt_no, product_company, product_name, 
                          product_unit, packing_size, unit_price, quantity) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_items = $conn->prepare($sql_items);
            $stmt_items->bind_param("isssssdi",
                $sale_id,
                $receipt_no,
                $item['company'],
                $item['product'],
                $item['unit'],
                $item['packing'],
                $item['price'],
                $item['quantity']
            );
            
            if (!$stmt_items->execute()) {
                throw new Exception("Failed to save sale items: " . $stmt_items->error);
            }
            
            // 3. Update stock in php_ims database
            $conn_ims = new mysqli("localhost", "root", "", "php_ims");
            if (!$conn_ims->connect_error) {
                $sql_stock = "UPDATE stock_master 
                             SET product_quantity = product_quantity - ? 
                             WHERE product_company = ? 
                             AND product_name = ? 
                             AND product_unit = ?";
                
                $stmt_stock = $conn_ims->prepare($sql_stock);
                $stmt_stock->bind_param("isss",
                    $item['quantity'],
                    $item['company'],
                    $item['product'],
                    $item['unit']
                );
                $stmt_stock->execute();
                $conn_ims->close();
            }
        }
        
        // 4. Link prescription if applicable
        if (isset($_SESSION['current_prescription'])) {
            $prescription_id = $_SESSION['current_prescription']['prescription_id'];
            
            $sql_prescription = "INSERT INTO prescription_sales 
                                (prescription_id, sale_id, receipt_no, dispensed_by) 
                                VALUES (?, ?, ?, ?)";
            
            $stmt_prescription = $conn->prepare($sql_prescription);
            $stmt_prescription->bind_param("iiss",
                $prescription_id,
                $sale_id,
                $receipt_no,
                $username
            );
            
            if (!$stmt_prescription->execute()) {
                throw new Exception("Failed to link prescription: " . $stmt_prescription->error);
            }
            
            // Update prescription status in the main database
            $update_prescription = $pdo->prepare("UPDATE prescriptions 
                                                 SET dispensed = 1, dispensed_date = NOW(), dispensed_by = ? 
                                                 WHERE id = ?");
            $user_id = $_SESSION['admin_id'] ?? 0;
            $update_prescription->execute([$user_id, $prescription_id]);
            
            unset($_SESSION['current_prescription']);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Clear cart
        unset($_SESSION['cart']);
        
        // Success message
        $success_message = "✓ SALE COMPLETED SUCCESSFULLY!\n\n";
        $success_message .= "Receipt No: $receipt_no\n";
        $success_message .= "Patient: $patient_name\n";
        $success_message .= "Total Amount: Ksh " . number_format($total_amount, 2) . "\n";
        $success_message .= "Payment Status: PENDING\n\n";
        $success_message .= "═══════════════════════════════════\n";
        $success_message .= "📋 PATIENT INSTRUCTION:\n";
        $success_message .= "Please proceed to the BILLING COUNTER\n";
        $success_message .= "to complete payment for all services.\n";
        $success_message .= "═══════════════════════════════════";
        
        echo "<script type='text/javascript'>
            alert('" . addslashes($success_message) . "');
            window.location.href = 'pharmacy_sales_report.php';
        </script>";
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo "<script type='text/javascript'>
            alert('Error: " . addslashes($e->getMessage()) . "');
        </script>";
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Pharmacy Sales - Unified Billing System</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
        }

        .container {
            display: flex;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .sales-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .sales-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .sales-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .sales-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control:read-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .col {
            flex: 1;
            padding: 0 10px;
            min-width: 200px;
        }

        .col-2 { flex: 0 0 16.666%; max-width: 16.666%; }
        .col-3 { flex: 0 0 25%; max-width: 25%; }
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; }
        .col-6 { flex: 0 0 50%; max-width: 50%; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .prescription-info {
            background: #e8f4fd;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .prescription-info h4 {
            margin: 0 0 15px 0;
            color: #004085;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prescription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .prescription-detail {
            padding: 8px 0;
        }

        .prescription-detail strong {
            color: #004085;
            font-size: 14px;
        }

        .prescription-detail span {
            color: #333;
            font-size: 14px;
        }

        .cart-section {
            margin-top: 25px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cart-table th {
            background: #667eea;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .cart-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .cart-table tr:hover {
            background: #f8f9fa;
        }

        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .cart-empty {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .cart-empty i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .bill-total {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border: 1px solid #e0e0e0;
            font-size: 18px;
            font-weight: bold;
            text-align: right;
        }

        .bill-total span {
            color: #667eea;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .quantity-control button {
            padding: 5px 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }

        .quantity-control input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }

        .delete-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
        }

        .delete-btn:hover {
            color: #bd2130;
        }

        .submit-form-btn {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .submit-form-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .submit-form-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .col {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .prescription-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="color: white;">Pharmacy Sales</h2>
            </div>
            <div style="margin-top: 30px;">
                <a href="pharmacy.php" style="display: block; padding: 12px; color: white; text-decoration: none; border-radius: 6px; margin: 8px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-arrow-left"></i> Back to Pharmacy
                </a>
                <a href="pharmacy_sales_report.php" style="display: block; padding: 12px; color: white; text-decoration: none; border-radius: 6px; margin: 8px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-chart-line"></i> Sales Report
                </a>
                <a href="pharmacy_pending_payments.php" style="display: block; padding: 12px; color: white; text-decoration: none; border-radius: 6px; margin: 8px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-money-bill-wave"></i> Pending Payments
                </a>
                <a href="create_new_sales_tables.php" style="display: block; padding: 12px; color: white; text-decoration: none; border-radius: 6px; margin: 8px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-database"></i> Setup Database
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sales Header -->
            <div class="sales-card">
                <div class="sales-header">
                    <h1><i class="fa fa-capsules"></i> Pharmacy Sales</h1>
                    <p>Unified Billing System - Pharmacy Module</p>
                </div>

                <!-- Unified Billing Notice -->
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <div>
                        <strong>UNIFIED BILLING SYSTEM</strong><br>
                        All pharmacy bills are created as PENDING. Patients will pay all hospital services together at the billing counter.
                    </div>
                </div>

                <!-- Prescription Information -->
                <?php if ($from_prescription && !empty($prescription_data)): ?>
                <div class="prescription-info">
                    <h4><i class="fa fa-prescription"></i> Prescription Information</h4>
                    <div class="prescription-details">
                        <?php foreach($prescription_data as $key => $value): ?>
                            <?php if (!in_array($key, ['prescription_id', 'patient_db_id']) && !empty($value)): ?>
                            <div class="prescription-detail">
                                <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                <span><?php echo htmlspecialchars($value); ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- MAIN FORM - This wraps everything -->
            <form name="pharmacy_sales_form" action="" method="post" id="pharmacy_sales_form">
                <!-- Hidden fields for prescription data -->
                <?php if ($from_prescription && !empty($prescription_data)): ?>
                <input type="hidden" name="prescription_id" value="<?php echo $prescription_data['prescription_id']; ?>">
                <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $patient_id; ?>">
                <?php endif; ?>
                
                <!-- Customer Information Form -->
                <div class="sales-card">
                    <h3 style="margin-bottom: 20px; color: #333;"><i class="fa fa-user"></i> Customer Information</h3>
                    
                    <div class="row">
                        <div class="col col-4">
                            <div class="form-group">
                                <label for="full_name"><i class="fa fa-user"></i> Full Name *</label>
                                <input type="text" class="form-control" name="full_name" id="full_name" 
                                       value="<?php echo $patient_name; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="col col-3">
                            <div class="form-group">
                                <label for="bill_type"><i class="fa fa-credit-card"></i> Preferred Payment Method</label>
                                <select class="form-control" name="bill_type" id="bill_type">
                                    <option value="Cash">Cash</option>
                                    <option value="Mpesa">Mpesa</option>
                                    <option value="Debit">Debit Card</option>
                                    <option value="Insurance">Insurance</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col col-2">
                            <div class="form-group">
                                <label for="date"><i class="fa fa-calendar"></i> Date</label>
                                <input type="text" class="form-control" name="date" id="date" 
                                       value="<?php echo date("Y-m-d H:i:s") ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="col col-3">
                            <div class="form-group">
                                <label for="bill_no"><i class="fa fa-receipt"></i> Bill Number</label>
                                <?php
                                $temp_conn = new mysqli("localhost", "root", "", "hospital");
                                $receipt_no = generate_receipt_no($temp_conn);
                                $temp_conn->close();
                                ?>
                                <input type="text" class="form-control" name="bill_no" id="bill_no" 
                                       value="<?php echo $receipt_no; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-circle"></i>
                        <div>
                            <strong>NOTE:</strong> This bill will be added to patient's outstanding balance.
                            Patient will pay all bills together at the billing counter.
                        </div>
                    </div>
                </div>

                <!-- Product Selection -->
                <div class="sales-card">
                    <h3 style="margin-bottom: 20px; color: #333;"><i class="fa fa-shopping-cart"></i> Select Products</h3>
                    
                    <div class="row">
                        <div class="col col-2">
                            <div class="form-group">
                                <label for="company_name"><i class="fa fa-building"></i> Company</label>
                                <select class="form-control" name="company_name" id="company_name" onchange="select_company(this.value)">
                                    <option value="">Select Company</option>
                                    <?php
                                    mysqli_select_db($link, "php_ims");
                                    $res = mysqli_query($link, "SELECT DISTINCT product_company FROM stock_master WHERE product_quantity > 0 ORDER BY product_company");
                                    while ($row = mysqli_fetch_array($res)) {
                                        echo "<option value='" . htmlspecialchars($row["product_company"]) . "'>" . htmlspecialchars($row["product_company"]) . "</option>";
                                    }
                                    mysqli_select_db($link, "hospital");
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col col-2">
                            <div class="form-group">
                                <label for="product_name"><i class="fa fa-capsules"></i> Product</label>
                                <div id="product_name_div">
                                    <select class="form-control" id="product_name">
                                        <option value="">Select Product</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col col-2">
                            <div class="form-group">
                                <label for="unit"><i class="fa fa-weight"></i> Unit</label>
                                <div id="unit_div">
                                    <select class="form-control" id="unit">
                                        <option value="">Select Unit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col col-2">
                            <div class="form-group">
                                <label for="packing_size"><i class="fa fa-box"></i> Packing Size</label>
                                <div id="packing_size_div">
                                    <select class="form-control" id="packing_size">
                                        <option value="">Select Size</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col col-1">
                            <div class="form-group">
                                <label for="price"><i class="fa fa-tag"></i> Price</label>
                                <input type="text" class="form-control" name="price" id="price" readonly value="0">
                            </div>
                        </div>
                        
                        <div class="col col-1">
                            <div class="form-group">
                                <label for="quantity"><i class="fa fa-calculator"></i> Qty</label>
                                <input type="number" class="form-control" name="quantity" id="quantity" 
                                       min="1" value="1" onkeyup="calculate_total()">
                            </div>
                        </div>
                        
                        <div class="col col-1">
                            <div class="form-group">
                                <label for="total"><i class="fa fa-money-bill-wave"></i> Total</label>
                                <input type="text" class="form-control" name="total" id="total" value="0" readonly>
                            </div>
                        </div>
                        
                        <div class="col col-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary" onclick="add_to_cart()" style="width: 100%;">
                                    <i class="fa fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Section -->
                <div class="sales-card cart-section">
                    <h3 style="margin-bottom: 20px; color: #333;"><i class="fa fa-shopping-cart"></i> Cart Items</h3>
                    
                    <div id="cart_items">
                        <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Company</th>
                                        <th>Unit</th>
                                        <th>Size</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $grand_total = 0;
                                    foreach ($_SESSION['cart'] as $index => $item): 
                                        $item_total = $item['price'] * $item['quantity'];
                                        $grand_total += $item_total;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($item['packing_size']); ?></td>
                                        <td>Ksh <?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <div class="quantity-control">
                                                <button type="button" onclick="update_quantity(<?php echo $index; ?>, -1)">-</button>
                                                <input type="text" value="<?php echo $item['quantity']; ?>" 
                                                       id="qty_<?php echo $index; ?>" 
                                                       onchange="update_quantity_input(<?php echo $index; ?>, this.value)">
                                                <button type="button" onclick="update_quantity(<?php echo $index; ?>, 1)">+</button>
                                            </div>
                                        </td>
                                        <td>Ksh <?php echo number_format($item_total, 2); ?></td>
                                        <td>
                                            <button type="button" class="delete-btn" onclick="remove_from_cart(<?php echo $index; ?>)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="cart-empty">
                                <i class="fa fa-shopping-cart"></i>
                                <p>No products added to cart yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                    <div class="bill-total">
                        <strong>TOTAL AMOUNT (Will be added to patient's outstanding balance):</strong>
                        <span id="grand_total">Ksh <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Form Submission Buttons -->
                    <div class="action-buttons">
                        <button type="submit" name="submit_sale" class="submit-form-btn" 
                                id="submit_sale_btn"
                                <?php echo (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) ? 'disabled' : ''; ?>>
                            <i class="fa fa-check-circle"></i> Complete Sale (Pending Payment)
                        </button>
                        
                        <button type="button" class="btn btn-danger" onclick="clear_cart()">
                            <i class="fa fa-trash"></i> Clear Cart
                        </button>
                        
                        <a href="pharmacy.php" class="btn btn-warning">
                            <i class="fa fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script type="text/javascript">
        // Your existing AJAX functions
        function select_company(company_name) {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("product_name_div").innerHTML = xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/load_product_using_company.php?company_name=" + encodeURIComponent(company_name), true);
            xmlhttp.send();
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
                    $('#packing_size').on('change', function() {
                        load_price(document.getElementById("packing_size").value);
                    });
                }
            };
            xmlhttp.open("GET", "forajax/load_packingsize_using_unit.php?unit=" + encodeURIComponent(unit) + "&product_name=" + encodeURIComponent(product_name) + "&company_name=" + encodeURIComponent(company_name), true);
            xmlhttp.send();
        }

        function load_price(packing_size) {
            var company_name = document.getElementById("company_name").value;
            var product_name = document.getElementById("product_name").value;
            var unit = document.getElementById("unit").value;

            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("price").value = xmlhttp.responseText;
                    calculate_total();
                }
            };
            xmlhttp.open("GET", "forajax/load_price.php?company_name=" + encodeURIComponent(company_name) + "&product_name=" + encodeURIComponent(product_name) + "&unit=" + encodeURIComponent(unit) + "&packing_size=" + encodeURIComponent(packing_size), true);
            xmlhttp.send();
        }

        function calculate_total() {
            var price = parseFloat(document.getElementById("price").value) || 0;
            var qty = parseInt(document.getElementById("quantity").value) || 0;
            document.getElementById("total").value = (price * qty).toFixed(2);
        }

        function add_to_cart() {
            var product_company = document.getElementById("company_name").value;
            var product_name = document.getElementById("product_name").value;
            var unit = document.getElementById("unit").value;
            var packing_size = document.getElementById("packing_size").value;
            var price = parseFloat(document.getElementById("price").value) || 0;
            var quantity = parseInt(document.getElementById("quantity").value) || 0;

            if (!product_company || !product_name || !unit || !packing_size || !quantity || quantity <= 0) {
                alert("Please fill all required fields with valid values!");
                return;
            }

            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    load_cart_items();
                    alert("Product added to cart!");
                    // Clear form
                    document.getElementById("quantity").value = "1";
                    document.getElementById("total").value = "0";
                }
            };
            
            xmlhttp.open("GET", "forajax/save_in_session.php?company_name=" + encodeURIComponent(product_company) + 
                        "&product_name=" + encodeURIComponent(product_name) + 
                        "&unit=" + encodeURIComponent(unit) + 
                        "&packing_size=" + encodeURIComponent(packing_size) + 
                        "&price=" + price + "&quantity=" + quantity, true);
            xmlhttp.send();
        }

        function load_cart_items() {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("cart_items").innerHTML = xmlhttp.responseText;
                    update_complete_button();
                    update_grand_total();
                }
            };
            xmlhttp.open("GET", "forajax/load_cart_items.php", true);
            xmlhttp.send();
        }

        function update_quantity(index, change) {
            var current_qty = parseInt(document.getElementById("qty_" + index).value) || 0;
            var new_qty = current_qty + change;
            
            if (new_qty < 1) {
                new_qty = 1;
            }
            
            document.getElementById("qty_" + index).value = new_qty;
            update_quantity_input(index, new_qty);
        }

        function update_quantity_input(index, new_qty) {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    load_cart_items();
                }
            };
            xmlhttp.open("GET", "forajax/update_cart_quantity.php?index=" + index + "&quantity=" + new_qty, true);
            xmlhttp.send();
        }

        function remove_from_cart(index) {
            if (confirm("Are you sure you want to remove this item from cart?")) {
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                        load_cart_items();
                    }
                };
                xmlhttp.open("GET", "forajax/remove_from_cart.php?index=" + index, true);
                xmlhttp.send();
            }
        }

        function clear_cart() {
            if (confirm("Are you sure you want to clear all items from cart?")) {
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                        load_cart_items();
                    }
                };
                xmlhttp.open("GET", "forajax/clear_cart.php", true);
                xmlhttp.send();
            }
        }

        function update_complete_button() {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    var cart_count = parseInt(xmlhttp.responseText) || 0;
                    var complete_btn = document.getElementById('submit_sale_btn');
                    if (complete_btn) {
                        complete_btn.disabled = cart_count === 0;
                    }
                }
            };
            xmlhttp.open("GET", "forajax/get_cart_count.php", true);
            xmlhttp.send();
        }

        function update_grand_total() {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById('grand_total').innerHTML = 'Ksh ' + xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/get_grand_total.php", true);
            xmlhttp.send();
        }

        // Form submission confirmation
        document.getElementById('pharmacy_sales_form').addEventListener('submit', function(e) {
            var cart_count = <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>;
            
            if (cart_count === 0) {
                e.preventDefault();
                alert("Please add at least one product to the cart before completing the sale!");
                return false;
            }
            
            if (!confirm("Complete sale and add to patient's pending bills?\n\nPatient will pay at the billing counter.")) {
                e.preventDefault();
                return false;
            }
            
            // Show processing message
            var submitBtn = document.getElementById('submit_sale_btn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
        });

        // Initialize
        load_cart_items();
    </script>
</body>
</html>