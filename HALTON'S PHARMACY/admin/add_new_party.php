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

<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            ADD NEW PARTY</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
        <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>ADD NEW USER</h5>
        </div>
        <div class="widget-content nopadding">
          <form name= "form1" action="" method="post" class="form-horizontal">
            <div class="control-group">
              <label class="control-label">First Name :</label>
              <div class="controls">
                <input type="text" class="span11" placeholder="First name" name="firstname" />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Last Name :</label>
              <div class="controls">
                <input type="text" class="span11" placeholder="Last name" name="lastname" />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Business Name :</label>
              <div class="controls">
                <input type="text" class="span11" placeholder="Business name" name="businessname" />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Contact</label>
              <div class="controls">
                <input type="text"  class="span11" placeholder="Enter contact Number" name="contact"  />
              </div>
            </div>

            <div class="control-group">
              <label class="control-label">Address</label>
              <div class="controls">
                <textarea class="span11" name="address"></textarea>
              </div>
            </div>
            
            <div class="control-group">
              <label class="control-label">City</label>
              <div class="controls">
                <input type="text"  class="span11" placeholder="Enter city" name="city"  />
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" name="submit1" class="btn btn-success">Save</button>
            </div>

            <div class="alert alert-success" id="success" style="display: none">
       YAY! Record saved successfully!
        </div>

          </form>
        </div>

      </div>

      <div class="widget-content nopadding">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>First name</th>
                  <th>Last name</th>
                  <th>Business Name(s)</th>
                  <th>Contact </th>
                  <th>Address </th>
                  <th>City </th>
                  <th>Edit</th>
                  <th>Delete</th>
                </tr>
              </thead>
              <tbody>

              <?php
              $res = mysqli_query($link, "select * from part_info");

              while($row=mysqli_fetch_array($res))
              {
                ?> 
                <tr>
                  <td><?php echo $row ["firstname"]; ?></td>
                  <td><?php echo $row ["lastname"]; ?></td>
                  <td><?php echo $row ["businessname"]; ?></td>
                  <td><?php echo $row ["contact"]; ?></td>
                 <td><?php echo $row ["address" ]; ?></td>
                 <td><?php echo $row ["city" ]; ?></td>
                  <td><a href="edit_party.php?id=<?php echo $row["id"]; ?>">Edit</a></td>
                  <td><a href="delete_party.php?id=<?php echo $row["id"]; ?>">Delete</a></td>
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
if(isset($_POST["submit1"]))
{
  
    mysqli_query($link, "insert into part_info values(NULL,'$_POST[firstname]','$_POST[lastname]','$_POST[businessname]','$_POST[contact]','$_POST[address]','$_POST[city]')") or die(mysqli_error($link));

    ?>
<script type="text/javascript">
  document.getElementById("error").style.display="none";
  document.getElementById("success").style.display="block";
  setTimeout(function(){
    window.location.href =window.location.href;
  },1000);
</script>
    <?php

  
}
?>

<?php
include "footer.php";
?>