<?php
session_start();

if (isset($_GET['index']) && isset($_GET['quantity'])) {
    $index = intval($_GET['index']);
    $quantity = intval($_GET['quantity']);
    
    if (isset($_SESSION['cart'][$index]) && $quantity > 0) {
        $_SESSION['cart'][$index]['quantity'] = $quantity;
        echo "Quantity updated";
    }
}
?>