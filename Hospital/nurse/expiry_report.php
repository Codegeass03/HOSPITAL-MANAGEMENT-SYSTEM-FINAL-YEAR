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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Master - Expiry Management</title>
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
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #bd2130;
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
        .expiry-normal {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
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
        .alert-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-banner.danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
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
            color: #dc3545;
        }
        .days-remaining {
            font-size: 11px;
            font-weight: 500;
            margin-top: 2px;
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
    </style>
</head>
<body>
    <div id="content" style="padding-left: 300px;">
        <!--breadcrumbs-->
        <div id="content-header">
            <div id="breadcrumb">
                <a href="#" class="tip-bottom">
                    <i class="icon-home"></i> Expiry Management
                </a>
            </div>
        </div>
        <!--End-breadcrumbs-->

        <!--Action boxes-->
        <div class="container-fluid">
            <div class="row-fluid">
                <!-- Alert Banner -->
                <div class="alert-banner danger">
                    <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                    <!-- <div>
                        <strong>Expiry Management</strong> - Monitor product expiry dates to ensure medication safety and compliance.
                    </div> -->
                </div>

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
                        <button type="submit" name="submit1" class="btn btn-danger">
                            <i class="fas fa-filter"></i> Show Expiring Products
                        </button>
                        <button type="button" name="submit2" class="btn btn-warning" 
                                onclick="window.location.href=window.location.href">
                            <i class="fas fa-times"></i> Clear Search
                        </button>
                        <button type="button" class="btn btn-success" onclick="showAllProducts()">
                            <i class="fas fa-list"></i> Show All Products
                        </button>
                    </div>
                </form>

                <br>

                <!-- Expiry Summary -->
                <?php
                $current_date = date('Y-m-d');
                $thirty_days_later = date('Y-m-d', strtotime('+30 days'));
                
                if(isset($_POST["submit1"])) {
                    $start_date = mysqli_real_escape_string($link, $_POST['dt']);
                    $end_date = mysqli_real_escape_string($link, $_POST['dt2']);
                    $total_query = mysqli_query($link, "SELECT COUNT(*) as total_count, SUM(quantity) as total_quantity, SUM(price * quantity) as total_value FROM purchase_master WHERE (expiry_date >= '$start_date' AND expiry_date <= '$end_date')");
                    $summary = mysqli_fetch_assoc($total_query);
                } else {
                    // Count expired and expiring soon products
                    $expired_query = mysqli_query($link, "SELECT COUNT(*) as expired_count FROM purchase_master WHERE expiry_date < '$current_date'");
                    $expired = mysqli_fetch_assoc($expired_query);
                    
                    $expiring_soon_query = mysqli_query($link, "SELECT COUNT(*) as expiring_count FROM purchase_master WHERE expiry_date >= '$current_date' AND expiry_date <= '$thirty_days_later'");
                    $expiring_soon = mysqli_fetch_assoc($expiring_soon_query);
                    
                    $total_query = mysqli_query($link, "SELECT COUNT(*) as total_count, SUM(quantity) as total_quantity, SUM(price * quantity) as total_value FROM purchase_master");
                    $summary = mysqli_fetch_assoc($total_query);
                }
                ?>

                <div class="summary-card">
                    <div class="summary-title">Expiry Overview</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Total Products</div>
                            <div class="summary-value"><?php echo $summary['total_count'] ?? 0; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Expired Products</div>
                            <div class="summary-value" style="color: #dc3545;"><?php echo $expired['expired_count'] ?? 0; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Expiring Soon (30 days)</div>
                            <div class="summary-value" style="color: #ffc107;"><?php echo $expiring_soon['expiring_count'] ?? 0; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: #6c757d;">Total Value at Risk</div>
                            <div class="summary-value" style="color: #dc3545;">
                                Ksh <?php 
                                $at_risk_value = 0;
                                if(isset($expired['expired_count']) && $expired['expired_count'] > 0) {
                                    $at_risk_query = mysqli_query($link, "SELECT SUM(price * quantity) as at_risk FROM purchase_master WHERE expiry_date < '$current_date'");
                                    $at_risk = mysqli_fetch_assoc($at_risk_query);
                                    $at_risk_value = $at_risk['at_risk'] ?? 0;
                                }
                                echo number_format($at_risk_value, 2); 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
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
                                    <th>Days Remaining</th>
                                    <th>Purchase Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = mysqli_query($link, "SELECT * FROM purchase_master WHERE (expiry_date >= '$start_date' AND expiry_date <= '$end_date') ORDER BY expiry_date ASC");
                                $count = 0;
                                $hasRecords = false;
                                
                                while($row = mysqli_fetch_array($res))
                                {
                                    $count++;
                                    $hasRecords = true;
                                    $expiry_date = $row["expiry_date"];
                                    $current_date = date('Y-m-d');
                                    $days_remaining = floor((strtotime($expiry_date) - strtotime($current_date)) / (60 * 60 * 24));
                                    
                                    if ($days_remaining < 0) {
                                        $expiry_class = 'expiry-danger';
                                        $status_text = 'EXPIRED';
                                    } elseif ($days_remaining <= 30) {
                                        $expiry_class = 'expiry-warning';
                                        $status_text = 'EXPIRING SOON';
                                    } else {
                                        $expiry_class = 'expiry-normal';
                                        $status_text = 'VALID';
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
                                    echo "<td>";
                                    echo "<span class='" . $expiry_class . "'>" . $status_text . "</span>";
                                    if ($days_remaining >= 0) {
                                        echo "<div class='days-remaining'>" . $days_remaining . " days</div>";
                                    } else {
                                        echo "<div class='days-remaining'>" . abs($days_remaining) . " days ago</div>";
                                    }
                                    echo "</td>";
                                    echo "<td>" . $row["purchase_date"] . "</td>";
                                    echo "</tr>";
                                }
                                
                                if (!$hasRecords) {
                                    echo "<tr><td colspan='13' style='text-align: center; padding: 20px;'>No products found expiring in the selected date range.</td></tr>";
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
                                    <th>Days Remaining</th>
                                    <th>Purchase Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = mysqli_query($link, "SELECT * FROM purchase_master ORDER BY expiry_date ASC");
                                $count = 0;
                                $hasRecords = false;
                                
                                while($row = mysqli_fetch_array($res))
                                {
                                    $count++;
                                    $hasRecords = true;
                                    $expiry_date = $row["expiry_date"];
                                    $current_date = date('Y-m-d');
                                    $days_remaining = floor((strtotime($expiry_date) - strtotime($current_date)) / (60 * 60 * 24));
                                    
                                    if ($days_remaining < 0) {
                                        $expiry_class = 'expiry-danger';
                                        $status_text = 'EXPIRED';
                                    } elseif ($days_remaining <= 30) {
                                        $expiry_class = 'expiry-warning';
                                        $status_text = 'EXPIRING SOON';
                                    } else {
                                        $expiry_class = 'expiry-normal';
                                        $status_text = 'VALID';
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
                                    echo "<td>";
                                    echo "<span class='" . $expiry_class . "'>" . $status_text . "</span>";
                                    if ($days_remaining >= 0) {
                                        echo "<div class='days-remaining'>" . $days_remaining . " days</div>";
                                    } else {
                                        echo "<div class='days-remaining'>" . abs($days_remaining) . " days ago</div>";
                                    }
                                    echo "</td>";
                                    echo "<td>" . $row["purchase_date"] . "</td>";
                                    echo "</tr>";
                                }
                                
                                if (!$hasRecords) {
                                    echo "<tr><td colspan='13' style='text-align: center; padding: 20px;'>No products found.</td></tr>";
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

    <script>
    function showAllProducts() {
        window.location.href = window.location.href.split('?')[0];
    }
    </script>

    <?php
    include "includes/footer.php";
    ?>
</body>
</html>