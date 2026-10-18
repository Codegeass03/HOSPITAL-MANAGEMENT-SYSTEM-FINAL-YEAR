<?php
session_start();
// if(!isset($_SESSION["admin"])) {
//     header("Location: index.php");
//     exit();
// }

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<?php
    $id=$_GET["id"];
    $firstname= "";
    $lastname="";
    $businessname="";
    $contact="";
    $address="";
    $city="";

    $res=mysqli_query($link,"select * from part_info where id=$id");
    while($row=mysqli_fetch_array($res))
    {
      $firstname=$row["firstname"];
      $lastname=$row["lastname"];
      $businessname=$row["businessname"];
      $contact=$row["contact"];
      $address=$row["address"];
      $city=$row["city"];
    }
?>

<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            EDIT PARTY INFO</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
            <div class="span12" style="display: flex; justify-content: center;">
                <div class="widget-box" style="width: 80%; max-width: 1200px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                    <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;"> 
                        <span class="icon" style="color: white;"> <i class="icon-align-justify"></i> </span>
                        <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">EDIT SUPPLIER INFORMATION</h5>
                    </div>
                    <div class="widget-content nopadding" style="background-color: #e3f2fd; padding: 20px;">
                        <form name="form1" action="" method="post" class="form-horizontal">
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">First Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" class="span11" placeholder="First name" name="firstname" value="<?php echo $firstname; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;"/>
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Last Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" class="span11" placeholder="Last name" name="lastname" value="<?php echo $lastname; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;"/>
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Business Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" class="span11" placeholder="Business name" name="businessname" value="<?php echo $businessname; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;"/>
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Contact</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" class="span11" placeholder="Enter contact Number" name="contact" value="<?php echo $contact; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;"/>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Address</label>
                                <div class="controls" style="width: 300px;">
                                    <textarea class="span11" name="address" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white; min-height: 80px;"><?php echo $address; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 20px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">City</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" class="span11" placeholder="Enter city" name="city" value="<?php echo $city; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;"/>
                                </div>
                            </div>

                            <div class="form-actions" style="text-align: center; background-color: #bbdefb; padding: 15px; border-radius: 5px; border: 1px solid #90caf9;">
                                <button type="submit" name="submit1" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;">
                                    <i class="icon-edit"></i> Update Supplier
                                </button>
                                <a href="supplier2.php" class="btn btn-secondary" style="padding: 8px 20px; font-weight: bold; margin-left: 10px;">
                                    <i class="icon-arrow-left"></i> Back to List
                                </a>
                            </div>

                            <div class="alert alert-success" id="success" style="display: none; text-align: center; background-color: #e8f5e8; border: 1px solid #4caf50; color: #2e7d32; padding: 10px; border-radius: 5px;">
                                <i class="icon-ok"></i> Record updated successfully!
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if(isset($_POST["submit1"]))
{
    mysqli_query($link, "update part_info set firstname='$_POST[firstname]', lastname='$_POST[lastname]', businessname='$_POST[businessname]', contact='$_POST[contact]', address='$_POST[address]', city='$_POST[city]' where id=$id") or die(mysqli_error($link));
    ?>
    <script type="text/javascript">
        document.getElementById("success").style.display="block";
        setTimeout(function(){
            window.location.href = "supplier2.php";
        },1000);
    </script>
    <?php
}
?>

<?php
include "includes/footer.php";
?>