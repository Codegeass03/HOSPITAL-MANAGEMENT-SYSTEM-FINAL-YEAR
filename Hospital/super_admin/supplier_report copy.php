<?php
// Supplier Report
$supplierFilter = $supplierId ? "WHERE s.id = ?" : "";

$supplierQuery = "
    SELECT 
        s.id,
        s.businessname as supplier_name,
        s.contact,
        s.city,
        COUNT(pm.id) as total_purchases,
        SUM(pm.quantity) as total_quantity,
        SUM(pm.price * pm.quantity) as total_value,
        AVG(pm.price) as avg_price
    FROM suppliers s
    LEFT JOIN purchase_master pm ON s.businessname = pm.party_name
    $supplierFilter
    GROUP BY s.id, s.businessname, s.contact, s.city
    ORDER BY total_value DESC
";

$params = $supplierId ? [$supplierId] : [];
$supplierResult = fetchData($supplierQuery, $params, $database);

// Get top products by supplier
$topProductsQuery = "
    SELECT 
        pm.party_name,
        pm.product_name,
        SUM(pm.quantity) as total_qty,
        SUM(pm.price * pm.quantity) as total_value
    FROM purchase_master pm
    WHERE pm.purchase_date BETWEEN ? AND ?
    GROUP BY pm.party_name, pm.product_name
    ORDER BY pm.party_name, total_value DESC
";
$topProductsResult = fetchData($topProductsQuery, [$startDate, $endDate], $database);
?>

<h2 style="color: var(--primarycolor); margin-bottom: 25px;">Supplier Performance Report</h2>

<div class="scroll">
    <table class="report-table">
        <thead>
            <tr>
                <th>Supplier Name</th>
                <th>Contact</th>
                <th>City</th>
                <th>Purchase Orders</th>
                <th>Total Quantity</th>
                <th>Total Value</th>
                <th>Avg Price</th>
            </tr>
        </thead>
        <tbody>
            <?php if($supplierResult->num_rows == 0): ?>
            <tr><td colspan="7" style="text-align: center; padding: 20px;">No supplier data found</td></tr>
            <?php else: ?>
            <?php while($row = $supplierResult->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['supplier_name']; ?></td>
                <td><?php echo $row['contact']; ?></td>
                <td><?php echo $row['city']; ?></td>
                <td><?php echo $row['total_purchases']; ?></td>
                <td><?php echo $row['total_quantity']; ?></td>
                <td>KSh <?php echo number_format($row['total_value'], 2); ?></td>
                <td>KSh <?php echo number_format($row['avg_price'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3 style="color: var(--primarydark); margin: 30px 0 15px 0;">Top Products by Supplier</h3>
<div class="scroll">
    <table class="report-table">
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Product</th>
                <th>Total Qty</th>
                <th>Total Value</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $topProductsResult->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['party_name']; ?></td>
                <td><?php echo $row['product_name']; ?></td>
                <td><?php echo $row['total_qty']; ?></td>
                <td>KSh <?php echo number_format($row['total_value'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>