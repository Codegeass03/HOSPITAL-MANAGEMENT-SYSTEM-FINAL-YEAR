<?php
function sanitize($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function getAllItems($conn) {
    $sql = "SELECT i.id, m.brand_name, m.generic_name, i.location, i.quantity, i.selling_price, 
                   m.expiry_date, m.min_stock 
            FROM inventory_items i
            JOIN medicines m ON i.medicine_id = m.id
            ORDER BY m.brand_name ASC";
    return $conn->query($sql);
}

function getLowStockItems($conn) {
    $sql = "SELECT m.brand_name, i.quantity, m.min_stock
            FROM inventory_items i
            JOIN medicines m ON i.medicine_id = m.id
            WHERE i.quantity <= m.min_stock";
    return $conn->query($sql);
}

function getExpiringItems($conn) {
    $sql = "SELECT m.brand_name, m.expiry_date, DATEDIFF(m.expiry_date, CURDATE()) AS days_left
            FROM medicines m
            WHERE m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
            ORDER BY m.expiry_date ASC";
    return $conn->query($sql);
}
?>
