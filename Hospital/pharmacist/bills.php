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
    <title>View Bills</title>
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
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin-top: 20px;
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
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        a:hover {
            text-decoration: underline;
            color: #0056b3;
        }
        label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
        .row-fluid {
            background: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
    </style>
</head>
<body>
    <!--main-container-part-->
    <div id="content" style="padding-left: 350px;">
        <!--breadcrumbs-->
        <div id="content-header">
            <div id="breadcrumb">
                <a href="pharmacy.php" title="Go to Home" class="tip-bottom">
                    <i class="icon-home"></i> RETURN TO PHARMACY
                </a>
            </div>
        </div>
        <!--End-breadcrumbs-->

        <!--Action boxes-->
        <div class="container-fluid">
            <div class="row-fluid">
                <!-- Date Filter Form -->
                <form class="form-inline" action="" name="form1" method="post">
                    <div class="form-group">
                        <label for="dt">Select Start Date</label>
                        <input type="date" name="dt" id="dt" autocomplete="off" class="form-control" required 
                               style="width:200px;" placeholder="Click to open calendar">
                    </div>
                    <div class="form-group">
                        <label for="dt2">Select End Date</label>
                        <input type="date" name="dt2" id="dt2" autocomplete="off" class="form-control" 
                               style="width:200px;" placeholder="Click to open calendar">
                    </div>
                    <div class="form-group" style="align-self: flex-end;">
                        <button type="submit" name="submit1" class="btn btn-success">
                            Show Bills From These Dates
                        </button>
                        <button type="button" name="submit2" class="btn btn-warning" 
                                onclick="window.location.href=window.location.href">
                            Clear Search
                        </button>
                    </div>
                </form>

                <br>

                <!-- Bills Table -->
                <div class="table-responsive">
                    <?php 
                    if(isset($_POST["submit1"]))
                    {
                        ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Bill No</th>
                                    <th>Bill Generated By</th>
                                    <th>Customer's Name</th>
                                    <th>Bill Type</th>
                                    <th>Bill Date</th>
                                    <th>Bill Total</th>
                                    <th>View Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $start_date = mysqli_real_escape_string($link, $_POST['dt']);
                            $end_date = mysqli_real_escape_string($link, $_POST['dt2'] . ' 23:59:59'); 
                            
                            $sql = "SELECT * FROM billing WHERE date BETWEEN '$start_date' AND '$end_date' ORDER BY id ASC";
                            $res = mysqli_query($link, $sql);
                            
                            if(mysqli_num_rows($res) > 0) {
                                while($row = mysqli_fetch_array($res))
                                {
                                    echo "<tr>";
                                    echo "<td>" . $row["bill_no"] . "</td>";
                                    echo "<td>" . $row["username"] . "</td>";
                                    echo "<td>" . $row["CustomerName"] . "</td>";
                                    echo "<td>" . $row["bill_type"] . "</td>";
                                    echo "<td>" . $row["date"] . "</td>";
                                    echo "<td><strong>" . get_total($row["id"], $link) . " Kshs</strong></td>";
                                    echo "<td><a href='view_bill_details.php?id=" . $row["id"] . "' class='view-details'>View Details</a></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align: center;'>No bills found for the selected date range.</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table> 
                        <?php
                    } 
                    else {
                        ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Bill No</th>
                                    <th>Bill Generated By</th>
                                    <th>Customer's Name</th>
                                    <th>Bill Type</th>
                                    <th>Bill Date</th>
                                    <th>Bill Total</th>
                                    <th>View Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $res = mysqli_query($link, "SELECT * FROM billing ORDER BY id ASC");
                            while($row = mysqli_fetch_array($res))
                            {
                                echo "<tr>";
                                echo "<td>" . $row["bill_no"] . "</td>";
                                echo "<td>" . $row["username"] . "</td>";
                                echo "<td>" . $row["CustomerName"] . "</td>";
                                echo "<td>" . $row["bill_type"] . "</td>";
                                echo "<td>" . $row["date"] . "</td>";
                                echo "<td><strong>" . get_total($row["id"], $link) . " Kshs</strong></td>";
                                echo "<td><a href='view_bill_details.php?id=" . $row["id"] . "' class='view-details'>View Details</a></td>";
                                echo "</tr>";
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
    <!--end-main-container-part-->

    <?php
    function get_total($billing_id, $link)
    {
        $total = 0;
        $res2 = mysqli_query($link, "SELECT * FROM billing_details WHERE billing_id = $billing_id");
        while($row = mysqli_fetch_array($res2))
        {
            $total = $total + ($row["price"] * $row["quantity"]);
        }
        return number_format($total, 2);
    }
    ?>

    <?php
    include "includes/footer.php";
    ?>
</body>
</html>