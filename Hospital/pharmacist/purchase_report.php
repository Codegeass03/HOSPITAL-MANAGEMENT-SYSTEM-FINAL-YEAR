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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Master - Purchase History</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #5a6fde;
            font-size: 14px;
            white-space: nowrap;
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
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,.05);
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
        .quantity-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .price-amount {
            font-weight: 600;
            color: #28a745;
        }
        .expiry-warning {
            background: #ffc107;
            color: #212529;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .expiry-danger {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .purchase-method {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
            background: #e9ecef;
            color: #495057;
        }
        .widget-content {
            overflow-x: auto;
        }
        @media (max-width: 1200px) {
            .table {
                font-size: 12px;
            }
            .table th,
            .table td {
                padding: 8px 10px;
            }
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
            .widget-content {
                overflow-x: scroll;
            }
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .summary-title {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div id="content" style="padding-left: 300px;">
        <!--breadcrumbs-->
        <!-- <div id="content-header">
            <div id="breadcrumb">
                <a href="#" class="tip-bottom">
                    <i class="icon-home"></i> Stock Master
                </a>
            </div>
        </div> -->
        <!--End-breadcrumbs-->

        <!--Action boxes-->
        <div class="container-fluid">
            <div class="row-fluid">
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
                            Show Purchase History
                        </button>
                        <button type="button" name="submit2" class="btn btn-warning" 
                                onclick="window.location.href=window.location.href">
                            Clear Search
                        </button>
                    </div>
                </form>

                <br>

                <!-- Purchase Summary -->
                <?php
                if(isset($_POST["submit1"])) {
                    $start_date = mysqli_real_escape_string($link, $_POST['dt']);
                    $end_date = mysqli_real_escape_string($link, $_POST['dt2']);
                    $total_query = mysqli_query($link, "SELECT COUNT(*) as total_count, SUM(quantity) as total_quantity, SUM(price * quantity) as total_value FROM purchase_master WHERE (purchase_date >= '$start_date' AND purchase_date <= '$end_date')");
                    $summary = mysqli_fetch_assoc($total_query);
                } else {
                    $total_query = mysqli_query($link, "SELECT COUNT(*) as total_count, SUM(quantity) as total_quantity, SUM(price * quantity) as total_value FROM purchase_master");
                    $summary = mysqli_fetch_assoc($total_query);
                }
                ?>

                <div class="summary-card">
                    <div class="summary-title">Purchase Summary</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Total Purchases</div>
                            <div class="summary-value"><?php echo $summary['total_count'] ?? 0; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Total Quantity</div>
                            <div class="summary-value"><?php echo $summary['total_quantity'] ?? 0; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Total Value</div>
                            <div class="summary-value">Ksh <?php echo number_format($summary['total_value'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Purchase History Table -->
                <div class="widget-content nopadding">
                    <?php  
                    if (isset($_POST["submit1"]))
                    {
                        $start_date = mysqli_real_escape_string($link, $_POST['dt']);
                        $end_date = mysqli_real_escape_string($link, $_POST['dt2']);
                        ?> 
                        <table class="table table-bordered table-striped">
                            <thead>    
                                <tr>
                                    <th>Sr No</th>
                                    <th>Purchase By</th>
                                    <th>Product Company</th>
                                    <th>Product Name</th>
                                    <th>Product Unit</th>
                                    <th>Product Size</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Party Name</th>
                                    <th>Purchase Method</th>
                                    <th>Expiry Date</th>
                                    <th>Purchase Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = mysqli_query($link, "SELECT * FROM purchase_master WHERE (purchase_date >= '$start_date' AND purchase_date <= '$end_date') ORDER BY purchase_date DESC");
                                $count = 0;
                                $hasRecords = false;
                                
                                while($row = mysqli_fetch_array($res))
                                {
                                    $count++;
                                    $hasRecords = true;
                                    $expiry_date = $row["expiry_date"];
                                    $current_date = date('Y-m-d');
                                    $expiry_class = '';
                                    
                                    if ($expiry_date < $current_date) {
                                        $expiry_class = 'expiry-danger';
                                    } elseif (date('Y-m-d', strtotime('+30 days')) >= $expiry_date) {
                                        $expiry_class = 'expiry-warning';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>" . $count . "</td>";
                                    echo "<td><strong>" . $row["username"] . "</strong></td>";
                                    echo "<td>" . $row["company_name"] . "</td>";
                                    echo "<td><strong>" . $row["product_name"] . "</strong></td>";
                                    echo "<td>" . $row["unit"] . "</td>";
                                    echo "<td>" . $row["packing_size"] . "</td>";
                                    echo "<td><span class='quantity-badge'>" . $row["quantity"] . "</span></td>";
                                    echo "<td class='price-amount'>Ksh " . number_format($row["price"], 2) . "</td>";
                                    echo "<td>" . $row["party_name"] . "</td>";
                                    echo "<td><span class='purchase-method'>" . $row["purchase_method"] . "</span></td>";
                                    echo "<td><span class='" . $expiry_class . "'>" . $row["expiry_date"] . "</span></td>";
                                    echo "<td>" . $row["purchase_date"] . "</td>";
                                    echo "</tr>";
                                }
                                
                                if (!$hasRecords) {
                                    echo "<tr><td colspan='12' style='text-align: center; padding: 20px;'>No purchase records found for the selected date range.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php
                    } else {
                        ?> 
                        <table class="table table-bordered table-striped">
                            <thead>    
                                <tr>
                                    <th>Sr No</th>
                                    <th>Purchase By</th>
                                    <th>Product Company</th>
                                    <th>Product Name</th>
                                    <th>Product Unit</th>
                                    <th>Product Size</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Party Name</th>
                                    <th>Purchase Method</th>
                                    <th>Expiry Date</th>
                                    <th>Purchase Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = mysqli_query($link, "SELECT * FROM purchase_master ORDER BY purchase_date DESC");
                                $count = 0;
                                $hasRecords = false;
                                
                                while($row = mysqli_fetch_array($res))
                                {
                                    $count++;
                                    $hasRecords = true;
                                    $expiry_date = $row["expiry_date"];
                                    $current_date = date('Y-m-d');
                                    $expiry_class = '';
                                    
                                    if ($expiry_date < $current_date) {
                                        $expiry_class = 'expiry-danger';
                                    } elseif (date('Y-m-d', strtotime('+30 days')) >= $expiry_date) {
                                        $expiry_class = 'expiry-warning';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>" . $count . "</td>";
                                    echo "<td><strong>" . $row["username"] . "</strong></td>";
                                    echo "<td>" . $row["company_name"] . "</td>";
                                    echo "<td><strong>" . $row["product_name"] . "</strong></td>";
                                    echo "<td>" . $row["unit"] . "</td>";
                                    echo "<td>" . $row["packing_size"] . "</td>";
                                    echo "<td><span class='quantity-badge'>" . $row["quantity"] . "</span></td>";
                                    echo "<td class='price-amount'>Ksh " . number_format($row["price"], 2) . "</td>";
                                    echo "<td>" . $row["party_name"] . "</td>";
                                    echo "<td><span class='purchase-method'>" . $row["purchase_method"] . "</span></td>";
                                    echo "<td><span class='" . $expiry_class . "'>" . $row["expiry_date"] . "</span></td>";
                                    echo "<td>" . $row["purchase_date"] . "</td>";
                                    echo "</tr>";
                                }
                                
                                if (!$hasRecords) {
                                    echo "<tr><td colspan='12' style='text-align: center; padding: 20px;'>No purchase records found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php
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