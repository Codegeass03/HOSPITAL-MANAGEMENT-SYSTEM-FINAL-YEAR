<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Cart Count:</strong> " . (isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0) . "<br><br>";

if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    echo "<strong>Cart Contents:</strong><br>";
    foreach($_SESSION['cart'] as $index => $item) {
        echo "Item $index: ";
        echo "Company: " . ($item['company_name'] ?? 'N/A') . ", ";
        echo "Product: " . ($item['product_name'] ?? 'N/A') . ", ";
        echo "Unit: " . ($item['unit'] ?? 'N/A') . ", ";
        echo "Packing: " . ($item['packing_size'] ?? 'N/A') . ", ";
        echo "Price: " . ($item['price'] ?? 'N/A') . ", ";
        echo "Qty: " . ($item['quantity'] ?? 'N/A');
        echo "<br>";
    }
    
    // Calculate total manually
    $manual_total = 0;
    foreach($_SESSION['cart'] as $item) {
        if(isset($item['price']) && isset($item['quantity'])) {
            $manual_total += ($item['price'] * $item['quantity']);
        }
    }
    echo "<br><strong>Manual Total Calculation:</strong> Kshs " . number_format($manual_total, 2);
} else {
    echo "<strong>Cart Status:</strong> Empty or not set";
}
?>