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

// Check for both 'id' and 'bill_id' parameters (for compatibility)
$bill_id = null;
if(isset($_GET["bill_id"]) && $_GET["bill_id"] !== '' && $_GET["bill_id"] !== null) {
    $bill_id = $_GET["bill_id"];
} elseif(isset($_GET["id"]) && $_GET["id"] !== '' && $_GET["id"] !== null) {
    $bill_id = $_GET["id"];
}

// Validate that bill_id parameter exists
if($bill_id === null) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h3 style='color: #dc3545;'>Error: Invalid or missing Bill ID</h3>";
    echo "<p>Please select a valid bill to view details.</p>";
    echo "<p style='color: #6c757d; font-size: 14px;'>This page requires an ID parameter in the URL</p>";
    echo "<a href='bills.php' style='color: #007bff; text-decoration: none;'>Return to Pharmacy Bills</a>";
    echo "</div>";
    exit();
}

$total=0;
$id = intval($bill_id); // Sanitize the ID

// Check if ID is valid after conversion
if($id <= 0) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h3 style='color: #dc3545;'>Error: Invalid Bill ID</h3>";
    echo "<p>The provided Bill ID is not valid: " . htmlspecialchars($bill_id) . "</p>";
    echo "<a href='bills.php' style='color: #007bff; text-decoration: none;'>Return to Pharmacy Bills</a>";
    echo "</div>";
    exit();
}
$bill_type="";
$date="";
$bill_no="";
$username="";
$customerName="";
$patient_id="";
$payment_status = 'pending';
$total_amount = null;

// Query pharmacy_billing table instead of billing table
$res=mysqli_query($link, "select * from pharmacy_billing where id=$id");

if(!$res) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h3 style='color: #dc3545;'>Database Error</h3>";
    echo "<p>Error executing query: " . mysqli_error($link) . "</p>";
    echo "<a href='bills.php' style='color: #007bff; text-decoration: none;'>Return to Pharmacy Bills</a>";
    echo "</div>";
    exit();
}

if(mysqli_num_rows($res) == 0) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h3 style='color: #dc3545;'>Bill Not Found</h3>";
    echo "<p>No pharmacy bill found with ID: $id</p>";
    echo "<a href='bills.php' style='color: #007bff; text-decoration: none;'>Return to Pharmacy Bills</a>";
    echo "</div>";
    exit();
}

