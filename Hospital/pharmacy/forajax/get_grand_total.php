<?php
session_start();

$grand_total = 0;
if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
    foreach ($_SESSION['cart'] as $item) {
        $grand_total += $item['price'] * $item['quantity'];
    }
}

echo number_format($grand_total, 2);
?>