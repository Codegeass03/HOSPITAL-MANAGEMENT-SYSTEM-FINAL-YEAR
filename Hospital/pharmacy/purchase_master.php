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

<div id="content">
    <div id="content-header">
        <div id="breadcrumb"><a href="index.html" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>
            ADD NEW PURCHASE</a></div>
    </div>
    <div class="container-fluid" style="padding-left: 40px;">

        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
            <div class="span12" style="display: flex; justify-content: center;">
                <div class="widget-box" style="width: 80%; max-width: 1200px; border: 2px solid #1e88e5; border-radius: 10px; overflow: hidden;">
                    <div class="widget-title" style="background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); border-bottom: 2px solid #1e88e5;"> 
                        <span class="icon" style="color: white;"> <i class="icon-align-justify"></i> </span>
                        <h5 style="text-align: center; color: white; margin: 0; padding: 10px;">ADD NEW PURCHASE</h5>
                    </div>
                    <div class="widget-content nopadding" style="background-color: #e3f2fd; padding: 20px;">
                        <form name="form1" action="" method="post" class="form-horizontal">
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Select Company :</label>
                                <div class="controls" style="width: 300px;">
                                    <select class="span11" name="company_name" id="company_name" onchange="select_company(this.value)" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <option>Select</option>
                                        <?php 
                                        $res=mysqli_query($link, "select * from suppliers");
                                        while($row=mysqli_fetch_array($res))
                                        {
                                            echo "<option>";
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
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Select Product Name :</label>
                                <div class="controls" id="product_name_div" style="width: 300px;">
                                    <select class="span11" name="product_name" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <option>Select</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Select Unit :</label>
                                <div class="controls" id="unit_div" style="width: 300px;">
                                    <select class="span11" name="unit" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <option>Select</option>
                                    </select>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Packing Size :</label>
                                <div class="controls" id="packing_size_div" style="width: 300px;">
                                    <select class="span11" name="packing_size" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <option>Select</option>
                                    </select>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Quantity :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="quantity" value="0" class="span11" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Price :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="price" value="0" class="span11" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>
                            
                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Selling Price :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="text" name="selling_price" value="0" class="span11" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Select Party Name :</label>
                                <div class="controls" style="width: 300px;">
                                    <select class="span11" name="party_name" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <?php
                                        $res=mysqli_query($link, "select * from part_info");
                                        while($row=mysqli_fetch_array($res))
                                        {
                                            echo "<option>";
                                            echo $row["businessname"];
                                            echo "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 15px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Purchase Method :</label>
                                <div class="controls" style="width: 300px;">
                                    <select class="span11" name="purchase_method" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                        <option>cash</option>
                                        <option>Mpesa</option>
                                        <option>Debit card</option>
                                    </select>
                                </div>
                            </div>

                            <div class="control-group" style="display: flex; justify-content: center; margin-bottom: 20px;">
                                <label class="control-label" style="width: 200px; text-align: right; margin-right: 15px; color: #1565c0; font-weight: bold;">Enter Expiry Date :</label>
                                <div class="controls" style="width: 300px;">
                                    <input type="date" name="expiry_date" id="dt" class="span11" placeholder="YYYY-MM-DD" required pattern="\d{4}-\d{2}-\d{2}" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
                                </div>
                            </div>

                            <div class="form-actions" style="text-align: center; background-color: #bbdefb; padding: 15px; border-radius: 5px; border: 1px solid #90caf9;">
                                <button type="submit" name="submit1" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;">
                                    <i class="icon-save"></i> Purchase Now
                                </button>
                            </div>

                            <div class="alert alert-success" id="success" style="display: none; text-align: center; background-color: #e8f5e8; border: 1px solid #4caf50; color: #2e7d32; padding: 10px; border-radius: 5px; margin-top: 15px;">
                                <i class="icon-ok"></i> Purchase made successfully!
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">
    function select_company(company_name)
    {
        // Fetch product category
        fetchProductCategory(company_name);
        
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
                document.getElementById("product_name_div").innerHTML=xmlhttp.responseText;
                // Since the product select is now loaded, call the next function to load its children
                var newProductNameSelect = document.querySelector('#product_name_div select');
                if (newProductNameSelect && newProductNameSelect.value !== 'Select') {
                    select_product(newProductNameSelect.value, company_name);
                }
            }
        };
        xmlhttp.open("GET", "forajax/load_product_using_company.php?company_name=" +company_name, true);
        xmlhttp.send();
    }

    function fetchProductCategory(company_name) {
        if(company_name === "Select" || company_name === "") {
            document.getElementById("product_category").value = "";
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "get_product_category.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        
        xhr.onreadystatechange = function() {
            if(xhr.readyState === 4 && xhr.status === 200) {
                document.getElementById("product_category").value = xhr.responseText;
            }
        };
        
        xhr.send("businessname=" + encodeURIComponent(company_name));
    }

function select_product(product_name, company_name)
{
    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
                document.getElementById("unit_div").innerHTML=xmlhttp.responseText;
                // Call next function
                var newUnitSelect = document.querySelector('#unit_div select');
                if (newUnitSelect && newUnitSelect.value !== 'Select') {
                    select_unit(newUnitSelect.value, product_name, company_name);
                }
            }
        };
        xmlhttp.open("GET", "forajax/load_unit_using_products.php?product_name="+product_name+"&company_name="+company_name, true);
        xmlhttp.send();
}

