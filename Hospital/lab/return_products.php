<?php
session_start();
if(!isset($_SESSION["admin"]))
{
?>
<!-- <script type="text/javascript">
  window.location="index.php";
</script> -->
<?php
}
?>
<?php  
include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

// Switch to hospital database
mysqli_select_db($link, "hospital");

// Check if id parameter exists
if(!isset($_GET["id"]) || empty($_GET["id"])) {
    die("<script>alert('Product ID is required.'); window.history.back();</script>");
}

$id = intval($_GET["id"]);
$success_message = "";
$error_message = "";

// Get the product details from pharmacy_billing_details
$product_details = [];
$res = mysqli_query($link, "SELECT pbd.*, pb.bill_no, pb.CustomerName 
                           FROM pharmacy_billing_details pbd 
                           JOIN pharmacy_billing pb ON pbd.billing_id = pb.id 
                           WHERE pbd.id = $id");

if($res && mysqli_num_rows($res) > 0) {
    $product_details = mysqli_fetch_array($res);
} else {
    die("<script>alert('Product not found or already returned.'); window.history.back();</script>");
}

// Handle return form submission
if(isset($_POST["submit1"])) {
    $return_quantity = intval($_POST["return_quantity"]);
    $reason = mysqli_real_escape_string($link, $_POST["reason"]);
    
    if($return_quantity <= 0) {
        $error_message = "Please enter a valid return quantity.";
    } elseif($return_quantity > $product_details["quantity"]) {
        $error_message = "Return quantity cannot exceed original purchase quantity.";
    } else {
        // Start transaction
        mysqli_begin_transaction($link);
        
        try {
            // 1. Calculate return amount
            $return_amount = $product_details["price"] * $return_quantity;
            
            // 2. Check if return_products table exists, if not create it
            $table_check = mysqli_query($link, "SHOW TABLES LIKE 'return_products'");
            if(mysqli_num_rows($table_check) == 0) {
                // Create return_products table if it doesn't exist
                $create_table = "CREATE TABLE return_products (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    bill_no VARCHAR(50) NOT NULL,
                    return_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    product_company VARCHAR(255) NOT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    product_unit VARCHAR(100) NOT NULL,
                    packing_size VARCHAR(100) NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    quantity INT(11) NOT NULL,
                    total DECIMAL(10,2) NOT NULL,
                    returned_by VARCHAR(100),
                    reason TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                if(!mysqli_query($link, $create_table)) {
                    throw new Exception("Failed to create return_products table: " . mysqli_error($link));
                }
            }
            
            // 3. Insert into return_products table
            $username = $_SESSION["username"] ?? 'system';
            $insert_return = "INSERT INTO return_products 
                             (bill_no, product_company, product_name, product_unit, packing_size, price, quantity, total, returned_by, reason) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $insert_return);
            mysqli_stmt_bind_param($stmt, "sssssdidss", 
                $product_details["bill_no"],
                $product_details["product_company"],
                $product_details["product_name"],
                $product_details["product_unit"],
                $product_details["packing_size"],
                $product_details["price"],
                $return_quantity,
                $return_amount,
                $username,
                $reason
            );
            
            if(!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert return record: " . mysqli_error($link));
            }
            
            // 4. Update stock in php_ims database
            mysqli_select_db($link, "php_ims");
            $update_stock = "UPDATE stock_master SET product_quantity = product_quantity + ? 
                            WHERE product_company = ? AND product_name = ? AND product_unit = ?";
            $stmt_stock = mysqli_prepare($link, $update_stock);
            mysqli_stmt_bind_param($stmt_stock, "isss", 
                $return_quantity,
                $product_details["product_company"],
                $product_details["product_name"],
                $product_details["product_unit"]
            );
            
            if(!mysqli_stmt_execute($stmt_stock)) {
                throw new Exception("Failed to update stock: " . mysqli_error($link));
            }
            
            // 5. Update pharmacy_billing_details if partial return
            mysqli_select_db($link, "hospital");
            if($return_quantity < $product_details["quantity"]) {
                $new_quantity = $product_details["quantity"] - $return_quantity;
                $new_total = $product_details["price"] * $new_quantity;
                
                $update_billing = "UPDATE pharmacy_billing_details 
                                  SET quantity = ?, total_amount = ? 
                                  WHERE id = ?";
                $stmt_update = mysqli_prepare($link, $update_billing);
                mysqli_stmt_bind_param($stmt_update, "idi", 
                    $new_quantity,
                    $new_total,
                    $id
                );
                
                if(!mysqli_stmt_execute($stmt_update)) {
                    throw new Exception("Failed to update billing details: " . mysqli_error($link));
                }
            } else {
                // Full return - delete the billing detail record
                $delete_billing = "DELETE FROM pharmacy_billing_details WHERE id = ?";
                $stmt_delete = mysqli_prepare($link, $delete_billing);
                mysqli_stmt_bind_param($stmt_delete, "i", $id);
                
                if(!mysqli_stmt_execute($stmt_delete)) {
                    throw new Exception("Failed to delete billing detail: " . mysqli_error($link));
                }
            }
            
            // 6. Update pharmacy_billing total if columns exist
            $column_check = mysqli_query($link, "SHOW COLUMNS FROM pharmacy_billing LIKE 'total_amount'");
            if(mysqli_num_rows($column_check) > 0) {
                $update_billing_total = "UPDATE pharmacy_billing 
                                        SET total_amount = total_amount - ?, 
                                            balance_amount = balance_amount - ? 
                                        WHERE bill_no = ?";
                $stmt_billing = mysqli_prepare($link, $update_billing_total);
                mysqli_stmt_bind_param($stmt_billing, "dds", 
                    $return_amount,
                    $return_amount,
                    $product_details["bill_no"]
                );
                mysqli_stmt_execute($stmt_billing);
            }
            
            // Commit transaction
            mysqli_commit($link);
            
            $success_message = "Product returned successfully! Return amount: Ksh " . number_format($return_amount, 2);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($link);
            $error_message = "Error processing return: " . $e->getMessage();
        }
        
        // Switch back to hospital database
        mysqli_select_db($link, "hospital");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Pharmacy Product</title>
    <style>
        .container-fluid {
            padding: 20px;
        }
        .row-fluid {
            background: white;
            border-radius: 5px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .form-horizontal .control-group {
            margin-bottom: 20px;
        }
        .form-horizontal .control-label {
            padding-top: 5px;
            text-align: right;
            font-weight: 600;
            color: #495057;
        }
        .form-horizontal .controls {
            margin-left: 0;
        }
        .form-control {
            border: 1px solid #666666;
            border-radius: 4px;
            padding: 8px 12px;
            height: 38px;
            width: 100%;
        }
        textarea.form-control {
            height: 100px;
            resize: vertical;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            margin-right: 10px;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .product-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin-bottom: 30px;
        }
        .product-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .product-info td {
            padding: 8px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .product-info td:first-child {
            font-weight: 600;
            color: #495057;
            width: 150px;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .return-summary {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .return-summary h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        #content-header {
            margin-bottom: 20px;
        }
        #breadcrumb a {
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
        }
        #breadcrumb a:hover {
            color: #495057;
        }
        .database-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div id="content" style="padding-left: 300px;">
        <!--breadcrumbs-->
        <div id="content-header">
            <div id="breadcrumb">
                <a href="bills.php" title="Go to Bills" class="tip-bottom">
                    <i class="icon-home"></i> RETURN TO PHARMACY BILLS
                </a>
            </div>
        </div>
        <!--End-breadcrumbs-->

        <!--Action boxes-->
        <div class="container-fluid">
            <div class="row-fluid">
                <!-- Database Info -->
                <div class="database-info">
                    <strong><i class="fa fa-database"></i> Database:</strong> Processing pharmacy product return in HOSPITAL database
                </div>

                <center>
                    <h3>Return Pharmacy Product</h3>
                </center>

                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
                        <br><br>
                        <a href="return_products.php" class="btn btn-success">View All Returns</a>
                        <a href="bills.php" class="btn btn-warning">Back to Bills</a>
                    </div>
                <?php elseif(!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fa fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if(empty($success_message) && !empty($product_details)): ?>
                
                <!-- Product Information -->
                <div class="product-info">
                    <h4>Product Details</h4>
                    <table>
                        <tr>
                            <td>Bill Number:</td>
                            <td><strong><?php echo htmlspecialchars($product_details["bill_no"]); ?></strong></td>
                        </tr>
                        <tr>
                            <td>Customer Name:</td>
                            <td><strong><?php echo htmlspecialchars($product_details["CustomerName"]); ?></strong></td>
                        </tr>
                        <tr>
                            <td>Product Company:</td>
                            <td><?php echo htmlspecialchars($product_details["product_company"]); ?></td>
                        </tr>
                        <tr>
                            <td>Product Name:</td>
                            <td><strong><?php echo htmlspecialchars($product_details["product_name"]); ?></strong></td>
                        </tr>
                        <tr>
                            <td>Unit:</td>
                            <td><?php echo htmlspecialchars($product_details["product_unit"]); ?></td>
                        </tr>
                        <tr>
                            <td>Packing Size:</td>
                            <td><?php echo htmlspecialchars($product_details["packing_size"]); ?></td>
                        </tr>
                        <tr>
                            <td>Price:</td>
                            <td>Ksh <?php echo number_format($product_details["price"], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Original Quantity:</td>
                            <td><strong><?php echo $product_details["quantity"]; ?></strong></td>
                        </tr>
                        <tr>
                            <td>Original Total:</td>
                            <td><strong>Ksh <?php echo number_format($product_details["price"] * $product_details["quantity"], 2); ?></strong></td>
                        </tr>
                    </table>
                </div>

                <!-- Return Form -->
                <form class="form-horizontal" action="" name="form1" method="post">
                    <div class="control-group">
                        <label class="control-label">Return Quantity</label>
                        <div class="controls">
                            <input type="number" class="form-control" name="return_quantity" 
                                   value="<?php echo $product_details["quantity"]; ?>" 
                                   min="1" max="<?php echo $product_details["quantity"]; ?>" 
                                   required onchange="calculateReturn()">
                            <small style="color: #6c757d;">Maximum: <?php echo $product_details["quantity"]; ?> units</small>
                        </div>
                    </div>

                    <div class="control-group">
                        <label class="control-label">Return Reason</label>
                        <div class="controls">
                            <textarea class="form-control" name="reason" placeholder="Enter reason for return..." required></textarea>
                        </div>
                    </div>

                    <!-- Return Summary -->
                    <div class="return-summary">
                        <h4>Return Summary</h4>
                        <div id="returnSummary">
                            Return Amount: Ksh <span id="returnAmount"><?php echo number_format($product_details["price"] * $product_details["quantity"], 2); ?></span>
                            <br>
                            <small>This amount will be deducted from the patient's outstanding balance.</small>
                        </div>
                    </div>

                    <div class="control-group">
                        <div class="controls">
                            <button type="submit" name="submit1" class="btn btn-success">
                                <i class="fa fa-undo"></i> Process Return
                            </button>
                            <button type="button" class="btn btn-warning" onclick="window.history.back()">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function calculateReturn() {
            var returnQuantity = document.querySelector('input[name="return_quantity"]').value;
            var price = <?php echo $product_details["price"] ?? 0; ?>;
            var returnAmount = returnQuantity * price;
            
            document.getElementById('returnAmount').textContent = returnAmount.toFixed(2);
        }

        // Initialize calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateReturn();
        });
    </script>

    <?php 
    include "includes/footer.php";
    ?>
</body>
</html>