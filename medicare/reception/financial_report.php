<?php
// Financial Summary Report
// Get total revenue from consultations and pharmacy
// Financial Summary Report
$revenueQuery = "
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM billing_payments 
         WHERE status = 'paid' 
         AND payment_date BETWEEN ? AND ?) as total_revenue,
        (SELECT COALESCE(SUM(price * quantity), 0) FROM purchase_master 
         WHERE purchase_date BETWEEN ? AND ?) as total_expenses
";
$revenueResult = fetchData(
    $revenueQuery, 
    [
        $startDate . ' 00:00:00',   // e.g. "2026-03-22 00:00:00"
        $endDate   . ' 23:59:59',   // e.g. "2026-03-22 23:59:59"
        $startDate . ' 00:00:00',
        $endDate   . ' 23:59:59'
    ], 
    $database
);
$financials = $revenueResult->fetch_assoc();
$netProfit = $financials['total_revenue'] - $financials['total_expenses'];

// Payment methods breakdown
$paymentMethodsQuery = "
    SELECT payment_method, COUNT(*) as transaction_count, SUM(amount) as total_amount
    FROM billing_payments
    WHERE status = 'paid' 
    AND payment_date BETWEEN ? AND ?
    GROUP BY payment_method
";
$paymentMethods = fetchData(
    $paymentMethodsQuery, 
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59'], 
    $database
);

// Top revenue by product
$topProductsQuery = "
    SELECT bd.product_name, SUM(bd.price * bd.quantity) as revenue
    FROM billing_details bd
    JOIN billing b ON bd.billing_id = b.id
    WHERE b.date BETWEEN ? AND ? AND b.payment_status = 'paid'
    GROUP BY bd.product_name
    ORDER BY revenue DESC
    LIMIT 10
";
$topProducts = fetchData($topProductsQuery, [$startDate." 00:00:00", $endDate." 23:59:59"], $database);
?>

<h2 style="color: var(--primarycolor); margin-bottom: 25px;">Financial Summary Report</h2>

<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-value">KSh <?php echo number_format($financials['total_revenue'], 2); ?></div>
        <div class="summary-label">Total Revenue</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">KSh <?php echo number_format($financials['total_expenses'], 2); ?></div>
        <div class="summary-label">Total Expenses</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">KSh <?php echo number_format($netProfit, 2); ?></div>
        <div class="summary-label">Net Profit</div>
    </div>
    <div class="summary-card">
        <div class="summary-value"><?php echo $netProfit > 0 ? number_format(($netProfit / $financials['total_revenue']) * 100, 1) : 0; ?>%</div>
        <div class="summary-label">Profit Margin</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- Payment Methods -->
    <div>
        <h3 style="color: var(--primarydark); margin-bottom: 15px;">Payment Methods Breakdown</h3>
        <div class="scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Transactions</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $paymentMethods->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo ucfirst($row['payment_method']); ?></td>
                        <td><?php echo $row['transaction_count']; ?></td>
                        <td>KSh <?php echo number_format($row['total_amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products -->
    <div>
        <h3 style="color: var(--primarydark); margin-bottom: 15px;">Top Selling Products</h3>
        <div class="scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $topProducts->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['product_name']; ?></td>
                        <td>KSh <?php echo number_format($row['revenue'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>