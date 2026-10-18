<?php
// forajax/load_cart_total.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$total = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        if(isset($item['price']) && isset($item['quantity'])) {
            $total += ($item['price'] * $item['quantity']);
        }
    }
}
echo number_format($total, 2);
?>