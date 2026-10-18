<?php
// Purchase Report
$supplierFilter = $supplierId ? "AND party_name = (SELECT businessname FROM suppliers WHERE id = ?)" : "";
$purchaseQuery = "
    SELECT 
        pm.*,
        s.businessname as supplier_name
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    WHERE purchase_date BETWEEN ? AND ? $supplierFilter
    ORDER BY purchase_date DESC
";

$params = $supplierId ? [$startDate, $endDate, $supplierId] : [$startDate, $endDate];
$purchaseResult = fetchData($purchaseQuery, $params, $database);
$totalPurchases = 0;
while($row = $purchaseResult->fetch_assoc()) {
    $totalPurchases += $row['price'] * $row['quantity'];
}
$purchaseResult->data_seek(0);
?>

<h2 style="color: var(--primarycolor); margin-bottom: 25px;">Purchase Report</h2>

<div class="summary-card" style="margin-bottom: 25px; width: fit-content;">
    <div class="summary-value">KSh <?php echo number_format($totalPurchases, 2); ?></div>
    <div class="summary-label">Total Purchase Value</div>
</div>

<div class="scroll">
    <table class="report-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Supplier</th>
                <th>Product</th>
                <th>Category</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
                <th>Method</th>
                <th>Expiry Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if($purchaseResult->num_rows == 0): ?>
            <tr><td colspan="10" style="text-align: center; padding: 20px;">No purchases found for the selected period</td></tr>
            <?php else: ?>
            <?php while($row = $purchaseResult->fetch_assoc()): 
                $total = $row['price'] * $row['quantity'];
            ?>
            <tr>
                <td><?php echo date('M j, Y', strtotime($row['purchase_date'])); ?></td>
                <td><?php echo $row['supplier_name'] ?: $row['party_name']; ?></td>
                <td><?php echo $row['product_name']; ?></td>
                <td><?php echo $row['productcategory'] ?: 'N/A'; ?></td>
                <td><?php echo $row['unit']; ?></td>
                <td><?php echo $row['quantity']; ?></td>
                <td>KSh <?php echo number_format($row['price'], 2); ?></td>
                <td>KSh <?php echo number_format($total, 2); ?></td>
                <td><?php echo ucfirst($row['purchase_method']); ?></td>
                <td><?php echo $row['expiry_date'] ? date('M j, Y', strtotime($row['expiry_date'])) : 'N/A'; ?></td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>