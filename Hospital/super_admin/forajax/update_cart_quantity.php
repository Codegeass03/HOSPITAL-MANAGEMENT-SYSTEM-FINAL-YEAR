<?php
session_start();

$response = ['success' => false];

$index = $_POST['index'] ?? null;
$quantity = $_POST['quantity'] ?? null;

if (
    $index !== null &&
    $quantity !== null &&
    $quantity > 0 &&
    isset($_SESSION['cart'][$index])
) {
    $_SESSION['cart'][$index]['quantity'] = (int)$quantity;
    $response['success'] = true;
}

echo json_encode($response);
