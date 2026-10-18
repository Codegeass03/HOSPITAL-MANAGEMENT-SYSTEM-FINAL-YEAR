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
        <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>EDIT PARTY</h5>
        </div>
        <div class="widget-content nopadding">
          <form name= "form1" action="" method="post" class="form-horizontal">
            <div class="control-group">
              <label class="control-label">First Name :</label>
              <div class="controls">
                <input type="text" class="span11" placeholder="First name" name="firstname" value="<?php echo $firstname; ?>"/>
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Last Name :</label>
              <div class="controls">
                <input type="text" class="span11" placeholder="Last name" name="lastname" value="<?php echo $lastname; ?>"/>
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Business Name :</label>
              <div class="controls">
                <input type="text" class="span11" placeholder="Business name" name="businessname" value="<?php echo $businessname; ?>"/>
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Contact</label>
              <div class="controls">
                <input type="text"  class="span11" placeholder="Enter contact Number" name="contact"  value="<?php echo $contact;  ?>"/>
              </div>
            </div>

            <div class="control-group">
              <label class="control-label">Address</label>
              <div class="controls">
                <textarea class="span11" name="address"><?php echo $address; ?></textarea>
              </div>
            </div>
            
            <div class="control-group">
              <label class="control-label">City</label>
              <div class="controls">
                <input type="text"  class="span11" placeholder="Enter city" name="city" value="<?php echo $city; ?>"/>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="submit1" class="btn btn-success">Update</button>
            </div>

            <div class="alert alert-success" id="success" style="display: none">
       YAY! Record updated successfully!
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
    window.location.href ="add_new_party.php";
  },1000);
</script>
    <?php

  
}
?>

<?php
include "footer.php";
?>