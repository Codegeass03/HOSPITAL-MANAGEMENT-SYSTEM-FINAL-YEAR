<?php
include "../../user/connection.php";
$company_name=$_GET["company_name"];
$product_name=$_GET["product_name"];
$unit=$_GET["unit"];
$packing_size=$_GET["packing_size"];

$res=mysqli_query($link, "select * from stock_master where product_company='$company_name' && product_name='$product_name' && product_unit='$unit' && packing_size='$packing_size'");

while($row=mysqli_fetch_array($res))
{
    echo $row["product_selling_price"];
}
?>