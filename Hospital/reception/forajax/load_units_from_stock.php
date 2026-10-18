<?php
include "../admin/connection.php";

$product_name = $_GET['product_name'];
$company_name = $_GET['company_name'];

$res = mysqli_query($link, "SELECT DISTINCT product_unit FROM stock_master 
                            WHERE product_name='$product_name' 
                            AND product_company='$company_name'
                            AND product_quantity > 0 
                            ORDER BY product_unit");

echo '<select class="form-select" name="unit" id="unit" 
      onchange="select_unit(this.value, \'' . $product_name . '\', \'' . $company_name . '\')">';
echo '<option value="">Select Unit</option>';

while($row = mysqli_fetch_array($res)) {
    echo '<option value="' . htmlspecialchars($row['product_unit']) . '">';
    echo htmlspecialchars($row['product_unit']);
    echo '</option>';
}

echo '</select>';
?>