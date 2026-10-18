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

    $id=$_GET["id"];
    $unit = "";
  

    $res = mysqli_query($link, "select * from unit where id = $id");
    while($row=mysqli_fetch_array($res))
    {
        $unit = $row["unit"];
    }
?>

<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            EDIT UNITS</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid" style="display: flex; justify-content: center; align-items: flex-start; padding: 20px; background-color: #f5f7fa; min-height: 100vh;">
        <div class="span10" style="width: 90%; max-width: 1000px;">
            
            <!-- Form Section -->
            <div class="widget-box" style="border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;">
                    <span class="icon" style="color: white;"> <i class="icon-align-justify"></i> </span>
                    <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">ADD NEW UNIT</h5>
                </div>

                <div class="widget-content nopadding" style="padding: 30px; background-color: white;">
                    <form name="form1" action="" method="post" class="form-horizontal" style="max-width: 500px; margin: 0 auto;">
                        <div class="control-group" style="margin-bottom: 20px;">
                            <label class="control-label" style="font-weight: bold; color: #1565c0;">Unit Name :</label>
                            <div class="controls">
                                <input type="text" class="span11" placeholder="Enter unit of measurement" name="unitname" required
                                       style="width: 100%; border: 1px solid #90caf9; border-radius: 6px; padding: 8px; font-size: 14px;">
                            </div>
                        </div>

               <div class="form-actions" style="text-align: center;">
              <button type="submit" name="submit1" class="btn btn-success"
              tyle="background-color: #43a047; border: none; padding: 10px 20px; border-radius: 5px;">
                                <i class="icon-save"></i> Update
                      </button>
            </div>

            <div class="alert alert-success" id="success" style="display: none; text-align: center;">
                            ✅ Record updated successfully!
                        </div>

          </form>
        </div>

      </div>
        </div>

    </div>
</div>

<?php
if (isset($_POST["submit1"]))
{
mysqli_query($link, "update unit set unit='$_POST[unitname]' where id =$id") or die(mysqli_error($link));

?>
<script type="text/javascript">
  document.getElementById("success").style.display="block";
  setTimeout(function(){
    window.location.href ="add_new_unit.php";
  }, 2000);
</script>
<?php

}
?>

<!--end-main-container-part-->

<?php
include "footer.php";
?>