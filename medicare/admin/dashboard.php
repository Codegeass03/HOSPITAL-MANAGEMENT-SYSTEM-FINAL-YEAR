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
include "header.php";
include "../user/connection.php";
?>

<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="dashboard.php" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
          DASHBOARD</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
         <div class="card" style="width: 20rem; border-style: solid; border-width: 1x; border-radius: 10px; float: left">
            <div class="card-body">
                <h3 class="card-title text-center">NO OF PRODUCTS</h3>
                <h1 class="card-title text-center">
                    <?php
                        $count=0;
                        $res=mysqli_query($link, "select * from products");
                        $count=mysqli_num_rows($res);
                        echo $count;
                    ?>
                </h1>
            </div>
</div>
            <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
         <div class="card" style="width: 20rem; border-style: solid; border-width: 1x; border-radius: 10px; float: left; margin-left: 25px">
            <div class="card-body">
                <h3 class="card-title text-center">TOTAL ORDERS</h3>
                <h1 class="card-title text-center">
                    <?php
                        $count=0;
                        $res=mysqli_query($link, "select * from billing_header");
                        $count=mysqli_num_rows($res);
                        echo $count;
                    ?>
                </h1>
            </div>
         </div>

         <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
         <div class="card" style="width: 20rem; border-style: solid; border-width: 1x; border-radius: 10px; float: left; margin-left: 25px">
            <div class="card-body">
                <h3 class="card-title text-center">TOTAL COMPANIES</h3>
                <h1 class="card-title text-center">
                    <?php
                        $count=0;
                        $res=mysqli_query($link, "select * from company_name");
                        $count=mysqli_num_rows($res);
                        echo $count;
                    ?>
                </h1>
            </div>
         </div>

        </div>
    </div>
</div>

<?php 
include "footer.php";
?>