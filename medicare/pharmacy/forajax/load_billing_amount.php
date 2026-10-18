<?php
session_start();

$quantity_session = 0;
$max = 0;
$grandtotal = 0;

if (isset($_SESSION['cart'])) {
    $max = sizeof($_SESSION['cart']);
}

for ($i = 0; $i < $max; $i++) {
    $price_session = "";

    if (isset($_SESSION['cart'][$i])) {
        foreach ($_SESSION['cart'][$i] as $key => $val) {
            if ($key == "quantity") {
                $quantity_session = (int)$val; // Convert to integer
            } else if ($key == "price") {
                $price_session = (float)$val; // Convert to float
            }
        }
        $grandtotal += $quantity_session * $price_session; // Perform the multiplication
    }
}

echo $grandtotal; // Add label for clarity
?>
