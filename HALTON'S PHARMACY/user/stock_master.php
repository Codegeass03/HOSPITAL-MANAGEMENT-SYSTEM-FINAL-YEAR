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
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Sr No</th>
                  <th>product company</th>
                  <th>product name</th>
                  <th>product unit</th>
                  <th>packing size</th>
                  <th>product quantity</th>
                  <th>product selling price</th>
               
                </tr>
              </thead>
              <tbody>

              <?php
              $count=0;
              $res = mysqli_query($link, "select * from stock_master");
              while($row=mysqli_fetch_array($res))
              {
              $count=$count+1;
             
                ?> 
                <tr>
                  <td><?php echo $count; ?></td>
                  <td><?php echo $row ["product_company"]; ?></td>
                  <td><?php echo $row ["product_name"]; ?></td>
                  <td><?php echo $row ["product_unit"]; ?></td>
                  <td><?php echo $row ["packing_size"]; ?></td>
                  <td><?php echo $row ["product_quantity"]; ?></td>
                  <td><?php echo $row ["product_selling_price"]; ?></td>
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
include "footer.php";
?>