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
$total=0;
$id=$_GET["id"];
$bill_type="";
$date="";
$bill_no="";
$username="";
$customerName="";
$res=mysqli_query($link, "select * from billing where id=$id");
while($row=mysqli_fetch_array($res))
{
    $customerName=$row["CustomerName"];
    $bill_type=$row["bill_type"];
    $date=$row["date"];
    $bill_no=$row["bill_no"];
    $username=$row["username"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Bills</title>
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
    </style>
</head>
<body>
    <div id="content"style="padding-left: 300px;">
        <!--breadcrumbs-->
        <div id="content-header">
            <div id="breadcrumb">
                <a href="bills.php" title="Go to Home" class="tip-bottom">
                    <i class="icon-home"></i> RETURN TO BILLS
                </a>
            </div>
        </div>
        <!--End-breadcrumbs-->

        <!--Action boxes-->
        <div class="container-fluid">
            <div class="row-fluid">
                <center>
                    <h4>Detailed Bill Information</h4>
                </center>
                
                <!-- Bill Header Information -->
                <div class="bill-info">
                    <table>
                        <tr>
                            <td>Bill No:</td>
                            <td><strong><?php echo $bill_no; ?></strong></td>
                        </tr>
                        <tr>
                            <td>Customer Name:</td>
                            <td><strong><?php echo $customerName; ?></strong></td>
                        </tr>
                        <tr>
                            <td>Billed On:</td>
                            <td><?php echo $date; ?></td>
                        </tr>
                        <tr>
                            <td>Bill Type:</td>
                            <td><?php echo $bill_type; ?></td>
                        </tr>
                        <tr>
                            <td>Billed By:</td>
                            <td><?php echo $username; ?></td>
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
                        $res=mysqli_query($link, "select * from billing_details where billing_id = $id");
                        while($row=mysqli_fetch_array($res))
                        {
                            $itemTotal = $row["price"] * $row["quantity"];
                            echo "<tr>";
                            echo "<td>" . $row["product_company"] . "</td>";
                            echo "<td>" . $row["product_name"] . "</td>";
                            echo "<td>" . $row["product_unit"] . "</td>";
                            echo "<td>" . $row["packing_size"] . "</td>";
                            echo "<td>" . number_format($row["price"], 2) . "</td>";
                            echo "<td>" . $row["quantity"] . "</td>";
                            echo "<td><strong>" . number_format($itemTotal, 2) . "</strong></td>";
                            echo "<td><a href='return.php?id=" . $row["id"] . "' class='return-link'>RETURN</a></td>";
                            echo "</tr>";
                            $total = $total + $itemTotal;
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Grand Total -->
                <div class="clearfix">
                    <div class="grand-total">
                        Grand Total: Kshs. <?php echo number_format($total, 2); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php 
    include "includes/footer.php";
    ?>
</body>
</html>