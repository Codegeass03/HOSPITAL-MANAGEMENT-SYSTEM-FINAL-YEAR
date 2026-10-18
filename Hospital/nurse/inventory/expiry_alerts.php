<?php
include "includes/db_connect.php";
include "includes/functions.php";

$expiring = getExpiringItems($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Expiry Alerts</title>
</head>
<body>
<h2>Medicines Nearing Expiry (Next 60 Days)</h2>
<table border="1" cellpadding="8">
    <tr>
        <th>Medicine</th>
        <th>Expiry Date</th>
        <th>Days Left</th>
    </tr>
    <?php while($row = $expiring->fetch_assoc()): ?>
    <tr style="background-color:#ffeeba;">
        <td><?= $row['brand_name']; ?></td>
        <td><?= $row['expiry_date']; ?></td>
        <td><?= $row['days_left']; ?> days</td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
