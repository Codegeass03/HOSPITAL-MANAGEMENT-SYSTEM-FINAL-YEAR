<?php
session_start();

include "admin/connection.php"; // php_ims connection
include "includes/db_connect.php"; // hospital connection
include "includes/sidebar.php";

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Get next bill number from hospital database
$bill_id = 0;
try {
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM pharmacy_billing");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $bill_id = $result['max_id'] ?? 0;
} catch (PDOException $e) {
    error_log("Error getting max bill ID: " . $e->getMessage());
    $bill_id = 0;
}

// Handle prescription data
$prescription_data = [];
$from_prescription = false;
$patient_id = null;

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
            $_SESSION['current_prescription'] = $prescription_data;
        }
    } catch (PDOException $e) {
        error_log("Error fetching prescription: " . $e->getMessage());
    }
}

// Handle form submission
if (isset($_POST["submit1"])) {
    $lastbillno = 0;
    $patient_id = $_POST['patient_id'] ?? null;
    
    // Use the correct session variables from debug
    $username = $_SESSION['username'] ?? 'superadmin'; // Use username from session
    $user_id = $_SESSION['user_id'] ?? 55; // Use user_id from session

    try {
        // 1. FIRST: Check stock availability BEFORE processing
        $insufficient_stock = false;
        $stock_error_message = "";
        
        mysqli_select_db($link, "php_ims");
        
        foreach ($_SESSION['cart'] as $item) {
            // Check stock for each item
            $check_stmt = mysqli_prepare($link, "
                SELECT product_quantity 
                FROM stock_master 
                WHERE product_company = ? 
                  AND product_name = ? 
                  AND product_unit = ? 
                  AND packing_size = ?
                LIMIT 1
            ");

            mysqli_stmt_bind_param(
                $check_stmt,
                "ssss",
                $item['company_name'],
                $item['product_name'],
                $item['unit'],
                $item['packing_size']
            );

            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_bind_result($check_stmt, $available_stock);
            mysqli_stmt_fetch($check_stmt);
            mysqli_stmt_close($check_stmt);

            if ($available_stock < $item['quantity']) {
                $insufficient_stock = true;
                $stock_error_message = "Insufficient stock for {$item['product_name']}. Available: {$available_stock}";
                break;
            }
        }
        
        if ($insufficient_stock) {
            echo "<script>
                alert('{$stock_error_message}');
                window.location.href='pharmacy.php';
            </script>";
            exit();
        }
        
        // 2. Calculate total amount from cart
        $total_amount = 0;
        if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
            foreach ($_SESSION['cart'] as $item) {
                $total_amount += $item['price'] * $item['quantity'];
            }
        }

        // 3. Switch to hospital database to create billing record
        mysqli_select_db($link, "hospital");
        
        // Check if new columns exist by trying a simple query
        $check_columns_query = "SHOW COLUMNS FROM pharmacy_billing LIKE 'total_amount'";
        $columns_result = mysqli_query($link, $check_columns_query);
        $has_new_columns = mysqli_num_rows($columns_result) > 0;

        if ($has_new_columns) {
            // Use new columns if they exist
            $bill_type = $_POST['bill_type'];
            $payment_status = 'pending'; // All bills start as pending
            $paid_amount = 0; // No payment collected at pharmacy
            $balance_amount = $total_amount; // Full amount is outstanding

            $insert_billing = "INSERT INTO pharmacy_billing 
                              (CustomerName, bill_type, date, bill_no, username, patient_id, 
                               total_amount, paid_amount, balance_amount, payment_status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $insert_billing);
            mysqli_stmt_bind_param($stmt, "sssssiddds", 
                $_POST['full_name'], 
                $bill_type, 
                $_POST['date'], 
                $_POST['bill_no'], 
                $username,
                $patient_id,
                $total_amount,
                $paid_amount,
                $balance_amount,
                $payment_status
            );
        } else {
            // Use old column structure (without payment tracking)
            $insert_billing = "INSERT INTO pharmacy_billing 
                              (CustomerName, bill_type, date, bill_no, username, patient_id) 
                              VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $insert_billing);
            mysqli_stmt_bind_param($stmt, "sssssi", 
                $_POST['full_name'], 
                $_POST['bill_type'], 
                $_POST['date'], 
                $_POST['bill_no'], 
                $username,
                $patient_id
            );
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $lastbillno = mysqli_insert_id($link);
            
            // 4. Insert billing details
            if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                foreach ($_SESSION['cart'] as $item) {
                    if (!empty($item['company_name'])) {
                        $item_total_amount = $item['price'] * $item['quantity'];
                        
                        $insert_details = "INSERT INTO pharmacy_billing_details 
                                          (billing_id, product_company, product_name, product_unit, packing_size, price, quantity, total_amount, patient_id) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_details = mysqli_prepare($link, $insert_details);
                        mysqli_stmt_bind_param($stmt_details, "issssdidi", 
                            $lastbillno, 
                            $item['company_name'], 
                            $item['product_name'], 
                            $item['unit'], 
                            $item['packing_size'], 
                            $item['price'], 
                            $item['quantity'],
                            $item_total_amount,
                            $patient_id
                        );
                        mysqli_stmt_execute($stmt_details);
                    }
                }
            }

            // 5. NOW UPDATE STOCK - AFTER BILL IS CREATED
            mysqli_select_db($link, "php_ims");
            
            foreach ($_SESSION['cart'] as $item) {
                // Update stock using prepared statement
                $update_stmt = mysqli_prepare($link, "
                    UPDATE stock_master 
                    SET product_quantity = product_quantity - ? 
                    WHERE product_company = ? 
                      AND product_name = ? 
                      AND product_unit = ? 
                      AND packing_size = ?
                ");
                
                mysqli_stmt_bind_param(
                    $update_stmt,
                    "issss",
                    $item['quantity'],
                    $item['company_name'],
                    $item['product_name'],
                    $item['unit'],
                    $item['packing_size']
                );
                
                if (!mysqli_stmt_execute($update_stmt)) {
                    error_log("Stock update failed for: " . $item['product_name']);
                }
                mysqli_stmt_close($update_stmt);
                
                // Log stock movement for audit trail
                $log_stmt = mysqli_prepare($link, "
                    INSERT INTO stock_log (product_company, product_name, product_unit, packing_size, 
                                          quantity_change, transaction_type, reference_id, username, notes)
                    VALUES (?, ?, ?, ?, -?, 'sale', ?, ?, 'Pharmacy sale - Bill #{$_POST['bill_no']}')
                ");
                
                mysqli_stmt_bind_param(
                    $log_stmt,
                    "ssssiss",
                    $item['company_name'],
                    $item['product_name'],
                    $item['unit'],
                    $item['packing_size'],
                    $item['quantity'],
                    $lastbillno,
                    $username
                );
                
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
            }

            // 6. Mark prescription as dispensed if applicable - use user_id from session
            if (isset($_SESSION['current_prescription'])) {
                $prescription_id = $_SESSION['current_prescription']['prescription_id'];
                
                try {
                    mysqli_select_db($link, "hospital");
                    $update_prescription = "UPDATE prescriptions SET dispensed = 1, dispensed_date = NOW(), dispensed_by = ? WHERE id = ?";
                    $stmt_pdo = $pdo->prepare($update_prescription);
                    $stmt_pdo->execute([$user_id, $prescription_id]); // Use user_id from session
                    unset($_SESSION['current_prescription']);
                    
                } catch (PDOException $e) {
                    error_log("Error updating prescription: " . $e->getMessage());
                    // Continue with billing even if prescription update fails
                }
            }

            // 7. Clear cart and show success message
            $success_message = "✓ Medicine Dispensed Successfully!\\n";
            $success_message .= "Bill Number: {$_POST['bill_no']}\\n";
            $success_message .= "Patient: {$_POST['full_name']}\\n\\n";
            $success_message .= "Total Amount: Ksh " . number_format($total_amount, 2) . "\\n";
            $success_message .= "Payment Status: PENDING\\n\\n";
            $success_message .= "✓ Stock updated successfully!\\n";
            
            // Clear session data
            unset($_SESSION['cart']);
            if (isset($_SESSION['current_prescription'])) {
                unset($_SESSION['current_prescription']);
            }
            
            echo "<script type='text/javascript'>
                alert('" . addslashes($success_message) . "');
                window.location.href = 'pharmacy.php';
            </script>";
            exit();
            
        } else {
            throw new Exception("Failed to insert billing record: " . mysqli_error($link));
        }
        
    } catch (Exception $e) {
        echo "<script type='text/javascript'>
            alert('Error generating bill: " . addslashes($e->getMessage()) . "');
        </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Products - Unified Billing</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .form-horizontal .control-group { margin-bottom: 15px; }
        .form-horizontal .control-label { padding-top: 5px; text-align: right; }
        .form-horizontal .controls { margin-left: 0; }
        .widget-box { background: #fff; border: 1px solid #cdcdcd; margin-bottom: 20px; }
        .widget-title { background: #efefef; border-bottom: 1px solid #cdcdcd; padding: 10px 15px; }
        .widget-content { padding: 15px; }
        .span12, .span11, .span4, .span3, .span2, .span1 { float: left; margin-left: 20px; }
        .span12 { width: 80%; } .span11 { width: 91.5%; } .span4 { width: 30.5%; } 
        .span3 { width: 22.5%; } .span2 { width: 14.5%; } .span1 { width: 6.5%; }
        .row-fluid:after { content: ""; display: table; clear: both; }
        input[type="text"], select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-success { background: #5cb85c; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
        .bill-total { background: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #ddd; font-size: 18px; font-weight: bold; }
        .prescription-info { background: #e8f4fd; border: 1px solid #b8daff; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .prescription-info h4 { margin: 0 0 10px 0; color: #004085; }
        .prescription-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .prescription-detail { padding: 5px 0; }
        .prescription-detail strong { color: #004085; }
        .cart-item { background: #f9f9f9; margin: 5px 0; padding: 10px; border-radius: 4px; }
        .payment-status-info { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            border-radius: 4px; 
            padding: 15px; 
            margin: 15px 0; 
            font-size: 14px;
            text-align: center;
        }
        .payment-status-info i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        .unified-billing-notice {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-size: 13px;
            color: #0c5460;
        }
        .database-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-size: 13px;
            color: #721c24;
        }
        .stock-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 5px 0;
            font-size: 12px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div id="content" style="padding-left: 350px;">
        <form name="form1" action="" method="post" class="form-horizontal nopadding">
            <div id="content-header">
                <div id="breadcrumb">
                    <a href="pharmacy.php" class="tip-bottom"><i class="icon-home"></i> RETURN TO PHARMACY</a>
                </div>
            </div>

            <div class="container-fluid">
                <!-- Database Structure Warning -->
                <div class="row-fluid">
                    <div class="span12">
                        <!-- <div class="database-warning">
                            <strong><i class="fa fa-exclamation-triangle"></i> Database Update Required:</strong> 
                            The pharmacy_billing table needs additional columns for unified billing. 
                            <a href="update_pharmacy_db.php" style="color: #721c24; text-decoration: underline;">Click here to update database structure</a>
                        </div> -->
                    </div>
                </div>

                <!-- Prescription Information -->
                <?php if ($from_prescription && !empty($prescription_data)): ?>
                <div class="row-fluid">
                    <div class="span12">
                        <div class="prescription-info">
                            <h4><i class="fa fa-prescription"></i> Prescription Information</h4>
                            <div class="prescription-details">
                                <?php foreach($prescription_data as $key => $value): ?>
                                    <?php if (!in_array($key, ['prescription_id', 'patient_db_id']) && !empty($value)): ?>
                                    <div class="prescription-detail">
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($value); ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="prescription_id" value="<?php echo $prescription_data['prescription_id']; ?>">
                <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $patient_id; ?>">
                <?php endif; ?>

                <!-- Customer Information -->
                <div class="row-fluid">
                    <div class="span12">
                        <div class="widget-box">
                            <div class="widget-title"><span class="icon"><i class="icon-align-justify"></i></span><h5>Customer Information - Unified Billing System</h5></div>
                            <div class="widget-content nopadding">
                                <!-- Unified Billing Notice -->
                                <!-- <div class="unified-billing-notice">
                                    <strong><i class="fa fa-info-circle"></i> Unified Billing System:</strong> 
                                    All pharmacy bills are created as PENDING. Patients will pay all bills (hospital services + pharmacy) together at the billing counter.
                                </div> -->
                                
                                <div class="row-fluid">
                                    <div class="span4">
                                        <div class="control-group">
                                            <label class="control-label">Full Name</label>
                                            <div class="controls">
                                                <input type="text" class="span12" name="full_name" id="full_name"
                                                       value="<?php echo $from_prescription ? htmlspecialchars($prescription_data['patient_name']) : ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span3">
                                        <div class="control-group">
                                            <label class="control-label">Preferred Payment Method</label>
                                            <div class="controls">
                                                <select class="span12" name="bill_type" id="bill_type">
                                                    <option value="Cash">Cash</option>
                                                    <option value="Mpesa">Mpesa</option>
                                                    <option value="Debit">Debit Card</option>
                                                    <option value="Insurance">Insurance</option>
                                                    <option value="Bank Transfer">Bank Transfer</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span2">
                                        <div class="control-group">
                                            <label class="control-label">Date</label>
                                            <div class="controls">
                                                <input type="text" class="span12" name="date" value="<?php echo date("Y-m-d H:i:s") ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span2">
                                        <div class="control-group">
                                            <label class="control-label">Bill No</label>
                                            <div class="controls">
                                                <input type="text" class="span12" name="bill_no" value="<?php echo generate_bill_no($bill_id); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Status Information -->
                                <!-- <div class="row-fluid">
                                    <div class="span12">
                                        <div class="payment-status-info">
                                            <i class="fa fa-money-bill-wave"></i>
                                            <strong>UNIFIED BILLING SYSTEM</strong><br>
                                            <span>This bill will be added to patient's outstanding balance</span><br>
                                            <small>Patient will pay all bills together at the billing counter</small>
                                        </div>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Selection -->
                <div class="row-fluid">
                    <div class="span12">
                        <div class="widget-box">
                            <div class="widget-title"><span class="icon"><i class="icon-shopping-cart"></i></span><h5>Select A Product</h5></div>
                            <div class="widget-content nopadding">
                                <div class="row-fluid">
                                    <div class="span2">
                                        <div class="control-group">
                                            <label class="control-label">Product Company</label>
                                            <div class="controls">
                                                <select class="span11" name="company_name" id="company_name" onchange="select_company(this.value)">
                                                    <option value="">Select</option>
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
                                    </div>
                                    <div class="span2">
                                        <div class="control-group">
                                            <label class="control-label">Product Name</label>
                                            <div class="controls">
                                                <div id="product_name_div"><select class="span11" id="product_name"><option>Select</option></select></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span1">
                                        <div class="control-group">
                                            <label class="control-label">Unit</label>
                                            <div class="controls">
                                                <div id="unit_div"><select class="span11" id="unit"><option>Select</option></select></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span2">
                                        <div class="control-group">
                                            <label class="control-label">Packing Size</label>
                                            <div class="controls">
                                                <div id="packing_size_div"><select class="span11" id="packing_size"><option>Select</option></select></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span1">
                                        <div class="control-group">
                                            <label class="control-label">Price</label>
                                            <div class="controls">
                                                <input type="text" class="span11" name="price" id="price" readonly value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span1">
                                        <div class="control-group">
                                            <label class="control-label">Quantity</label>
                                            <div class="controls">
                                                <input type="text" class="span11" name="quantity" id="quantity" autocomplete="off" onkeyup="generate_total(this.value)">
                                                <div id="stock_warning" style="display: none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span1">
                                        <div class="control-group">
                                            <label class="control-label">Total</label>
                                            <div class="controls">
                                                <input type="text" class="span11" name="total" id="total" value="0" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span1">
                                        <div class="control-group">
                                            <label class="control-label">&nbsp;</label>
                                            <div class="controls">
                                                <input type="button" class="span11 btn btn-success" value="Add" onclick="add_session();">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Section -->
                <div class="row-fluid">
                    <div class="span12">
                        <div class="widget-box">
                            <div class="widget-title"><span class="icon"><i class="icon-list"></i></span><h5>Selected Products</h5></div>
                            <div class="widget-content nopadding">
                                <div id="bill_products">
                                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                        <?php foreach ($_SESSION['cart'] as $index => $item): ?>
    <div class="cart-item">
        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong> -
        <?php echo htmlspecialchars($item['company_name']); ?> -
        Qty: <?php echo $item['quantity']; ?> -
        Price: Ksh <?php echo number_format($item['price'], 2); ?> -
        Total: Ksh <?php echo number_format($item['price'] * $item['quantity'], 2); ?>

        <button 
            type="button"
            onclick="delete_cart_item(<?php echo $index; ?>)"
            style="float:right; background:#dc3545; color:#fff; border:none; padding:4px 8px; border-radius:3px; cursor:pointer;"
        >
            <i class="fa fa-trash"></i>
        </button>
    </div>
<?php endforeach; ?>

                                    <?php else: ?>
                                        <div style="padding: 20px; text-align: center; color: #666;">
                                            <i class="fa fa-shopping-cart" style="font-size: 48px; margin-bottom: 10px;"></i>
                                            <p>No products added to cart yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="bill-total">
                                    <span>Total Amount (Will be added to patient's outstanding balance): Kshs </span>
                                    <span id="totalbill">0</span>
                                </div>
                                <center>
                                    <input type="submit" name="submit1" value="Generate Bill (Unified Billing)" class="btn btn-success" style="margin: 20px 0; padding: 10px 30px; font-size: 16px;">
                                </center>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript">
        // Function to check stock before adding to cart
        function checkStockBeforeAdd() {
            var company_name = document.getElementById("company_name").value;
            var product_name = document.getElementById("product_name").value;
            var unit = document.getElementById("unit").value;
            var packing_size = document.getElementById("packing_size").value;
            var quantity = parseInt(document.getElementById("quantity").value) || 0;
            
            if (!company_name || !product_name || !unit || !packing_size || !quantity) {
                return true; // Let validation handle empty fields
            }
            
            // Check stock via AJAX
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "check_stock.php?company_name=" + encodeURIComponent(company_name) + 
                    "&product_name=" + encodeURIComponent(product_name) + 
                    "&unit=" + encodeURIComponent(unit) + 
                    "&packing_size=" + encodeURIComponent(packing_size) + 
                    "&quantity=" + quantity, false); // Synchronous for simplicity
            xhr.send();
            
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (!response.success) {
                    alert(response.message);
                    return false;
                }
                return true;
            }
            return true; // If check fails, proceed (will fail later anyway)
        }
        
        // Function to add session with stock check
        function add_session() {
            if (!checkStockBeforeAdd()) {
                return;
            }
            
            var product_company = document.getElementById("company_name").value;
            var product_name = document.getElementById("product_name").value;
            var unit = document.getElementById("unit").value;
            var packing_size = document.getElementById("packing_size").value;
            var price = parseFloat(document.getElementById("price").value) || 0;
            var quantity = parseInt(document.getElementById("quantity").value) || 0;
            var total = parseFloat(document.getElementById("total").value) || 0;

            if (!product_company || !product_name || !unit || !packing_size || !quantity || quantity <= 0) {
                alert("Please fill all required fields with valid values!");
                return;
            }

            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4) {
                    if (xmlhttp.status == 200) {
                        load_billing_products();
                        load_total_bill();
                        // Clear quantity field after adding
                        document.getElementById("quantity").value = "";
                        document.getElementById("total").value = "0";
                        alert("Product added to cart!");
                    } else {
                        alert(xmlhttp.responseText);
                    }
                }
            };

            xmlhttp.open("GET", "forajax/save_in_session.php?company_name=" + encodeURIComponent(product_company) + "&product_name=" + encodeURIComponent(product_name) + "&unit=" + encodeURIComponent(unit) + "&packing_size=" + encodeURIComponent(packing_size) + "&price=" + price + "&quantity=" + quantity + "&total=" + total, true);
            xmlhttp.send();
        }
        
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
                }
            };
            xmlhttp.open("GET", "forajax/load_price.php?company_name=" + encodeURIComponent(company_name) + "&product_name=" + encodeURIComponent(product_name) + "&unit=" + encodeURIComponent(unit) + "&packing_size=" + encodeURIComponent(packing_size), true);
            xmlhttp.send();
        }

        function generate_total(quantity) {
            var price = parseFloat(document.getElementById("price").value) || 0;
            var qty = parseFloat(quantity) || 0;
            document.getElementById("total").value = (price * qty).toFixed(2);
        }

        function load_billing_products() {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("bill_products").innerHTML = xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/load_billing_products.php", true);
            xmlhttp.send();
        }

        function load_total_bill() {
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    document.getElementById("totalbill").innerHTML = xmlhttp.responseText;
                }
            };
            xmlhttp.open("GET", "forajax/load_billing_amount.php", true);
            xmlhttp.send();
        }

        // Load initial cart
        load_billing_products();
        load_total_bill();

