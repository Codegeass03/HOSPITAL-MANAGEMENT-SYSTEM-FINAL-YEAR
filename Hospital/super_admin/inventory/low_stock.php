<?php
include "includes/db_connect.php";
include "includes/functions.php";

$low_stock = getLowStockItems($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Low Stock Alerts</title>
</head>
<body>
<h2>Low Stock Alerts</h2>
<table border="1" cellpadding="8">
    <tr>
        <th>Medicine</th>
        <th>Quantity</th>
        <th>Min Stock</th>
    </tr>
    <?php while($row = $low_stock->fetch_assoc()): ?>
    <tr style="background-color:#fdd;">
        <td><?= $row['brand_name']; ?></td>
        <td><?= $row['quantity']; ?></td>
        <td><?= $row['min_stock']; ?></td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
