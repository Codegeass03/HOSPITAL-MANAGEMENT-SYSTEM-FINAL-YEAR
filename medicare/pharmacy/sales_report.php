<?php
// Sales Report
$salesQuery = "
    SELECT 
        b.bill_no,
        b.CustomerName,
        b.date,
        b.bill_type,
        b.payment_status,
        SUM(bd.price * bd.quantity) as total_amount
    FROM billing b
    JOIN billing_details bd ON b.id = bd.billing_id
    WHERE DATE(b.date) BETWEEN ? AND ?
    GROUP BY b.id, b.bill_no, b.CustomerName, b.date, b.bill_type, b.payment_status
    ORDER BY b.date DESC
";
$salesResult = fetchData($salesQuery, [$startDate, $endDate], $database);

// Sales summary
$summaryQuery = "
    SELECT 
        COUNT(DISTINCT b.id) as total_transactions,
        SUM(bd.price * bd.quantity) as gross_sales,
        AVG(bd.price * bd.quantity) as avg_sale,
        COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END) as paid_transactions
    FROM billing b
    JOIN billing_details bd ON b.id = bd.billing_id
    WHERE DATE(b.date) BETWEEN ? AND ?
";
$summaryResult = fetchData($summaryQuery, [$startDate, $endDate], $database);
$salesSummary = $summaryResult->fetch_assoc();
?>

<h2 style="color: var(--primarycolor); margin-bottom: 25px;">Sales Report</h2>

<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-value"><?php echo $salesSummary['total_transactions']; ?></div>
        <div class="summary-label">Total Transactions</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">KSh <?php echo number_format($salesSummary['gross_sales'], 2); ?></div>
        <div class="summary-label">Gross Sales</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">KSh <?php echo number_format($salesSummary['avg_sale'], 2); ?></div>
        <div class="summary-label">Average Sale</div>
    </div>
    <div class="summary-card">
        <div class="summary-value"><?php echo $salesSummary['paid_transactions']; ?></div>
        <div class="summary-label">Paid Transactions</div>
    </div>
</div>

<div class="scroll">
    <table class="report-table">
        <thead>
            <tr>
                <th>Bill #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if($salesResult->num_rows == 0): ?>
            <tr><td colspan="6" style="text-align: center; padding: 20px;">No sales found for the selected period</td></tr>
            <?php else: ?>
            <?php while($row = $salesResult->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['bill_no']; ?></td>
                <td><?php echo $row['CustomerName']; ?></td>
                <td><?php echo date('M j, Y H:i', strtotime($row['date'])); ?></td>
                <td><?php echo ucfirst($row['bill_type']); ?></td>
                <td>KSh <?php echo number_format($row['total_amount'], 2); ?></td>
                <td><span class="status-badge status-<?php echo $row['payment_status']; ?>"><?php echo ucfirst($row['payment_status']); ?></span></td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>