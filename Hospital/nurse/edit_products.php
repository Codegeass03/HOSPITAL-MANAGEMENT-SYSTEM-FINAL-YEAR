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
    $company_name="";
    $product_category="";
    $product_name="";
    $unit="";
    $packing_size="";
    $res=mysqli_query($link, "select * from products where id=$id");
    while($row=mysqli_fetch_array($res))
    {
        $company_name=$row["company_name"];
        $product_category=$row["product_category"];
        $product_name=$row["product_name"];
        $unit=$row["unit"];
        $packing_size=$row["packing_size"];
    }
?>

<!--main-container-part-->
<div id="content">
    <!--breadcrumbs-->
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            EDIT PRODUCTS</a></div>
    </div>
    <!--End-breadcrumbs-->

    <!--Action boxes-->
    <div class="container-fluid">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
            <div class="span12" style="display: flex; justify-content: center;">
                <div class="widget-box" style="width: 80%; max-width: 1200px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                    <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;"> 
                        <span class="icon" style="color: white;"> <i class="icon-align-justify"></i> </span>
                        <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">EDIT PRODUCT INFORMATION</h5>
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
                                            ?>
                                            <option value="<?php echo $row["businessname"]; ?>" <?php if($row["businessname"]==$company_name) {echo "selected";} ?>>
                                            <?php
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
                                    <input type="text" name="product_category" id="product_category" class="span11" placeholder="Auto-filled from supplier" value="<?php echo $product_category; ?>" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Product Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_name" class="span11" placeholder="Enter Product Name" value="<?php echo $product_name; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
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
                                            ?>
                                            <option <?php if($row["unit"]==$unit) {echo "selected";} ?>>
                                            <?php
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
                                    <input type="text" name="packing_size" class="span11" placeholder="Enter packing Size" value="<?php echo $packing_size; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>

                            <div class="alert alert-danger" id="error" style="display: none; text-align: center; background-color: #ffebee; border: 1px solid #f44336; color: #c62828; padding: 10px; border-radius: 5px;">
                                <i class="icon-warning-sign"></i> OOPS! Product already exists, please try another!
                            </div>

                            <div class="form-actions" style="text-align: center; background-color: #bbdefb; padding: 15px; border-radius: 5px; border: 1px solid #90caf9;">
                                <button type="submit" name="submit1" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;">
                                    <i class="icon-save"></i> Update Product
                                </button>
                                <a href="add_products.php" class="btn btn-secondary" style="padding: 8px 20px; font-weight: bold; margin-left: 10px; background-color: #6c757d; color: white; text-decoration: none; display: inline-block; border-radius: 5px;">
                                    <i class="icon-arrow-left"></i> Back to Products
                                </a>
                            </div>

                            <div class="alert alert-success" id="success" style="display: none; text-align: center; background-color: #e8f5e8; border: 1px solid #4caf50; color: #2e7d32; padding: 10px; border-radius: 5px;">
                                <i class="icon-ok"></i> Product updated successfully!
                            </div>
                        </form>
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
    mysqli_query($link, "update products set company_name = '$_POST[company_name]', product_category='$_POST[product_category]', product_name='$_POST[product_name]', unit='$_POST[unit]', packing_size='$_POST[packing_size]' where id = $id") or die(mysqli_error($link));
    ?>
    <script type="text/javascript">
        document.getElementById("error").style.display="none";
        document.getElementById("success").style.display="block";
        setTimeout(function(){
            window.location.href= "add_products.php";
        },1000);
    </script>
    <?php
}
?>

<?php
include "includes/footer.php";
?>