<?php
session_start();
include "../admin/connection.php";

$company_name = $_GET['company_name'];
$product_name = $_GET['product_name'];
$unit = $_GET['unit'];
$packing_size = $_GET['packing_size'];
$price = floatval($_GET['price']);
$quantity = intval($_GET['quantity']);

mysqli_select_db($link, "php_ims");

/* Fetch available stock */
$stmt = mysqli_prepare($link, "
    SELECT product_quantity 
    FROM stock_master 
    WHERE product_company = ? 
      AND product_name = ? 
      AND product_unit = ? 
      AND packing_size = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ssss", $company_name, $product_name, $unit, $packing_size);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $available_stock);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

/* Calculate quantity already in cart */
$cart_quantity = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (
            $item['company_name'] === $company_name &&
            $item['product_name'] === $product_name &&
            $item['unit'] === $unit &&
            $item['packing_size'] === $packing_size
        ) {
            $cart_quantity += $item['quantity'];
        }
    }
}

/* Final stock check */
if ($available_stock === null || ($cart_quantity + $quantity) > $available_stock) {
    http_response_code(400);
    echo "ERROR: Insufficient stock available. In stock: {$available_stock}";
    exit;
}

/* Add to cart */
$_SESSION['cart'][] = [
    'company_name' => $company_name,
    'product_name' => $product_name,
    'unit' => $unit,
    'packing_size' => $packing_size,
    'price' => $price,
    'quantity' => $quantity
];

echo "SUCCESS";
