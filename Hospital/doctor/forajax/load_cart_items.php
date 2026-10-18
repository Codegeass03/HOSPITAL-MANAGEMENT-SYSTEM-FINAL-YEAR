<?php
session_start();

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo '<p style="text-align: center; color: #6b7280; padding: 2rem;">Cart is empty. Add products to get started.</p>';
    exit;
}

echo '<table class="bill-table">';
echo '<thead>';
echo '<tr>';
echo '<th>Product</th>';
echo '<th>Company</th>';
echo '<th>Unit</th>';
echo '<th>Packing</th>';
echo '<th>Price</th>';
echo '<th>Quantity</th>';
echo '<th>Total</th>';
echo '<th>Action</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($_SESSION['cart'] as $index => $item) {
    // Skip empty or invalid items
    if (empty($item['product_name']) || empty($item['product_company'])) {
        continue;
    }
    
    echo '<tr>';
    echo '<td>' . htmlspecialchars($item['product_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['product_company'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['packing_size'] ?? '') . '</td>';
    echo '<td>Ksh ' . number_format($item['price'] ?? 0, 2) . '</td>';
    echo '<td>' . htmlspecialchars($item['quantity'] ?? 0) . '</td>';
    echo '<td>Ksh ' . number_format($item['total'] ?? 0, 2) . '</td>';
    echo '<td>';
    echo '<button type="button" class="btn btn-danger" onclick="deleteProduct(' . $index . ')" style="padding: 0.375rem 0.75rem; font-size: 0.875rem;">';
    echo '<i class="fas fa-trash"></i> Delete';
    echo '</button>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
?>