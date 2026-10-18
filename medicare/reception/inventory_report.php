<?php
// Inventory Report
$inventoryQuery = "
    SELECT 
        product_company,
        product_name,
        product_unit,
        packing_size,
        product_quantity,
        product_selling_price,
        (product_quantity * product_selling_price) as stock_value
    FROM stock_master
    ORDER BY stock_value DESC
";
$inventoryResult = $database->query($inventoryQuery);

// Inventory summary
$summaryQuery = "
    SELECT 
        COUNT(*) as total_products,
        SUM(product_quantity) as total_quantity,
        SUM(product_quantity * product_selling_price) as total_stock_value
    FROM stock_master
";
$summaryResult = $database->query($summaryQuery);
$inventorySummary = $summaryResult->fetch_assoc();
?>

<h2 style="color: var(--primarycolor); margin-bottom: 25px;">Inventory Report</h2>

<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-value"><?php echo $inventorySummary['total_products']; ?></div>
        <div class="summary-label">Total Products</div>
    </div>
    <div class="summary-card">
        <div class="summary-value"><?php echo number_format($inventorySummary['total_quantity']); ?></div>
        <div class="summary-label">Total Quantity</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">KSh <?php echo number_format($inventorySummary['total_stock_value'], 2); ?></div>
        <div class="summary-label">Total Stock Value</div>
    </div>
</div>

<div class="scroll">
    <table class="report-table">
        <thead>
            <tr>
                <th>Company</th>
                <th>Product Name</th>
                <th>Unit</th>
                <th>Pack Size</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Stock Value</th>
            </tr>
        </thead>
        <tbody>
            <?php if($inventoryResult->num_rows == 0): ?>
            <tr><td colspan="7" style="text-align: center; padding: 20px;">No inventory data found</td></tr>
            <?php else: ?>
            <?php while($row = $inventoryResult->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['product_company']; ?></td>
                <td><?php echo $row['product_name']; ?></td>
                <td><?php echo $row['product_unit']; ?></td>
                <td><?php echo $row['packing_size']; ?></td>
                <td><?php echo $row['product_quantity']; ?></td>
                <td>KSh <?php echo number_format($row['product_selling_price'], 2); ?></td>
                <td>KSh <?php echo number_format($row['stock_value'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>