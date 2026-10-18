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
    $product_company="";
    $product_name="";
    $product_unit="";
    $packing_size="";
    $product_quantity="";
    $product_selling_price="";
    $res=mysqli_query($link, "select * from stock_master where id=$id");
    while($row=mysqli_fetch_array($res))
    {
        $product_company=$row["product_company"];
        $product_name=$row["product_name"];
        $product_unit=$row["product_unit"];
        $packing_size=$row["packing_size"];
        $product_quantity=$row["product_quantity"];
        $product_selling_price=$row["product_selling_price"];
    }
?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb"><a href="stock_master.php" title="Go to Stock" class="tip-bottom"><i class="icon-home"></i>
            EDIT STOCK PRICE</a></div>
    </div>
    <div class="container-fluid" style="padding-left: 40px;">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
            <div class="span12" style="display: flex; justify-content: center;">
                <div class="widget-box" style="width: 80%; max-width: 1200px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                    <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;"> 
                        <span class="icon" style="color: white;"> <i class="icon-align-justify"></i> </span>
                        <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">EDIT STOCK PRICE</h5>
                    </div>
                    <div class="widget-content nopadding" style="background-color: #e3f2fd; padding: 20px;">
                        <form name="form1" action="" method="post" class="form-horizontal">
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Product Company :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_company" class="span11" value="<?php echo $product_company; ?>" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Product Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_name" class="span11" value="<?php echo $product_name; ?>" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Product Unit :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_unit" class="span11" value="<?php echo $product_unit; ?>" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Packing Size :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="packing_size" class="span11" value="<?php echo $packing_size; ?>" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Product Quantity :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_quantity" class="span11" value="<?php echo $product_quantity; ?>" readonly style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: #f0f8ff;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 20px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Selling Price :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="product_selling_price" class="span11" placeholder="Enter selling price" value="<?php echo $product_selling_price; ?>" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>

                            <div class="form-actions" style="text-align: center; background-color: #bbdefb; padding: 15px; border-radius: 5px; border: 1px solid #90caf9;">
                                <button type="submit" name="submit1" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;">
                                    <i class="icon-save"></i> Update Price
                                </button>
                            </div>

                            <div class="alert alert-success" id="success" style="display: none; text-align: center; background-color: #e8f5e8; border: 1px solid #4caf50; color: #2e7d32; padding: 10px; border-radius: 5px; margin-top: 15px;">
                                <i class="icon-ok"></i> Product updated successfully! Redirecting...
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
    $product_selling_price = mysqli_real_escape_string($link, $_POST['product_selling_price']);
    mysqli_query($link, "UPDATE stock_master SET product_selling_price='$product_selling_price' WHERE id=$id") or die(mysqli_error($link));
    ?>
    <script type="text/javascript">
        document.getElementById("success").style.display="block";
        setTimeout(function(){
            window.location = "inventorylist.php";
        }, 2000);
    </script>
    <?php
}
?>

<?php
include "includes/footer.php";
?>