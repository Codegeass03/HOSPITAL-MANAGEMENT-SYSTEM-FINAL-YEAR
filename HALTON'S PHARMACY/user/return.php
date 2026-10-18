<?php
session_start();
if(!isset($_SESSION["user"]))
{
?>
<script type="text/javascript">
  window.location="index.php";
</script>
<?php
}
include "../user/connection.php";
$id=$_GET["id"];
$bill_id="";
$product_company="";
$product_name="";
$product_unit="";
$packing_size="";
$price="";
$quantity="";
$total=0;

$res=mysqli_query($link, "select * from billing_details where id=$id");
while($row=mysqli_fetch_array($res))
{
    $bill_id=$row["bill_id"];
    $product_company=$row["product_company"];
    $product_name=$row["product_name"];
    $product_unit=$row["product_unit"];
    $packing_size=$row["packing_size"];
    $price=$row["price"];
    $quantity=$row["quantity"];
    $total= $price * $quantity;
}

$bill_no="";
$res2=mysqli_query($link, "select * from billing_header where id=$bill_id");
while($row2 = mysqli_fetch_array($res2)) 
{
    $bill_no=$row2["bill_no"];
}

$today_date=date('Y-m-d');

mysqli_query($link, "insert into return_products values(NULL, '$_SESSION[user]', '$bill_no', '$today_date', '$product_company', '$product_name', '$product_unit', '$packing_size', '$price', '$quantity', '$total')");
mysqli_query($link, "update stock_master set product_quantity=product_quantity+$quantity where product_company='$product_company' && product_name='$product_name' && product_unit='$product_unit' && packing_size='$packing_size')");
mysqli_query($link, "delete from billing_details where id=$id");
?>

<script type="text/javascript">
    alert("Product taken as a Return successfully!")
    window.location="view_bill_details.php?id=<?php echo $bill_id; ?>";
</script>