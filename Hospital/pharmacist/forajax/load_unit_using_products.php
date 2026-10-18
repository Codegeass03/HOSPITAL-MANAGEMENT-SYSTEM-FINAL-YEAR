<?php 
include "../admin/connection.php";
$company_name = $_GET["company_name"];
$product_name = $_GET["product_name"];
$res = mysqli_query($link, "select * from products where company_name='$company_name' && product_name='$product_name'");
?>
<select class="span11" name="unit" id="unit" onchange="select_unit(this.value,'<?php echo $product_name; ?>', '<?php echo $company_name; ?>')" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
<option>Select</option>
<?php
while($row = mysqli_fetch_array($res))
{
    echo "<option>";
    echo htmlspecialchars($row["unit"]);
    echo "</option>";
}
?>
</select>