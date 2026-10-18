<?php
include "../../includes/db_connect.php";
// include "../includes/sidebar.php";
include "includes/functions.php";

$items = getAllItems($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Auracare | Inventory Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<h2>Inventory Dashboard</h2>

<a href="add_item.php" class="btn">Add New Item</a>
<a href="low_stock.php" class="btn">Low Stock Alerts</a>
<a href="expiry_alerts.php" class="btn">Expiry Alerts</a>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Medicine Name</th>
            <th>Generic Name</th>
            <th>Location</th>
            <th>Quantity</th>
            <th>Price (KSh)</th>
            <th>Expiry</th>
            <th>Min Stock</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $items->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= $row['brand_name']; ?></td>
            <td><?= $row['generic_name']; ?></td>
            <td><?= $row['location']; ?></td>
            <td><?= $row['quantity']; ?></td>
            <td><?= number_format($row['selling_price'], 2); ?></td>
            <td><?= $row['expiry_date']; ?></td>
            <td><?= $row['min_stock']; ?></td>
            <td>
                <a href="edit_item.php?id=<?= $row['id']; ?>">Edit</a> | 
                <a href="delete_item.php?id=<?= $row['id']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
