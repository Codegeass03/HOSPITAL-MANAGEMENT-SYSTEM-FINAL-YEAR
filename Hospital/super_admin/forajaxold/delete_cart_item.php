<?php
session_start();

$index = $_GET['index'];

if (isset($_SESSION['cart'][$index])) {
    unset($_SESSION['cart'][$index]);
    // Re-index array
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    echo "Item removed from cart";
} else {
    echo "Item not found in cart";
}
?>