function select_unit(unit, product_name, company_name)
{
    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
                document.getElementById("packing_size_div").innerHTML=xmlhttp.responseText;
            }
        };
        xmlhttp.open("GET", "forajax/load_packingsize_using_unit.php?unit="+unit+"&product_name="+product_name+"&company_name="+company_name, true);
        xmlhttp.send();
}

// Attach listeners to dynamically loaded selects (requires a library like jQuery or a more complex vanilla JS approach)
// For simplicity, make sure the AJAX files (like load_product_using_company.php) add the onchange attributes to the select tags they return:
/* Example in load_product_using_company.php:
    echo '<select class="span11" name="product_name" onchange="select_product(this.value, document.getElementById(\'company_name\').value)">';
*/

</script>


<?php
if(isset($_POST["submit1"]))
{
    // Use clear variable names and ensure they match the case of your HTML input 'name' attributes
    // Assuming you used these names in the HTML: name="quantity", name="price", name="selling_price", name="expiry_date"
    $company_name = mysqli_real_escape_string($link, $_POST['company_name']);
    $product_name = mysqli_real_escape_string($link, $_POST['product_name']);
    $unit = mysqli_real_escape_string($link, $_POST['unit']);
    $packing_size = mysqli_real_escape_string($link, $_POST['packing_size']);
    $quantity = mysqli_real_escape_string($link, $_POST['quantity']); // ERROR: Was looking for 'Quantity'
    $price = mysqli_real_escape_string($link, $_POST['price']);       // ERROR: Was looking for 'Price' (this is cost price)
    $selling_price = mysqli_real_escape_string($link, $_POST['selling_price']); // ERROR: Was looking for 'product_selling_price'
    $party_name = mysqli_real_escape_string($link, $_POST['party_name']);
    $purchase_method = mysqli_real_escape_string($link, $_POST['purchase_method']);
    $expiry_date = mysqli_real_escape_string($link, $_POST['expiry_date']); // ERROR: Was looking for 'dt'
    $today_date = date("Y-m-d");
    $admin_user = mysqli_real_escape_string($link, $_SESSION['admin']);
    
    // 1. Insert into purchase_master (Based on your purchase_master schema)
    // The variables used here (e.g., $quantity, $price, $expiry_date) now correctly map to the form inputs.
    mysqli_query($link, "INSERT INTO purchase_master VALUES(NULL, '$company_name', '$product_name', '$unit', '$packing_size', '$quantity', '$price', '$party_name', '$purchase_method', '$expiry_date', '$today_date', '$admin_user')") or die (mysqli_error($link));
    
    // 2. Update/Insert into stock_master
    $count=0;
    
    // Check if the product/unit/packing_size combination exists in stock
    // FIX 1: Changed 'product_packing_size' to 'packing_size' to match your stock_master table schema.
    $res=mysqli_query($link, "SELECT * FROM stock_master WHERE product_company='$company_name' AND product_name='$product_name' AND product_unit='$unit' AND packing_size='$packing_size'");
    $count=mysqli_num_rows($res);

    if($count==0)
    {
        // Insert new stock entry (Based on your stock_master schema: 7 columns + ID)
        // stock_master: id, product_company, product_name, product_unit, packing_size, product_quantity, product_selling_price
        // FIX 2: Removed the placeholder '0' for a non-existent 8th column from the last version. Your stock_master only has 7 columns plus the ID, making 8 total values needed.
        // It looks like the $selling_price should be inserted into product_selling_price, and the quantity is $quantity.
        mysqli_query($link, "INSERT INTO stock_master (product_company, product_name, product_unit, packing_size, product_quantity, product_selling_price) VALUES('$company_name', '$product_name', '$unit', '$packing_size', '$quantity', '$selling_price')") or die(mysqli_error($link));
    } else {
        // Update existing stock
        // FIX 3: Changed 'product_packing_size' to 'packing_size' to match your stock_master table schema.
        mysqli_query($link, "UPDATE stock_master SET product_quantity=product_quantity + $quantity WHERE product_company='$company_name' AND product_name='$product_name' AND product_unit='$unit' AND packing_size='$packing_size'") or die(mysqli_error($link));
    }
    ?> 
    <script type="text/javascript">
        document.getElementById("success").style.display="block";
        setTimeout(function(){
            window.location.href = window.location.href;
        },2000);
    </script>
    <?php
}
?>

<?php
include "includes/footer.php";
?>