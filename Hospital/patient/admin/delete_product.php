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

if (isset($_POST['index'])) {
    $index = intval($_POST['index']);

    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        // Reindex the array to maintain order
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        echo "Product deleted successfully!";
    } else {
        echo "Product not found in the cart.";
    }
} else {
    echo "Invalid request.";
}
?>
