<?php
session_start();
if(!isset($_SESSION["admin"]))
{
?>
<script type="text/javascript">
  window.location="index.php";
</script>
<?php
}
?>
<?php
    include "admin/connection.php";
    include "includes/db_connect.php";
include "includes/sidebar.php";
?>
<div class="modal-header">
      <h2 class="modal-title">Add New Stock</h2>
      <button class="modal-close" onclick="closeAddStaffModal()">&times;</button>
    </div>
<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            STOCK MASTER</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
        <div class="span12">


      <div class="widget-content nopadding">
    <div class="table-responsive" style="display: flex; justify-content: center;">
        <table class="table table-bordered table-striped" style="text-align: center; margin: 0 auto;">
            <thead>
                <tr style="background-color: #37474F; color: white;">
                    <th>Sr No</th>
                    <th>Product Company</th>
                    <th>Product Name</th>
                    <th>Product Unit</th>
                    <th>Packing Size</th>
                    <th>Product Quantity</th>
                    <th>Product Selling Price</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 0;
                $res = mysqli_query($link, "select * from stock_master");
                while($row = mysqli_fetch_array($res)) {
                    $count = $count + 1;
                ?>
                <tr>
                    <td><?php echo $count; ?></td>
                    <td><?php echo $row["product_company"]; ?></td>
                    <td><?php echo $row["product_name"]; ?></td>
                    <td><?php echo $row["product_unit"]; ?></td>
                    <td><?php echo $row["packing_size"]; ?></td>
                    <td><?php echo $row["product_quantity"]; ?></td>
                    <td><?php echo $row["product_selling_price"]; ?></td>
                </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

    </div>
</div>


<?php
include "includes/footer.php";
?>
