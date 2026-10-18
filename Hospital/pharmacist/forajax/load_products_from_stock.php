<!-- File: forajax/load_products_from_stock.php -->
<?php
include "../admin/connection.php";

$company_name = $_GET['company_name'];

$res = mysqli_query($link, "SELECT DISTINCT product_name FROM stock_master 
                            WHERE product_company='$company_name' 
                            AND product_quantity > 0 
                            ORDER BY product_name");

echo '<select class="form-select" name="product_name" id="product_name" 
      onchange="select_product(this.value, \'' . $company_name . '\')">';
echo '<option value="">Select Product</option>';

while($row = mysqli_fetch_array($res)) {
    echo '<option value="' . htmlspecialchars($row['product_name']) . '">';
    echo htmlspecialchars($row['product_name']);
    echo '</option>';
}

echo '</select>';
?>