while($row=mysqli_fetch_array($res))
{
    $customerName=$row["CustomerName"];
    $bill_type=$row["bill_type"];
    $date=$row["date"];
    $bill_no=$row["bill_no"];
    $username=$row["username"];
    $patient_id=$row["patient_id"];
    
    // Check if new payment columns exist
    $payment_status = isset($row['payment_status']) ? $row['payment_status'] : 'pending';
    $total_amount = isset($row['total_amount']) ? $row['total_amount'] : null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Bill Details</title>
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
        }
        h4 {
            color: #343a40;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 600;
        }
        .bill-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin-bottom: 30px;
        }
        .bill-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .bill-info td {
            padding: 8px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .bill-info td:first-child {
            font-weight: 600;
            color: #495057;
            width: 150px;
        }
        .bill-info td:last-child {
            color: #6c757d;
        }
        .table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .table th {
            background: #343a40;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #454d55;
        }
        .table td {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .table tr:hover {
            background: #e9ecef;
        }
        .grand-total {
            background: #28a745;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            text-align: right;
            margin-top: 20px;
            display: inline-block;
            float: right;
        }
        a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        a:hover {
            text-decoration: underline;
        }
        .return-link {
            color: #dc3545 !important;
            font-weight: 600;
        }
        .return-link:hover {
            color: #c82333 !important;
            text-decoration: underline;
        }
        #content-header {
            margin-bottom: 20px;
        }
        #breadcrumb a {
            color: #6c757d;
            text-decoration: none;
        }
        #breadcrumb a:hover {
            color: #495057;
        }
        .tip-bottom {
            font-size: 14px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .payment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-paid {
            background: #d1edff;
            color: #004085;
            border: 1px solid #b3d7ff;
        }
    </style>
</head>
<body>
    <div id="content" style="padding-left: 300px;">
        <!--breadcrumbs-->
        <div id="content-header">
            <div id="breadcrumb">
                <a href="bills.php" title="Go to Home" class="tip-bottom">
                    <i class="icon-home"></i> RETURN TO PHARMACY BILLS
                </a>
            </div>
        </div>
        <!--End-breadcrumbs-->

        <center>
            <h4>Pharmacy Bill Details</h4>
        </center>
        
        <!-- Bill Header Information -->
        <div class="bill-info">
            <table>
                <tr>
                    <td>Bill No:</td>
                    <td><strong><?php echo htmlspecialchars($bill_no); ?></strong></td>
                </tr>
                <tr>
                    <td>Customer Name:</td>
                    <td><strong><?php echo htmlspecialchars($customerName); ?></strong></td>
                </tr>
                <?php if($patient_id): ?>
                <tr>
                    <td>Patient ID:</td>
                    <td><?php echo htmlspecialchars($patient_id); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Billed On:</td>
                    <td><?php echo htmlspecialchars($date); ?></td>
                </tr>
                <tr>
                    <td>Payment Method:</td>
                    <td><?php echo htmlspecialchars($bill_type); ?></td>
                </tr>
                <tr>
                    <td>Billed By:</td>
                    <td><?php echo htmlspecialchars($username); ?></td>
                </tr>
                <tr>
                    <td>Payment Status:</td>
                    <td>
                        <span class="payment-status <?php echo $payment_status == 'paid' ? 'status-paid' : 'status-pending'; ?>">
                            <?php echo ucfirst(htmlspecialchars($payment_status)); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Bill Items Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Product Company</th>
                    <th>Product Name</th>
                    <th>Product Unit</th>
                    <th>Packing Size</th>
                    <th>Price (Kshs)</th>
                    <th>Quantity</th>
                    <th>Total (Kshs)</th>
                    <th>Return</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Query pharmacy_billing_details instead of billing_details
                $res=mysqli_query($link, "select * from pharmacy_billing_details where billing_id = $id");
                if($res && mysqli_num_rows($res) > 0) {
                    while($row=mysqli_fetch_array($res))
                    {
                        // Use total_amount if available, otherwise calculate
                        if (isset($row['total_amount']) && $row['total_amount'] > 0) {
                            $itemTotal = $row['total_amount'];
                        } else {
                            $itemTotal = $row["price"] * $row["quantity"];
                        }
                        
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row["product_company"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["product_name"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["product_unit"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["packing_size"]) . "</td>";
                        echo "<td>" . number_format($row["price"], 2) . "</td>";
                        echo "<td>" . intval($row["quantity"]) . "</td>";
                        echo "<td><strong>" . number_format($itemTotal, 2) . "</strong></td>";
                        echo "<td><a href='return_products.php?id=" . intval($row["id"]) . "' class='return-link'>RETURN</a></td>";
                        echo "</tr>";
                        $total = $total + $itemTotal;
                    }
                } else {
                    echo "<tr><td colspan='8' style='text-align: center;'>No items found for this pharmacy bill.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Grand Total -->
        <div class="clearfix">
            <div class="grand-total">
                <?php 
                // Use stored total_amount if available and no items were calculated
                if ($total_amount !== null && $total == 0) {
                    echo "Grand Total: Kshs. " . number_format($total_amount, 2);
                } else {
                    echo "Grand Total: Kshs. " . number_format($total, 2);
                }
                ?>
            </div>
        </div>

        <!-- Print Button -->
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Print Bill
            </button>
        </div>
    </div>

    <?php 
    include "includes/footer.php";
    ?>
</body>
</html>