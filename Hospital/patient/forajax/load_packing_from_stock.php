<?php
include "../admin/connection.php";

$unit = $_GET['unit'];
$product_name = $_GET['product_name'];
$company_name = $_GET['company_name'];

$res = mysqli_query($link, "SELECT DISTINCT packing_size FROM stock_master 
                            WHERE product_unit='$unit' 
                            AND product_name='$product_name' 
                            AND product_company='$company_name'
                            AND product_quantity > 0 
                            ORDER BY packing_size");

echo '<select class="form-select" name="packing_size" id="packing_size" onchange="load_price(this.value)">';
echo '<option value="">Select Packing Size</option>';

while($row = mysqli_fetch_array($res)) {
    echo '<option value="' . htmlspecialchars($row['packing_size']) . '">';
    echo htmlspecialchars($row['packing_size']);
    echo '</option>';
}

echo '</select>';
?>