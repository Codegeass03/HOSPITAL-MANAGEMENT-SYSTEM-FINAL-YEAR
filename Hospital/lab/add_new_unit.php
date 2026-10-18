<?php
session_start();
if(!isset($_SESSION["admin"])) {
?>
<!-- <script type="text/javascript">
  window.location="index.php";
</script> -->
<?php
}

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb">
            <a href="index.html" title="Go to Home" class="tip-bottom">
                <i class="icon-home"></i> ADD NEW UNIT
            </a>
        </div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid" style="display: flex; justify-content: center; align-items: flex-start; padding: 20px; background-color: #f5f7fa; min-height: 100vh;">
        <div class="span10" style="width: 90%; max-width: 1000px;">
            
            <!-- Form Section -->
            <div class="widget-box" style="border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;">
                    <span class="icon" style="color: white;"> <i class="icon-align-justify"></i> </span>
                    <a href="inventorylist.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
           <button>INVENTORY MANAGEMENT</button>
        </a>
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

                        <div class="alert alert-danger" id="error" style="display: none; text-align: center;">
                            OOPS! Unit already exists, please try another!
                        </div>

                        <div class="alert alert-success" id="success" style="display: none; text-align: center;">
                            ✅ Record saved successfully!
                        </div>

                        <div class="form-actions" style="text-align: center;">
                            <button type="submit" name="submit1" class="btn btn-success"
                                    style="background-color: #43a047; border: none; padding: 10px 20px; border-radius: 5px;">
                                <i class="icon-save"></i> Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Section -->
            <div class="widget-box" style="margin-top: 30px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;">
                    <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">AVAILABLE UNITS</h5>
                </div>

                 <div class="widget-content nopadding" style="padding: 20px; display: flex; justify-content: center;">
                    <table class="table table-bordered table-striped" style="width: 90%; max-width: 900px; border: 1px solid #e3f2fd; table-layout: fixed;">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); text-align: center;">
                                <th style="color: #1565c0; width: 10%;">ID</th>
                                <th style="color: #1565c0; width: 50%;">Unit Name</th>
                                <th style="color: #1565c0; width: 20%;">Edit</th>
                                <th style="color: #1565c0; width: 20%;">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($link, "select * from unit");
                            while($row=mysqli_fetch_array($res)) {
                            ?> 
                                <tr style="text-align: center; background-color: white;">
                                    <td><?php echo $row["id"]; ?></td>
                                    <td><?php echo $row["unit"]; ?></td>
                                    <td>
                                        <a href="edit_unit.php?id=<?php echo $row["id"]; ?>" class="btn btn-mini btn-info" style="padding: 4px 8px;">
                                            <i class="icon-edit"></i> Edit
                                        </a>
                                    </td>
                                    <td>
                                        <a href="delete_unit.php?id=<?php echo $row["id"]; ?>" class="btn btn-mini btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this unit?');"
                                           style="padding: 4px 8px;">
                                            <i class="icon-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
if(isset($_POST["submit1"])) {
  $count=0;
  $res=mysqli_query($link, "select * from unit where unit = '$_POST[unitname]'");
  $count=mysqli_num_rows($res);

  if($count>0) {
    ?>
    <script type="text/javascript">
      document.getElementById("success").style.display="none";
      document.getElementById("error").style.display="block";
    </script>
    <?php
  } else {
    mysqli_query($link, "insert into unit values(NULL,'$_POST[unitname]')") or die(mysqli_error($link));
    ?>
    <script type="text/javascript">
      document.getElementById("error").style.display="none";
      document.getElementById("success").style.display="block";
      setTimeout(function(){
        window.location.href = window.location.href;
      },1000);
    </script>
    <?php
  }
}
?>

<?php
include "includes/footer.php";
?>
