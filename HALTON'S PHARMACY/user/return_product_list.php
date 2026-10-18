<?php
session_start();
if(!isset($_SESSION["user"]))
{
?>
<script type="text/javascript">
  window.location="index.php";
</script>
<?php
}
?>
<?php  
include "header.php";
include "../user/connection.php";
?>

<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
           RETURN PRODUCT LIST</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
        <form class="form-inline" action="" name="form1" method="post">
                    <div class="form-group">
                        <label for="email">Select Start Date</label>
                        <input type="date" name="dt" id="dt" autocomplete="off" class="form-control" required style="width:200px;border-style:solid; border-width:1px; border-color:#666666" placeholder="click here to open calender"  >
                    </div>
                    <div class="form-group">
                        <label for="email">Select End Date</label>
                        <input type="date" name="dt2" id="dt2" autocomplete="off" placeholder="click here to open calender"  class="form-control" style="width:200px;border-style:solid; border-width:1px; border-color:#666666" >
                    </div>
                    <button type="submit" name="submit1" class="btn btn-success">Show Returned Products From These Dates</button>
                    <button type="button" name="submit2" class="btn btn-warning" onclick="window.location.href=window.location.href">Clear Search</button>
                </form>

                <br>
                <?php
                if(isset($_POST["submit1"]))
                {
?>  
<table class="table table-bordered">
                     <tr>
                         <th>Bill No</th>
                         <th>Return Date</th>
                         <th>Product Company</th>
                         <th>Product Name</th>
                         <th>Packing size</th>
                         <th>Product Price</th>
                         <th>Product Quantity</th>
                         <th>Total</th>
                     </tr>
                     <?php 
                     $res=mysqli_query($link, "select * from return_products where (return_date>='$_POST[dt]' &&  return_date<='$_POST[dt2]')order by id asc");
                     while ($row = mysqli_fetch_array($res)) {
                         echo "<tr>";
                         echo "<td>"; echo $row["bill_no"]; "</td>";
                         echo "<td>"; echo $row["return_date"]; "</td>";
                         echo "<td>"; echo $row["product_company"]; "</td>";
                         echo "<td>"; echo $row["product_name"]; "</td>";
                         echo "<td>"; echo $row["packing_size"]; "</td>";
                         echo "<td>"; echo $row["product_price"]; "</td>";
                         echo "<td>"; echo $row["product_quantity"]; "</td>";
                         echo "<td>"; echo $row["total"]; "</td>";
                         echo "</tr>";
                     }
                     ?>
                    </table>
<?php
                }
                else 
                {
                    ?>
                    <table class="table table-bordered">
                     <tr>
                         <th>Bill No</th>
                         <th>Return Date</th>
                         <th>Product Company</th>
                         <th>Product Name</th>
                         <th>Packing size</th>
                         <th>Product Price</th>
                         <th>Product Quantity</th>
                         <th>Total</th>
                     </tr>
                     <?php 
                     $res=mysqli_query($link, "select * from return_products order by id asc");
                     while ($row = mysqli_fetch_array($res)) {
                         echo "<tr>";
                         echo "<td>"; echo $row["bill_no"]; "</td>";
                         echo "<td>"; echo $row["return_date"]; "</td>";
                         echo "<td>"; echo $row["product_company"]; "</td>";
                         echo "<td>"; echo $row["product_name"]; "</td>";
                         echo "<td>"; echo $row["packing_size"]; "</td>";
                         echo "<td>"; echo $row["product_price"]; "</td>";
                         echo "<td>"; echo $row["product_quantity"]; "</td>";
                         echo "<td>"; echo $row["total"]; "</td>";
                         echo "</tr>";
                     }
                     ?>
                    </table>
                    <?php
                }
               ?>
        </div>
        </div>
</div>

<?php 
include "footer.php";
?>