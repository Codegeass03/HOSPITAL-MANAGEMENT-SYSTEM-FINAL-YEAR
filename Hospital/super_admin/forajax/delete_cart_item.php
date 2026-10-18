<?php
session_start();

$response = ['success' => false];

if (isset($_GET['index']) && isset($_SESSION['cart'][$_GET['index']])) {
    unset($_SESSION['cart'][$_GET['index']]);

    // Reindex array to avoid holes
    $_SESSION['cart'] = array_values($_SESSION['cart']);

    $response['success'] = true;
}

echo json_encode($response);
