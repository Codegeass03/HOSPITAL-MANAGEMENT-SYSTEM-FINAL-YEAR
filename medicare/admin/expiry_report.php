<?php
// Expiry Report - shows products expiring within 90 days
$expiryQuery = "
    SELECT 
        product_name,
        unit,
        packing_size,
        expiry_date,
        quantity,
        price,
        DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
    FROM purchase_master
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY expiry_date ASC
";
$expiryResult = $database->query($expiryQuery);
$expiryCount = $expiryResult->num_rows;

// Get expired items
$expiredQuery = "
    SELECT product_name, unit, expiry_date, quantity
    FROM purchase_master
    WHERE expiry_date < CURDATE()
    ORDER BY expiry_date DESC
";
$expiredResult = $database->query($expiredQuery);
$expiredCount = $expiredResult->num_rows;
?>

<h2 style="color: var(--primarycolor); margin-bottom: 25px;">Expiry Report (90 Days)</h2>

<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-value"><?php echo $expiryCount; ?></div>
        <div class="summary-label">Items Expiring Soon</div>
    </div>
    <div class="summary-card">
        <div class="summary-value"><?php echo $expiredCount; ?></div>
        <div class="summary-label">Expired Items</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <div>
        <h3 style="color: var(--primarydark); margin-bottom: 15px;">Items Expiring Soon</h3>
        <div class="scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Qty</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($expiryCount == 0): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">No items expiring within 90 days</td></tr>
                    <?php else: ?>
                    <?php while($row = $expiryResult->fetch_assoc()): 
                        $days = $row['days_until_expiry'];
                        $status = $days <= 30 ? 'expiring' : '';
                        $statusText = $days <= 30 ? 'Critical' : 'Warning';
                    ?>
                    <tr>
                        <td><?php echo $row['product_name']; ?></td>
                        <td><?php echo $row['unit']; ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($row['expiry_date'])); ?></td>
                        <td><?php echo $days; ?></td>
                        <td><span class="status-badge status-<?php echo $status; ?>"><?php echo $statusText; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h3 style="color: var(--primarydark); margin-bottom: 15px;">Expired Items</h3>
        <div class="scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Expired On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($expiredCount == 0): ?>
                    <tr><td colspan="3" style="text-align: center; padding: 20px;">No expired items</td></tr>
                    <?php else: ?>
                    <?php while($row = $expiredResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['product_name']; ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($row['expiry_date'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>