<?php
include("config/database.php");
include("admin/connection.php");

// Get date range parameters
$startDate = isset($startDate) ? $startDate : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'));
$endDate = isset($endDate) ? $endDate : (isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'));

// Initialize all variables with defaults
$financials = [
    'total_revenue' => 0,
    'total_collected' => 0,
    'total_outstanding' => 0,
    'total_expenses' => 0,
    'pharmacy_revenue' => 0,
    'pharmacy_transactions' => 0
];

$totalHospitalRevenue = 0;
$totalPharmacyRevenue = 0;
$totalExpenses = 0;
$netProfit = 0;
$profitMargin = 0;

// Function to safely execute queries with error handling
function safeQuery($connection, $query, $params = []) {
    try {
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
        return false;
    }
}

// Try to get hospital billing data from different possible databases/tables
$hospitalRevenue = 0;
$hospitalCollected = 0;
$hospitalOutstanding = 0;

// Option 1: Try the standard hospital database billing table (has total_amount column)
if (isset($database)) {
    // First, let's check which database we're in
    $dbResult = $database->query("SELECT DATABASE() as db");
    if ($dbResult) {
        $dbName = $dbResult->fetch_assoc()['db'];
    }
    
    // Try query 1: Standard billing table with total_amount
    $query1 = "
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(paid_amount), 0) as total_collected,
            COALESCE(SUM(balance_amount), 0) as total_outstanding
        FROM billing
        WHERE bill_date BETWEEN ? AND ?
        AND status IN ('paid', 'partial')
    ";
    
    $result1 = safeQuery($database, $query1, [$startDate, $endDate]);
    if ($result1) {
        $data = $result1->fetch_assoc();
        if ($data) {
            $hospitalRevenue = $data['total_revenue'];
            $hospitalCollected = $data['total_collected'];
            $hospitalOutstanding = $data['total_outstanding'];
        }
    } else {
        // If that fails, try query 2: Maybe the column is called grand_total
        $query2 = "
            SELECT 
                COALESCE(SUM(grand_total), 0) as total_revenue,
                COALESCE(SUM(paid_amount), 0) as total_collected,
                COALESCE(SUM(balance_amount), 0) as total_outstanding
            FROM billing
            WHERE bill_date BETWEEN ? AND ?
            AND status IN ('paid', 'partial')
        ";
        
        $result2 = safeQuery($database, $query2, [$startDate, $endDate]);
        if ($result2) {
            $data = $result2->fetch_assoc();
            if ($data) {
                $hospitalRevenue = $data['total_revenue'];
                $hospitalCollected = $data['total_collected'];
                $hospitalOutstanding = $data['total_outstanding'];
            }
        }
    }
}

// Get expenses data
if (isset($database)) {
    $expensesQuery = "
        SELECT 
            COALESCE(SUM(price * quantity), 0) as total_expenses
        FROM purchase_master
        WHERE status = 'received'
        AND purchase_date BETWEEN ? AND ?
    ";
    
    $expensesResult = safeQuery($database, $expensesQuery, [$startDate, $endDate]);
    if ($expensesResult) {
        $expensesData = $expensesResult->fetch_assoc();
        $totalExpenses = $expensesData['total_expenses'] ?? 0;
    }
}

// Try to get pharmacy revenue from multiple sources
$pharmacyRevenue = 0;

// Source 1: Check if pharmacy_billing table exists in hospital database
if (isset($database)) {
    $checkTable = $database->query("SHOW TABLES LIKE 'pharmacy_billing'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $pharmacyQuery = "
            SELECT 
                COALESCE(SUM(total_amount), 0) as pharmacy_revenue,
                COUNT(*) as transaction_count
            FROM pharmacy_billing 
            WHERE payment_status = 'paid'
            AND date BETWEEN ? AND ?
        ";
        
        $pharmacyResult = safeQuery($database, $pharmacyQuery, [$startDate, $endDate]);
        if ($pharmacyResult) {
            $pharmacyData = $pharmacyResult->fetch_assoc();
            if ($pharmacyData) {
                $pharmacyRevenue += $pharmacyData['pharmacy_revenue'] ?? 0;
                $financials['pharmacy_transactions'] += $pharmacyData['transaction_count'] ?? 0;
            }
        }
    }
}

