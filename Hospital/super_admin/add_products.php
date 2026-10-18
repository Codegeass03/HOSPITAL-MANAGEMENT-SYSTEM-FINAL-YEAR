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
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    
</body>
</html>
<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            ADD NEW PRODUCT</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid" style="padding-left: 280px;">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
            <div class="span12" style="display: flex; justify-content: center; margin-bottom: 30px;">
                <div class="widget-box" style="width: 80%; max-width: 1200px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                    <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5; display: flex; align-items: center; justify-content: space-between; padding: 10px 15px;"> 
                        <a href="inventorylist.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                            <i class="icon-arrow-left"></i>
                            <span style="font-size: 12px;">INVENTORY MANAGEMENT</span>
                        </a>
                        <h5 style="color: white; margin: 0; flex-grow: 1; text-align: center;">ADD NEW PRODUCT</h5>
                        <div style="width: 180px;"></div> <!-- Spacer for centering -->
                    </div>
                    <div class="widget-content nopadding" style="background-color: #e3f2fd; padding: 20px;">
                        <form name="form1" action="" method="post" class="form-horizontal">
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Select Company :</label>
                                <div class="controls" style="width: 300px;">
                                    <select class="span11" name="company_name" id="company_name" onchange="fetchProductCategory()" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <option value="">-- Select Company --</option>
                                        <?php 
                                        $res=mysqli_query($link, "select businessname from suppliers");
                                        while($row=mysqli_fetch_array($res))
                                        {
                                            echo "<option value='".$row["businessname"]."'>";
                                            echo $row["businessname"];
                                            echo "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Product Category :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_category" id="product_category" class="span11" placeholder="Auto-filled from supplier" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Product Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_name" class="span11" placeholder="Enter Product Name" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Select Unit :</label>
                                <div class="controls" style="width: 300px;">
                                    <select class="span11" name="unit" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <?php 
                                        $res=mysqli_query($link, "select * from unit");
                                        while($row=mysqli_fetch_array($res))
                                        {
                                            echo "<option>";
                                            echo $row["unit"];
                                            echo "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 20px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Packing Size :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="packing_size" class="span11" placeholder="Enter packing Size" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>

                            <div class="alert alert-danger" id="error" style="display: none; text-align: center; background-color: #ffebee; border: 1px solid #f44336; color: #c62828; padding: 10px; border-radius: 5px;">
                                <i class="icon-warning-sign"></i> OOPS! Product already exists, please try another!
                            </div>

                            <div class="form-actions" style="text-align: center; background-color: #bbdefb; padding: 15px; border-radius: 5px; border: 1px solid #90caf9;">
                                <button type="submit" name="submit1" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;">
                                    <i class="icon-save"></i> Save Product
                                </button>
                            </div>

                            <div class="alert alert-success" id="success" style="display: none; text-align: center; background-color: #e8f5e8; border: 1px solid #4caf50; color: #2e7d32; padding: 10px; border-radius: 5px;">
                                <i class="icon-ok"></i> Product saved successfully!
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="span12" style="display: flex; justify-content: center;">
                <div class="widget-box" style="width: 95%; max-width: 1400px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                    <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;">
                        <span class="icon" style="color: white;"> <i class="icon-th-list"></i> </span>
                        <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">PRODUCT INVENTORY</h5>
                    </div>
                    <div class="widget-content nopadding" style="padding: 20px; display: flex; justify-content: center;">
                        <table class="table table-bordered table-striped" style="width: 95%; max-width: 1300px; border: 1px solid #e3f2fd; table-layout: fixed;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 5%;">ID</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 18%;">Company Name</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 15%;">Product Category</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 20%;">Product Name</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 10%;">Unit</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 12%;">Packing Size</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 10%;">Edit</th>
                                    <th style="text-align: center; border: 1px solid #90caf9; color: #1565c0; font-weight: bold; width: 10%;">Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = mysqli_query($link, "select * from products");
                                while($row=mysqli_fetch_array($res))
                                {
                                    ?> 
                                    <tr style="background-color: white;">
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;"><?php echo $row["id"]; ?></td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;"><?php echo $row["company_name"]; ?></td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;"><?php echo $row["product_category"]; ?></td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;"><?php echo $row["product_name"]; ?></td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;"><?php echo $row["unit"]; ?></td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;"><?php echo $row["packing_size"]; ?></td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;">
                                            <a href="edit_products.php?id=<?php echo $row["id"]; ?>" class="btn btn-mini btn-info" style="padding: 4px 8px;">
                                                <i class="icon-edit"></i> Edit
                                            </a>
                                        </td>
                                        <td style="text-align: center; border: 1px solid #e3f2fd; padding: 8px;">
                                            <a href="delete_products.php?id=<?php echo $row["id"]; ?>" class="btn btn-mini btn-danger" style="padding: 4px 8px;" onclick="return confirm('Are you sure you want to delete this product?');">
                                                <i class="icon-trash"></i> Delete
                                            </a>
                                        </td>
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
    </div>
</div>

<script type="text/javascript">
function fetchProductCategory() {
    var businessName = document.getElementById("company_name").value;
    
    if(businessName === "") {
        document.getElementById("product_category").value = "";
        return;
    }
    
    // Create AJAX request
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "get_product_category.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4 && xhr.status === 200) {
            document.getElementById("product_category").value = xhr.responseText;
        }
    };
    
    xhr.send("businessname=" + encodeURIComponent(businessName));
}
</script>

<?php
if(isset($_POST["submit1"]))
{
    $count=0;
    $res=mysqli_query($link, "select * from products where company_name = '$_POST[company_name]' && product_name='$_POST[product_name]' && unit='$_POST[unit]' && packing_size='$_POST[packing_size]'");
    $count=mysqli_num_rows($res);

    if($count>0)
    {
        ?>
        <script type="text/javascript">
            document.getElementById("success").style.display="none";
            document.getElementById("error").style.display="block";
        </script>
        <?php
    }
    else {
        mysqli_query($link, "insert into products values(NULL,'$_POST[company_name]', '$_POST[product_category]', '$_POST[product_name]', '$_POST[unit]', '$_POST[packing_size]')") or die(mysqli_error($link));
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