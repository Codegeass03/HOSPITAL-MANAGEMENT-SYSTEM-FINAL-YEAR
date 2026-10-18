<?php 
include "../admin/connection.php";
$company_name = $_GET["company_name"];
$product_name = $_GET["product_name"];
$unit = $_GET["unit"];
$res = mysqli_query($link, "select * from products where company_name='$company_name' && product_name='$product_name' && unit='$unit'");
?>
<select class="span11" name="packing_size" id="packing_size" style="width: 100%; padding: 8px; border: 1px solid #90caf9; border-radius: 5px; background-color: white;">
<option>Select</option>
<?php
while($row = mysqli_fetch_array($res))
{
    echo "<option>";
    echo htmlspecialchars($row["packing_size"]);
    echo "</option>";
}
?>
</select>
