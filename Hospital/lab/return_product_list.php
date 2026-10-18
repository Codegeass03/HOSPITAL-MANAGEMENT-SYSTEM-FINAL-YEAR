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
<DOCTYPE html>
<head>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
</html>
<?php  
include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

// Switch to hospital database
mysqli_select_db($link, "hospital");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returned Pharmacy Products</title>
    <style>
        .container-fluid {
            padding: 20px;
        }
        .form-inline {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .form-group {
            margin-right: 15px;
            margin-bottom: 10px;
        }
        .form-control {
            border: 1px solid #666666;
            border-radius: 4px;
            padding: 8px 12px;
            height: 38px;
            width: 200px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
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
        .table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        .table th {
            background: #dc3545;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #c82333;
            font-size: 14px;
        }
        .table td {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            vertical-align: top;
            font-size: 14px;
        }
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .table tr:hover {
            background: #e9ecef;
        }
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            color: #495057;
        }
        .row-fluid {
            background: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            min-height: 1000px;
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
        .tip-bottom {
            font-size: 14px;
        }
        .total-amount {
            font-weight: 600;
            color: #dc3545;
        }
        .return-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .form-inline {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .database-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .customer-info {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 5px 0;
            font-size: 13px;
        }
        @media (max-width: 768px) {
            .form-inline {
                flex-direction: column;
                align-items: stretch;
            }
            .form-group {
                width: 100%;
            }
            .form-control {
                width: 100% !important;
            }
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            .table {
                font-size: 12px;
            }
            .table th,
            .table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div id="content" style="padding-left: 300px;">
        <!--breadcrumbs-->
        <div id="content-header" style="color: red; background-color: white; font-size: 32px; font-weight: bold;">
            <div id="breadcrumb">
                <a href="pharmacy.php" title="Go to Pharmacy" class="tip-bottom">
                    <i class="icon-home"></i> RETURNED PHARMACY PRODUCTS
                </a>
            </div>
        </div>
        <!--End-breadcrumbs-->

      

                <!-- Date Filter Form -->
                <form class="form-inline" action="" name="form1" method="post">
                    <div class="form-group">
                        <label for="dt">Select Start Date</label>
                        <input type="date" name="dt" id="dt" autocomplete="off" class="form-control" required 
                               placeholder="Select start date">
                    </div>
                    <div class="form-group">
                        <label for="dt2">Select End Date</label>
                        <input type="date" name="dt2" id="dt2" autocomplete="off" class="form-control" 
                               placeholder="Select end date">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit1" class="btn btn-success">
                            Show Returned Products
                        </button>
                        <button type="button" name="submit2" class="btn btn-warning" 
                                onclick="window.location.href=window.location.href">
                            Clear Search
                        </button>
                    </div>
                </form>

                <br>

                <!-- Returned Products Table -->
                <div class="table-responsive">
                    <?php
                    // Check if return_products table exists in hospital database
                    $table_check = mysqli_query($link, "SHOW TABLES LIKE 'return_products'");
                    $table_exists = mysqli_num_rows($table_check) > 0;

                    if(!$table_exists) {
                        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; text-align: center; margin: 20px 0;'>";
                        echo "<h4><i class='fa fa-exclamation-triangle'></i> Return Products Table Not Found</h4>";
                        echo "<p>The return_products table does not exist in the hospital database.</p>";
                        echo "<p>Please run the database update script to create the required table.</p>";
                        echo "<a href='update_pharmacy_db.php' style='color: #721c24; text-decoration: underline;'>Click here to update database</a>";
                        echo "</div>";
                    } else {
                        if(isset($_POST["submit1"]))
                        {
                            $start_date = mysqli_real_escape_string($link, $_POST['dt']);
                            $end_date = mysqli_real_escape_string($link, $_POST['dt2'] . ' 23:59:59');
                            ?>  
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Pharmacy Bill No</th>
                                        <th>Customer Name</th>
                                        <th>Return Date</th>
                                        <th>Product Company</th>
                                        <th>Product Name</th>
                                        <th>Packing Size</th>
                                        <th>Product Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($link, "SELECT rp.*, pb.CustomerName 
                                                               FROM return_products rp 
                                                               LEFT JOIN pharmacy_billing pb ON rp.bill_no = pb.bill_no 
                                                               WHERE (rp.return_date >= '$start_date' AND rp.return_date <= '$end_date') 
                                                               ORDER BY rp.id DESC");
                                    $totalReturns = 0;
                                    $hasReturns = false;
                                    
                                    if($res && mysqli_num_rows($res) > 0) {
                                        while ($row = mysqli_fetch_array($res)) {
                                            $hasReturns = true;
                                            $totalReturns += $row["total"];
                                            echo "<tr>";
                                            echo "<td><strong>" . htmlspecialchars($row["bill_no"]) . "</strong></td>";
                                            echo "<td class='customer-info'>" . htmlspecialchars($row["CustomerName"] ?? 'N/A') . "</td>";
                                            echo "<td>" . htmlspecialchars($row["return_date"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["product_company"]) . "</td>";
                                            echo "<td><strong>" . htmlspecialchars($row["product_name"]) . "</strong></td>";
                                            echo "<td>" . htmlspecialchars($row["packing_size"]) . "</td>";
                                            echo "<td>Ksh " . number_format($row["price"], 2) . "</td>";
                                            echo "<td><span class='return-badge'>" . $row["quantity"] . "</span></td>";
                                            echo "<td class='total-amount'>Ksh " . number_format($row["total"], 2) . "</td>";
                                            echo "</tr>";
                                        }
                                    }
                                    
                                    if (!$hasReturns) {
                                        echo "<tr><td colspan='9' style='text-align: center; padding: 20px;'>No returned pharmacy products found for the selected date range.</td></tr>";
                                    } else {
                                        echo "<tr style='background: #f8d7da;'>";
                                        echo "<td colspan='8' style='text-align: right; font-weight: 600;'>Total Returns:</td>";
                                        echo "<td class='total-amount' style='font-size: 16px;'>Ksh " . number_format($totalReturns, 2) . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <?php
                        }
                        else 
                        {
                            ?>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Pharmacy Bill No</th>
                                        <th>Customer Name</th>
                                        <th>Return Date</th>
                                        <th>Product Company</th>
                                        <th>Product Name</th>
                                        <th>Packing Size</th>
                                        <th>Product Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($link, "SELECT rp.*, pb.CustomerName 
                                                               FROM return_products rp 
                                                               LEFT JOIN pharmacy_billing pb ON rp.bill_no = pb.bill_no 
                                                               ORDER BY rp.id DESC");
                                    $totalReturns = 0;
                                    $hasReturns = false;
                                    
                                    if($res && mysqli_num_rows($res) > 0) {
                                        while ($row = mysqli_fetch_array($res)) {
                                            $hasReturns = true;
                                            $totalReturns += $row["total"];
                                            echo "<tr>";
                                            echo "<td><strong>" . htmlspecialchars($row["bill_no"]) . "</strong></td>";
                                            echo "<td class='customer-info'>" . htmlspecialchars($row["CustomerName"] ?? 'N/A') . "</td>";
                                            echo "<td>" . htmlspecialchars($row["return_date"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["product_company"]) . "</td>";
                                            echo "<td><strong>" . htmlspecialchars($row["product_name"]) . "</strong></td>";
                                            echo "<td>" . htmlspecialchars($row["packing_size"]) . "</td>";
                                            echo "<td>Ksh " . number_format($row["price"], 2) . "</td>";
                                            echo "<td><span class='return-badge'>" . $row["quantity"] . "</span></td>";
                                            echo "<td class='total-amount'>Ksh " . number_format($row["total"], 2) . "</td>";
                                            echo "</tr>";
                                        }
                                    }
                                    
                                    if (!$hasReturns) {
                                        echo "<tr><td colspan='9' style='text-align: center; padding: 20px;'>No returned pharmacy products found in the system.</td></tr>";
                                    } else {
                                        echo "<tr style='background: #f8d7da;'>";
                                        echo "<td colspan='8' style='text-align: right; font-weight: 600;'>Total Returns:</td>";
                                        echo "<td class='total-amount' style='font-size: 16px;'>Ksh " . number_format($totalReturns, 2) . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <?php 
    include "includes/footer.php";
    ?>
</body>
</html>