function delete_cart_item(index) {
    if (!confirm("Remove this product from cart?")) return;

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                load_billing_products();
                load_total_bill();
            } else {
                alert("Failed to remove product");
            }
        }
    };

    xhr.open("GET", "forajax/delete_cart_item.php?index=" + index, true);
    xhr.send();
}

function update_cart_quantity(index, quantity) {
    if (quantity <= 0) {
        alert("Quantity must be at least 1");
        load_billing_products();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                load_billing_products();
                load_total_bill();
            } else {
                alert("Failed to update quantity");
            }
        }
    };

    xhr.open("POST", "forajax/update_cart_quantity.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("index=" + index + "&quantity=" + quantity);
}



        function delete_cart_item(index) {
    if (!confirm("Remove this item from cart?")) {
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    load_billing_products();
                    load_total_bill();
                } else {
                    alert(response.message || "Failed to remove item");
                }
            } catch (e) {
                alert("Unexpected response from server");
            }
        }
    };

    xhr.open(
        "GET",
        "forajax/delete_cart_item.php?index=" + index,
        true
    );
    xhr.send();
}


    </script>

    <?php
    function generate_bill_no($id) {
        $id1 = ($id === "" || $id === 0) ? 0 : $id;
        $id1 = $id1 + 1;
        $len = strlen($id1);

        if ($len == 1) {
            $id1 = "PHARM-000" . $id1;
        } elseif ($len == 2) {
            $id1 = "PHARM-00" . $id1;
        } elseif ($len == 3) {
            $id1 = "PHARM-0" . $id1;
        } elseif ($len == 4) {
            $id1 = "PHARM-" . $id1;
        } else {
            $id1 = "PHARM-" . $id1;
        }

        return $id1;
    }
    ?>

    <?php include "includes/footer.php"; ?>
</body>
</html>