// Source 2: Check php_ims database via $link connection
if (isset($link)) {
    // First select the database
    if (mysqli_select_db($link, "php_ims")) {
        // Check if billing_details table exists (contains amounts)
        $checkDetails = mysqli_query($link, "SHOW TABLES LIKE 'billing_details'");
        if ($checkDetails && mysqli_num_rows($checkDetails) > 0) {
            $phpImsQuery = "
                SELECT 
                    COALESCE(SUM(bd.price), 0) as pharmacy_revenue,
                    COUNT(DISTINCT b.id) as transaction_count
                FROM billing b
                JOIN billing_details bd ON b.id = bd.billing_id
                WHERE b.date BETWEEN ? AND ?
            ";
            
            $stmt = mysqli_prepare($link, $phpImsQuery);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    $phpImsData = mysqli_fetch_assoc($result);
                    if ($phpImsData) {
                        $pharmacyRevenue += $phpImsData['pharmacy_revenue'] ?? 0;
                        $financials['pharmacy_transactions'] += $phpImsData['transaction_count'] ?? 0;
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Calculate totals
$totalHospitalRevenue = $hospitalRevenue;
$totalPharmacyRevenue = $pharmacyRevenue;
$totalGrossRevenue = $totalHospitalRevenue + $totalPharmacyRevenue;
$netProfit = $totalGrossRevenue - $totalExpenses;
$profitMargin = $totalGrossRevenue > 0 ? ($netProfit / $totalGrossRevenue * 100) : 0;

// Get other data for display
$expensesByCategory = null;
$topMedications = null;
$paymentMethods = null;
$pharmacyPaymentMethods = null;

// Get expenses by category
if (isset($database)) {
    $expensesByCategoryQuery = "
        SELECT productcategory, SUM(price * quantity) AS expenses
        FROM purchase_master
        WHERE status = 'received'
        AND purchase_date BETWEEN ? AND ?
        GROUP BY productcategory
        ORDER BY expenses DESC
    ";
    
    $expensesByCategory = safeQuery($database, $expensesByCategoryQuery, [$startDate, $endDate]);
}

// Get top medications from pharmacy_billing_details
if (isset($database)) {
    $topMedicationsQuery = "
        SELECT 
            pbd.product_name,
            SUM(pbd.quantity) as total_quantity,
            SUM(pbd.price * pbd.quantity) as total_revenue,
            COUNT(DISTINCT pb.id) as sale_count
        FROM pharmacy_billing_details pbd
        JOIN pharmacy_billing pb ON pbd.billing_id = pb.id
        WHERE pb.payment_status = 'paid'
        AND pb.date BETWEEN ? AND ?
        GROUP BY pbd.product_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ";
    
    $topMedications = safeQuery($database, $topMedicationsQuery, [$startDate, $endDate]);
}

// Get payment methods from hospital billing
if (isset($database)) {
    $paymentMethodsQuery = "
        SELECT 
            payment_method, 
            COUNT(*) as transaction_count, 
            SUM(paid_amount) as total_amount
        FROM billing 
        WHERE status IN ('paid', 'partial') 
        AND bill_date BETWEEN ? AND ?
        GROUP BY payment_method
        HAVING total_amount > 0
        ORDER BY total_amount DESC
    ";
    
    $paymentMethods = safeQuery($database, $paymentMethodsQuery, [$startDate, $endDate]);
}
?>

<!-- HTML Output -->
<div class="report-header">
    <h2 style="color: #0e7490; margin-bottom: 5px;">Financial Summary Report</h2>
    <p style="color: #64748b; font-size: 14px;">
        <?php echo date('F j, Y', strtotime($startDate)); ?> - <?php echo date('F j, Y', strtotime($endDate)); ?>
    </p>
</div>

<!-- Revenue Breakdown -->
<div class="summary-grid" style="margin-bottom: 20px;">
    <div class="summary-card" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid #3b82f6;">
        <div class="summary-value">KSh <?php echo number_format($totalHospitalRevenue, 2); ?></div>
        <div class="summary-label">Hospital Services Revenue</div>
        <small style="font-size: 12px; color: #4b5563;">
            Collected: KSh <?php echo number_format($hospitalCollected, 2); ?><br>
            Outstanding: KSh <?php echo number_format($hospitalOutstanding, 2); ?>
        </small>
    </div>
    
    <div class="summary-card" style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-left: 4px solid #8b5cf6;">
        <div class="summary-value">KSh <?php echo number_format($totalPharmacyRevenue, 2); ?></div>
        <div class="summary-label">Pharmacy Sales Revenue</div>
        <small style="font-size: 12px; color: #4b5563;"><?php echo $financials['pharmacy_transactions']; ?> transactions</small>
    </div>
    
    <div class="summary-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b;">
        <div class="summary-value">KSh <?php echo number_format($totalExpenses, 2); ?></div>
        <div class="summary-label">Total Expenses</div>
    </div>
</div>

<!-- Net Profit and Margin -->
<div class="summary-grid">
    <div class="summary-card" style="background: <?php echo $netProfit >= 0 ? 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)' : 'linear-gradient(135deg, #fecaca 0%, #fca5a5 100%)'; ?>; border-left: 4px solid <?php echo $netProfit >= 0 ? '#10b981' : '#ef4444'; ?>;">
        <div class="summary-value">KSh <?php echo number_format($netProfit, 2); ?></div>
        <div class="summary-label">Net Profit</div>
        <div style="margin-top: 5px; font-size: 12px;">
            <?php if($netProfit >= 0): ?>
                <span style="color: #065f46; font-weight: 600;">✓ Profit</span>
            <?php else: ?>
                <span style="color: #7f1d1d; font-weight: 600;">✗ Loss</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="summary-card" style="background: <?php echo $profitMargin >= 0 ? 'linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%)' : 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)'; ?>; border-left: 4px solid <?php echo $profitMargin >= 0 ? '#0ea5e9' : '#ef4444'; ?>;">
        <div class="summary-value"><?php echo number_format($profitMargin, 1); ?>%</div>
        <div class="summary-label">Profit Margin</div>
        <small style="font-size: 12px; color: #4b5563;">Gross Revenue: KSh <?php echo number_format($totalGrossRevenue, 2); ?></small>
    </div>
</div>

<?php if ($expensesByCategory && $expensesByCategory->num_rows > 0): ?>
<!-- Expenses by Category -->
<div style="margin-top: 30px; margin-bottom: 30px;">
    <h3 style="color: #0e7490; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #06b6d4;">
        Expenses by Category
    </h3>
    <div class="scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Expenses</th>
                    <th>% of Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $expensesByCategory->fetch_assoc()): 
                    $percentage = $totalExpenses > 0 ? ($row['expenses'] / $totalExpenses * 100) : 0;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['productcategory']); ?></strong></td>
                    <td style="color: #dc2626; font-weight: 600;">KSh <?php echo number_format($row['expenses'], 2); ?></td>
                    <td><?php echo number_format($percentage, 1); ?>%</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f8fafc;">
                    <td style="font-weight: 600;">Total</td>
                    <td style="color: #dc2626; font-weight: 700;">KSh <?php echo number_format($totalExpenses, 2); ?></td>
                    <td>100%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
    <!-- Top Selling Medications -->
    <div>
        <h3 style="color: #0e7490; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #06b6d4;">
            Top Selling Medications
        </h3>
        <div class="scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Revenue</th>
                        <th>Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($topMedications && $topMedications->num_rows > 0): ?>
                        <?php while ($row = $topMedications->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                            <td><?php echo $row['total_quantity']; ?></td>
                            <td style="color: #065f46; font-weight: 600;">KSh <?php echo number_format($row['total_revenue'], 2); ?></td>
                            <td><?php echo $row['sale_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">
                                No medication sales data available.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div>
        <h3 style="color: #0e7490; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #06b6d4;">
            Payment Methods Breakdown
        </h3>
        
        <?php if ($paymentMethods && $paymentMethods->num_rows > 0): ?>
        <div style="margin-bottom: 20px;">
            <h4 style="color: #4b5563; font-size: 14px; margin-bottom: 10px;">Hospital Services</h4>
            <div class="scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Transactions</th>
                            <th>Amount</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $paymentMethods->fetch_assoc()): 
                            $percentage = $totalHospitalRevenue > 0 ? ($row['total_amount'] / $totalHospitalRevenue * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo ucfirst($row['payment_method'] ?: 'cash'); ?></strong></td>
                            <td><?php echo $row['transaction_count']; ?></td>
                            <td style="color: #065f46; font-weight: 600;">KSh <?php echo number_format($row['total_amount'], 2); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Export Button -->
<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
    <button onclick="window.print()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #06b6d4 0%, #0e7490 100%); color: white; border-radius: 8px; font-weight: 600; border: none; cursor: pointer;">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .summary-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    
    .summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }
    
    .summary-label {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
    }
    
    .scroll {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    
    .report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    
    .report-table th {
        background: #f8fafc;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .report-table td {
        padding: 10px 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .report-table tr:hover {
        background-color: #f1f5f9;
    }
    
    button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>