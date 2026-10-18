<?php
include "../admin/connection.php";

$company_name = $_GET['company_name'];
$product_name = $_GET['product_name'];
$unit = $_GET['unit'];
$packing_size = $_GET['packing_size'];

$res = mysqli_query($link, "SELECT product_selling_price FROM stock_master 
                            WHERE product_company='$company_name' 
                            AND product_name='$product_name' 
                            AND product_unit='$unit' 
                            AND packing_size='$packing_size'
                            AND product_quantity > 0");

if($row = mysqli_fetch_array($res)) {
    echo $row['product_selling_price'];
} else {
    echo "0";
}
?>