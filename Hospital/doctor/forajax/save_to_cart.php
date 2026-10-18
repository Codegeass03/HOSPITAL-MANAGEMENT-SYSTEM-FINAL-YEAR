<?php
session_start();

$product_company = $_GET['company_name'];
$product_name = $_GET['product_name'];
$unit = $_GET['unit'];
$packing_size = $_GET['packing_size'];
$price = $_GET['price'];
$quantity = $_GET['quantity'];
$total = $_GET['total'];

// Check if product already exists in cart
$found = false;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_company'] == $product_company && 
            $item['product_name'] == $product_name && 
            $item['unit'] == $unit && 
            $item['packing_size'] == $packing_size) {
            // Update quantity
            $_SESSION['cart'][$key]['quantity'] += $quantity;
            $_SESSION['cart'][$key]['total'] = $_SESSION['cart'][$key]['quantity'] * $price;
            $found = true;
            break;
        }
    }
}

if (!$found) {
    // Add new item to cart - use consistent field names
    $cart_item = array(
        'product_company' => $product_company,
        'product_name' => $product_name,
        'unit' => $unit,
        'packing_size' => $packing_size,
        'price' => $price,
        'quantity' => $quantity,
        'total' => $total
    );
    
    $_SESSION['cart'][] = $cart_item;
}

// Return empty response on success
echo "";
?>