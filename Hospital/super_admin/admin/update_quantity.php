<?php
session_start();
if(!isset($_SESSION["admin"]))
{
?>
<script type="text/javascript">
  window.location="index.php";
</script>
<?php
}
?>
<?php
session_start();

if (isset($_POST['index']) && isset($_POST['quantity'])) {
    $index = intval($_POST['index']);
    $new_quantity = intval($_POST['quantity']);

    if (isset($_SESSION['cart'][$index])) {
        if ($new_quantity > 0) { // Ensure the quantity is valid
            $_SESSION['cart'][$index]['quantity'] = $new_quantity;
            echo "Quantity updated successfully!";
        } else {
            echo "Quantity must be greater than 0.";
        }
    } else {
        echo "Product not found in the cart.";
    }
} else {
    echo "Invalid request.";
}
?>

